<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('source_ingestion_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30)->index();
            $table->string('status', 20)->default('running')->index();
            $table->unsignedInteger('records_seen')->default(0);
            $table->unsignedInteger('incidents_created')->default(0);
            $table->unsignedInteger('incidents_updated')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::table('source_records', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->index()->after('processed_at');
            $table->timestamp('missing_since')->nullable()->index()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('source_records', function (Blueprint $table) {
            $table->dropColumn(['last_seen_at', 'missing_since']);
        });
        Schema::dropIfExists('source_ingestion_runs');
    }
};
