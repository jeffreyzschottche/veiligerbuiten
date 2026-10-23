<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('source_reference', 191)->nullable()->after('source');
            $table->unique(['source', 'source_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_reference']);
            $table->dropColumn('source_reference');
        });
    }
};
