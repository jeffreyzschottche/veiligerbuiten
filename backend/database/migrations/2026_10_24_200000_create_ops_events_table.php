<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Logboek van belangrijke gebeurtenissen (betalingen, bronnen, fouten) voor de beheer-CMS.
    public function up(): void
    {
        Schema::create('ops_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60);
            $table->string('level', 10); // info, warning, error
            $table->string('title', 160);
            $table->string('message', 1000)->nullable();
            $table->json('data')->nullable();
            $table->string('fingerprint', 64)->nullable()->index();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_at');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_events');
    }
};
