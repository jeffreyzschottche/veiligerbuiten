<?php

namespace App\Mcp;

use App\Models\AlertDelivery;
use App\Models\Incident;
use App\Models\User;
use App\Support\DangerScale;
use App\Support\GeoUtils;
use App\Support\IncidentPresenter;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/** De tools die een AI-assistent namens de gebruiker kan aanroepen. Alles is alleen-lezen. */
class VeiligBuitenTools
{
    public function definitions(): array
    {
        $readOnly = ['readOnlyHint' => true, 'openWorldHint' => false];

        return [
            [
                'name' => 'get_situations_nearby',
                'title' => 'Situaties in de buurt',
                'description' => 'Actuele veiligheidssituaties (brand, geweld, bommeldingen, verkeer, noodweer, enz.) rond een plek in Nederland. Geef de naam van een opgeslagen plek van de gebruiker, of coördinaten. Weet je alleen een adres of station, gebruik dan de coördinaten die je zelf kent. Zonder argumenten wordt de eerste opgeslagen plek gebruikt.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'place' => ['type' => 'string', 'description' => 'Naam van een opgeslagen plek, bijvoorbeeld "Thuis".'],
                        'latitude' => ['type' => 'number', 'minimum' => 50.5, 'maximum' => 53.7],
                        'longitude' => ['type' => 'number', 'minimum' => 3.1, 'maximum' => 7.3],
                        'radius_km' => ['type' => 'number', 'minimum' => 0.2, 'maximum' => 30, 'default' => 3],
                        'min_level' => ['type' => 'string', 'enum' => ['info', 'warn', 'danger'], 'default' => 'info'],
                    ],
                ],
                'annotations' => $readOnly,
            ],
            [
                'name' => 'list_places',
                'title' => 'Opgeslagen plekken',
                'description' => 'De plekken die de gebruiker in VeiligerBuiten heeft opgeslagen (zoals Thuis of Werk), met de straal waarbinnen meldingen komen.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                'annotations' => $readOnly,
            ],
            [
                'name' => 'get_recent_alerts',
                'title' => 'Recente meldingen',
                'description' => 'De meldingen die de gebruiker recent als pushbericht heeft gekregen.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['hours' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 48, 'default' => 24]],
                ],
                'annotations' => $readOnly,
            ],
            [
                'name' => 'get_incident',
                'title' => 'Details van een situatie',
                'description' => 'Alle details van één situatie, op id.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer']],
                    'required' => ['id'],
                ],
                'annotations' => $readOnly,
            ],
        ];
    }

    /** @return array{0: string, 1: array} tekstsamenvatting en gestructureerde data */
    public function call(User $user, string $name, array $arguments): array
    {
        return match ($name) {
            'get_situations_nearby' => $this->situationsNearby($user, $arguments),
            'list_places' => $this->listPlaces($user),
            'get_recent_alerts' => $this->recentAlerts($user, $arguments),
            'get_incident' => $this->incident($arguments),
            default => throw new InvalidArgumentException("Unknown tool: {$name}"),
        };
    }

    private function situationsNearby(User $user, array $arguments): array
    {
        [$latitude, $longitude, $label] = $this->resolveLocation($user, $arguments);
        $radiusKm = min(30, max(0.2, (float) ($arguments['radius_km'] ?? 3)));
        $minRank = DangerScale::rank($arguments['min_level'] ?? 'info');
        $latDelta = $radiusKm / 111;
        $lonDelta = $radiusKm / (111 * cos(deg2rad($latitude)));

        $items = Incident::query()
            ->where('visibility', 'public')->where('expires_at', '>', now())->whereNull('resolved_at')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lonDelta, $longitude + $lonDelta])
            ->get()
            ->filter(fn (Incident $i) => GeoUtils::distanceMeters($latitude, $longitude, $i->latitude, $i->longitude) <= $radiusKm * 1000)
            ->filter(fn (Incident $i) => DangerScale::rank(DangerScale::level($i)) >= $minRank)
            ->map(fn (Incident $i) => IncidentPresenter::present($i, $latitude, $longitude))
            ->sortBy(fn (array $i) => [-DangerScale::rank($i['level']), $i['distance']])
            ->take(25)->values()->all();

        $lines = array_map(fn (array $i) => sprintf(
            '- [%s] %s, %s, %s%s',
            __('alerts.level.'.$i['level']), $i['title'], IncidentPresenter::distanceText($i['distance']),
            $i['sourceName'] ?? __('mcp.confirmed', ['count' => $i['confirmations']]),
            ' (id '.$i['id'].', '.Carbon::parse($i['updatedAt'])->diffForHumans().')',
        ), $items);

        $text = $items
            ? __('mcp.header', ['label' => $label, 'count' => count($items), 'radius' => $radiusKm])."\n".implode("\n", $lines)
            : __('mcp.none', ['label' => $label, 'radius' => $radiusKm]);

        return [$text, ['location' => compact('latitude', 'longitude', 'label'), 'radius_km' => $radiusKm, 'situations' => $items]];
    }

    private function listPlaces(User $user): array
    {
        $places = $user->places()->orderBy('id')->get(['id', 'name', 'label', 'latitude', 'longitude', 'radius_m', 'alerts_enabled'])->toArray();
        $text = $places
            ? implode("\n", array_map(fn ($p) => "- {$p['name']}".($p['label'] ? " ({$p['label']})" : '')." · {$p['radius_m']} m", $places))
            : __('mcp.no_places');

        return [$text, ['places' => $places]];
    }

    private function recentAlerts(User $user, array $arguments): array
    {
        $hours = min(48, max(1, (int) ($arguments['hours'] ?? 24)));
        $alerts = AlertDelivery::query()->with('incident')->where('user_id', $user->id)
            ->where('created_at', '>', now()->subHours($hours))->latest()->get()
            ->unique('incident_id')->filter(fn ($a) => $a->incident)
            ->map(fn ($a) => ['level' => $a->level, 'sent_at' => $a->created_at->toIso8601String(), 'place_id' => $a->place_id, 'incident' => IncidentPresenter::present($a->incident)])
            ->values()->all();
        $text = $alerts
            ? implode("\n", array_map(fn ($a) => "- {$a['incident']['title']} (".__('alerts.level.'.$a['level']).', '.Carbon::parse($a['sent_at'])->diffForHumans().')', $alerts))
            : __('mcp.no_alerts', ['hours' => $hours]);

        return [$text, ['alerts' => $alerts]];
    }

    private function incident(array $arguments): array
    {
        $incident = Incident::query()->where('visibility', 'public')->find((int) ($arguments['id'] ?? 0));
        if (! $incident) {
            throw new InvalidArgumentException(__('mcp.not_found'));
        }
        $data = IncidentPresenter::present($incident);

        return [json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ['situation' => $data]];
    }

    /** @return array{float, float, string} */
    private function resolveLocation(User $user, array $arguments): array
    {
        if (isset($arguments['latitude'], $arguments['longitude'])) {
            $lat = (float) $arguments['latitude'];
            $lng = (float) $arguments['longitude'];
            if (! GeoUtils::isInNetherlands($lat, $lng)) {
                throw new InvalidArgumentException(__('mcp.nl_only'));
            }

            return [$lat, $lng, __('mcp.around', ['lat' => round($lat, 4), 'lng' => round($lng, 4)])];
        }

        $query = $user->places()->orderBy('id');
        if (! empty($arguments['place'])) {
            $query->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($arguments['place']))]);
        }
        $place = $query->first();
        if (! $place) {
            throw new InvalidArgumentException(__('mcp.place_not_found'));
        }

        return [$place->latitude, $place->longitude, $place->name];
    }
}
