<?php

namespace App\Services;

class IncidentSafetyPolicy
{
    /** Categories that need an extra independent signal before they are broadly shown or can alert. */
    public function requiresIndependentConfirmation(string $category): bool
    {
        return in_array($category, ['geweld', 'stoffen', 'onveilig'], true);
    }

    public function initialVisibility(string $category): string
    {
        return $this->requiresIndependentConfirmation($category) ? 'review' : 'public';
    }
}
