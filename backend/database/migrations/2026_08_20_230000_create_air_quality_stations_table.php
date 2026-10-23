<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('air_quality_stations', function (Blueprint $table) {
            $table->id();
            $table->string('station_number', 30)->unique();
            $table->string('location')->nullable();
            $table->string('municipality')->nullable()->index();
            $table->string('organisation')->nullable();
            $table->decimal('latitude', 10, 7)->nullable()->index();
            $table->decimal('longitude', 10, 7)->nullable()->index();
            $table->json('components')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_catalogued_at')->nullable();
            $table->timestamp('last_measured_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('air_quality_stations'); }
};
