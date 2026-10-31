<?php

use App\Services\AreaSync;
use App\Services\IncidentLifecycle;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (app(IncidentLifecycle::class)->archiveDisabledSources() > 0) {
            AreaSync::bump();
        }
    }

    public function down(): void
    {
        // Oude meldingen niet opnieuw als actuele situaties publiceren.
    }
};
