<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('visibility', 20)->default('public')->index()->after('status');
            $table->timestamp('first_observed_at')->nullable()->index()->after('resolved_at');
            $table->timestamp('last_activity_at')->nullable()->index()->after('first_observed_at');
            $table->timestamp('archived_at')->nullable()->index()->after('last_activity_at');
            $table->unsignedSmallInteger('location_precision_m')->nullable()->after('longitude');
            $table->json('risk_flags')->nullable()->after('geometry');
        });

        Schema::table('incident_reports', function (Blueprint $table) {
            $table->decimal('user_latitude', 10, 7)->nullable()->after('longitude');
            $table->decimal('user_longitude', 10, 7)->nullable()->after('user_latitude');
            $table->unsignedSmallInteger('location_accuracy_m')->nullable()->after('user_longitude');
            $table->string('source', 30)->default('user')->after('status');
            $table->json('moderation_flags')->nullable()->after('source');
            $table->timestamp('reported_at')->nullable()->index()->after('moderation_flags');
        });

        Schema::create('incident_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30)->index();
            $table->string('source_reference', 191)->nullable();
            $table->string('kind', 30)->index();
            $table->string('status', 20)->default('accepted')->index();
            $table->string('category', 30)->nullable();
            $table->string('subtype', 80)->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('observed_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['source', 'source_reference']);
            $table->index(['incident_id', 'kind', 'observed_at']);
        });

        Schema::create('incident_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->string('vote', 20)->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('distance_m')->nullable();
            $table->timestamps();
            $table->unique(['incident_id', 'installation_id']);
        });

        Schema::table('moderation_events', function (Blueprint $table) {
            $table->foreignId('incident_id')->nullable()->after('installation_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('moderation_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('incident_id');
        });
        Schema::dropIfExists('incident_votes');
        Schema::dropIfExists('incident_observations');
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->dropColumn(['user_latitude', 'user_longitude', 'location_accuracy_m', 'source', 'moderation_flags', 'reported_at']);
        });
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['visibility', 'first_observed_at', 'last_activity_at', 'archived_at', 'location_precision_m', 'risk_flags']);
        });
    }
};
