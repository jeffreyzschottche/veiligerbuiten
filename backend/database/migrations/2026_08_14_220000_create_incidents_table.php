<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30)->index();
            $table->string('subtype', 80);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('gemeld')->index();
            $table->string('source', 20)->default('user');
            $table->decimal('latitude', 10, 7)->index();
            $table->decimal('longitude', 10, 7)->index();
            $table->unsignedInteger('confirmations_count')->default(1);
            $table->unsignedInteger('resolved_count')->default(0);
            $table->timestamp('last_confirmed_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
