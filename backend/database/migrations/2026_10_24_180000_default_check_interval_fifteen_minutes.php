<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Nieuwe accounts kijken standaard elk kwartier; bestaande keuzes blijven staan.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('check_interval_minutes')->default(15)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('check_interval_minutes')->default(30)->change();
        });
    }
};
