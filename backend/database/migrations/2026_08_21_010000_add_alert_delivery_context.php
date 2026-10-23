<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('installations', 'last_location_at')) {
            Schema::table('installations', function (Blueprint $table) {
                $table->timestamp('last_location_at')->nullable()->index()->after('last_seen_at');
                $table->unsignedSmallInteger('last_location_accuracy_m')->nullable()->after('last_location_at');
            });
        }
        Schema::table('alert_deliveries', function (Blueprint $table) {
            // MySQL may use the original composite unique index for incident_id's foreign key.
            $table->index('incident_id', 'alert_deliveries_incident_fk_index');
            $table->dropUnique(['incident_id', 'installation_id']);
            $table->string('event_key', 100)->after('installation_id');
            $table->string('level', 20)->after('event_key');
            $table->json('payload')->nullable()->after('level');
            $table->string('reason', 160)->nullable()->after('payload');
            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->timestamp('failed_at')->nullable()->after('opened_at');
            $table->text('error_message')->nullable()->after('failed_at');
            $table->unique(['incident_id', 'installation_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::table('alert_deliveries', function (Blueprint $table) {
            $table->dropUnique(['incident_id', 'installation_id', 'event_key']);
            $table->unique(['incident_id', 'installation_id']);
            $table->dropColumn(['event_key', 'level', 'payload', 'reason', 'opened_at', 'failed_at', 'error_message']);
        });
        Schema::table('installations', function (Blueprint $table) {
            $table->dropColumn(['last_location_at', 'last_location_accuracy_m']);
        });
    }
};
