<?php

namespace App\Services;

class IncidentSafetyPolicy
{
    /** Categories that need an extra independent signal before they are broadly shown or can alert. */
    public function requiresIndependentConfirmation(string $category): bool
    {
        return in_array($category, ['geweld', 'stoffen', 'onveilig'], true);
    }

    public function expiryHours(string $category, ?string $subtype = null): int
    {
        $subtype = mb_strtolower($subtype ?? '');
        if (str_contains($subtype, 'rell') || str_contains($subtype, 'vechtpartij') || str_contains($subtype, 'bedreiging')) return 2;
        if (str_contains($subtype, 'weg geblokkeerd') || str_contains($subtype, 'politieafzetting')) return 6;
        return match ($category) {
            'brand', 'stoffen' => 8,
            'blokkade', 'weer' => 6,
            'verkeer' => 4,
            default => 2,
        };
    }

    public function initialVisibility(string $category): string
    {
        return $this->requiresIndependentConfirmation($category) ? 'review' : 'public';
    }
}
