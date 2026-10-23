<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('source_records', function (Blueprint $table) { $table->id(); $table->string('source', 30)->index(); $table->string('external_id', 191); $table->string('payload_hash', 64); $table->json('payload'); $table->timestamp('received_at'); $table->timestamp('processed_at')->nullable(); $table->string('status', 20)->default('received'); $table->timestamps(); $table->unique(['source', 'external_id']); }); }
    public function down(): void { Schema::dropIfExists('source_records'); }
};
