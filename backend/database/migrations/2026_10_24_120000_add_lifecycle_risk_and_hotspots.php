<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            // Bronvermelding per item (verplicht bij o.a. Zwaailicht) en link naar het originele bericht.
            $table->string('source_url', 500)->nullable()->after('source_reference');
            // Delta-sync vraagt 'wat veranderde sinds' binnen een gebied.
            $table->index(['updated_at']);
        });

        // Eén stem per account per melding, niet per installatie: nieuwe installaties
        // aanmaken mag nooit extra stemmen opleveren.
        Schema::table('incident_votes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('installation_id')->constrained()->nullOnDelete();
            $table->unique(['incident_id', 'user_id']);
        });

        Schema::create('danger_hotspots', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('category', 30);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_m');
            $table->unsignedSmallInteger('incidents_count');
            $table->unsignedTinyInteger('days_count');
            $table->decimal('score', 6, 2);
            $table->timestamp('last_incident_at');
            $table->timestamp('computed_at')->index();
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('danger_hotspots');
        Schema::table('incident_votes', function (Blueprint $table) {
            $table->dropUnique(['incident_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['updated_at']);
            $table->dropColumn('source_url');
        });
    }
};
