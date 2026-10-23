<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReportQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Alles wat de app over het account nodig heeft, in één verzoek. */
class AccountController extends Controller
{
    public function show(Request $request, ReportQuota $quota): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user(), $quota)]);
    }

    public function updateSettings(Request $request, ReportQuota $quota): JsonResponse
    {
        $categories = array_keys(\App\Support\CategoryConfig::CATEGORIES);
        $data = $request->validate([
            'locale' => 'sometimes|in:nl,en',
            'check_interval_minutes' => ['sometimes', 'integer', Rule::in(config('veiligbuiten.check_intervals'))],
            'alert_filters' => 'sometimes|array',
            'alert_filters.categories' => 'sometimes|array',
            'alert_filters.categories.*' => ['string', Rule::in($categories)],
            'alert_filters.min_level' => 'sometimes|in:info,warn,danger',
            'quiet_hours' => 'sometimes|nullable|array',
            'quiet_hours.enabled' => 'sometimes|boolean',
            'quiet_hours.from' => 'sometimes|date_format:H:i',
            'quiet_hours.to' => 'sometimes|date_format:H:i',
        ]);

        $user = $request->user();
        if (isset($data['alert_filters'])) {
            $data['alert_filters'] = [...$user->alertFilters(), ...$data['alert_filters']];
        }
        $user->update($data);

        return response()->json(['data' => $this->payload($user->fresh(), $quota)]);
    }

    private function payload(User $user, ReportQuota $quota): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'is_admin' => (bool) $user->is_admin,
            'subscription' => [
                'active' => $user->hasActiveSubscription(),
                'status' => $user->subscription_status,
                'expires_at' => $user->subscription_expires_at?->toIso8601String(),
            ],
            'settings' => [
                'alert_filters' => $user->alertFilters(),
                'check_interval_minutes' => $user->check_interval_minutes,
                'quiet_hours' => $user->quiet_hours ?? ['enabled' => false, 'from' => '23:00', 'to' => '07:00'],
            ],
            'report_quota' => $quota->summary($user),
            'ai' => ['connected' => $user->ai_token_hash !== null, 'webhook' => $user->webhook_url !== null],
        ];
    }
}
