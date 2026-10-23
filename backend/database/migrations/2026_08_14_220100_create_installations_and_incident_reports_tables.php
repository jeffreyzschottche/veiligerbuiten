<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('installations', function (Blueprint $table) { $table->id(); $table->string('token', 64)->unique(); $table->decimal('last_latitude', 10, 7)->nullable(); $table->decimal('last_longitude', 10, 7)->nullable(); $table->json('alert_preferences')->nullable(); $table->timestamp('last_seen_at')->nullable(); $table->timestamps(); });
        Schema::create('incident_reports', function (Blueprint $table) { $table->id(); $table->foreignId('incident_id')->constrained()->cascadeOnDelete(); $table->foreignId('installation_id')->constrained()->cascadeOnDelete(); $table->string('category', 30); $table->string('subtype', 80); $table->text('description')->nullable(); $table->decimal('latitude', 10, 7); $table->decimal('longitude', 10, 7); $table->string('status', 20); $table->timestamps(); $table->unique(['incident_id', 'installation_id']); });
    }
    public function down(): void { Schema::dropIfExists('incident_reports'); Schema::dropIfExists('installations'); }
};
