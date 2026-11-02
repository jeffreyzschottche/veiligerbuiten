<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            // Een kleur uit het vaste palet (config veiligbuiten.places.colors), voor de cirkel op de kaart.
            $table->string('color', 16)->default('ink')->after('radius_m');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
