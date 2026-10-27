<?php

namespace App\Support;

/** Leesbare meldingen uit DATEX-types, ook wanneer de feed geen vrije tekst bevat. */
class NdwIncidentDetails
{
    public static function describe(array $item): array
    {
        $type = strtolower(preg_replace('/^.*:/', '', $item['type'] ?? ''));
        $comment = trim($item['comment'] ?? '');
        $details = strtolower(implode(' ', [$item['subtype'] ?? '', ...($item['subtypes'] ?? [])]));

        [$category, $subtype, $severity, $description] = match (true) {
            str_contains($type, 'accident') || str_contains($type, 'collision') =>
                ['verkeer', 'Verkeersongeval', 4, 'Er is een verkeersongeval gemeld.'],
            str_contains($type, 'roadworks') || str_contains($type, 'maintenance') =>
                ['blokkade', 'Werkzaamheden aan de weg', 2, 'Er zijn werkzaamheden aan de weg.'],
            str_contains($type, 'management') || str_contains($type, 'closure') =>
                ['blokkade', 'Verkeersmaatregel', 2, 'Er is een verkeersmaatregel gemeld. De precieze maatregel is niet bekend.'],
            str_contains($type, 'vehicleobstruction') =>
                ['verkeer', 'Voertuig vormt een obstakel', 3, 'Een voertuig vormt een obstakel voor het verkeer.'],
            str_contains($type, 'obstruction') =>
                ['verkeer', 'Obstakel op de weg', 3, 'Er is een obstakel op de weg gemeld. Het soort obstakel is niet bekend.'],
            str_contains($type, 'poorenvironment') || str_contains($type, 'weather') =>
                ['weer', 'Hinder door weersomstandigheden', 3, 'Weersomstandigheden kunnen het verkeer hinderen.'],
            str_contains($type, 'hazard') || str_contains($type, 'roadsurface') =>
                ['verkeer', 'Gevaarlijke verkeerssituatie', 3, 'Er is een gevaarlijke verkeerssituatie gemeld. Verdere details zijn niet beschikbaar.'],
            default =>
                ['verkeer', 'Verkeerssituatie', 2, 'Er is verkeershinder gemeld. Verdere details zijn niet beschikbaar.'],
        };

        // Alleen concrete details gebruiken; "other" en "stationary" vertellen niet wat er staat.
        [$subtype, $description] = match (true) {
            str_contains($details, 'overturned') => ['Omgevallen voertuig', 'Er is een voertuig omgevallen.'],
            str_contains($details, 'brokendownvehicle') => ['Voertuig met pech', 'Een voertuig met pech vormt een obstakel voor het verkeer.'],
            str_contains($details, 'shedload') => ['Object op de rijbaan', 'Er ligt verloren lading op de rijbaan.'],
            str_contains($details, 'object') => ['Object op de rijbaan', 'Er is een voorwerp op de rijbaan gemeld.'],
            str_contains($details, 'animalson') => ['Dieren op de weg', 'Er zijn dieren op of bij de rijbaan gemeld.'],
            str_contains($details, 'peopleon') || str_contains($details, 'pedestrians') => ['Personen op de rijbaan', 'Er zijn personen op de rijbaan gemeld.'],
            str_contains($details, 'wrongway') => ['Spookrijder', 'Er is een voertuig tegen de rijrichting in gemeld.'],
            str_contains($details, 'roadclosed') || str_contains($details, 'carriagewayclosures') => ['Weg afgesloten', 'De weg of rijbaan is afgesloten.'],
            str_contains($details, 'laneclosures') => ['Rijstrook afgesloten', 'Een of meer rijstroken zijn afgesloten.'],
            str_contains($details, 'oil') => ['Oliespoor op de weg', 'Er is olie op het wegdek gemeld.'],
            str_contains($details, 'ice') || str_contains($details, 'snow') => ['Glad wegdek', 'Er is sneeuw of ijs op het wegdek gemeld.'],
            str_contains($details, 'fog') => ['Slecht zicht door mist', 'Mist beperkt het zicht op de weg.'],
            default => [$subtype, $description],
        };

        $equipment = mb_strtolower($comment);
        if (in_array($equipment, ['pijlwagen', 'botsabsorber', 'chevron'], true)) {
            $subtype = 'Werkvoertuig bij de weg';
            $description = match ($equipment) {
                'pijlwagen' => 'Er is een pijlwagen ingezet bij de weg.',
                'botsabsorber' => 'Er is een werkvoertuig met botsabsorber ingezet bij de weg.',
                default => 'Er is een voertuig met waarschuwingsmarkering ingezet bij de weg.',
            };
        } elseif ($comment !== '' && $comment !== 'Actuele verkeersmelding van NDW.') {
            $description = $comment;
        }

        $context = [];
        $road = trim($item['road'] ?? '');
        if ($road !== '') $context[] = 'Weg: '.$road.'.';
        $carriageway = match ($item['carriageway'] ?? '') {
            'exitSlipRoad' => 'Locatie: afrit.',
            'entrySlipRoad' => 'Locatie: oprit.',
            'connectingCarriageway' => 'Locatie: verbindingsweg.',
            'parallelCarriageway' => 'Locatie: parallelbaan.',
            default => null,
        };
        if ($carriageway) $context[] = $carriageway;
        if ($context) $description = rtrim($description, '. ').'. '.implode(' ', $context);

        return compact('category', 'subtype', 'severity', 'description');
    }
}
