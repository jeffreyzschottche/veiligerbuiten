<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->unsignedTinyInteger('severity')->default(2)->index();
            $table->decimal('confidence', 3, 2)->default(0.20)->index();
            $table->string('incident_type', 30)->default('event')->index();
            $table->json('geometry')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
        });
    }
    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) { $table->dropColumn(['severity', 'confidence', 'incident_type', 'geometry', 'resolved_at']); });
    }
};
