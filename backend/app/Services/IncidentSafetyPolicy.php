<?php

namespace App\Services;

class IncidentSafetyPolicy
{
    /**
     * Categories that need an extra independent signal before they are broadly shown or can alert.
     * Intimidatie bewust niet: het speelt kort en wordt zelden door een tweede persoon gezien, dus
     * wachten op bevestiging maakt de melding nutteloos. DangerScale houdt een losse melding wel op
     * hoogstens 'opletten', en de levensduur is kort.
     */
    public function requiresIndependentConfirmation(string $category): bool
    {
        return in_array($category, ['geweld', 'stoffen', 'onveilig'], true);
    }

    public function initialVisibility(string $category): string
    {
        return $this->requiresIndependentConfirmation($category) ? 'review' : 'public';
    }
}
