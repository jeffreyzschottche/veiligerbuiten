<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buurten met opvallend veel geweld en straatroof volgens de politiecijfers (CBS, per maand).
        Schema::create('neighbourhood_risks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 120);
            $table->string('place', 80)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_m');
            $table->json('counts');
            $table->unsignedSmallInteger('total');
            $table->decimal('ratio', 8, 1);
            $table->string('period_from', 8);
            $table->string('period_to', 8);
            $table->timestamp('computed_at')->index();
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neighbourhood_risks');
    }
};
