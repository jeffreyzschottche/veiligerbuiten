<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->default('nl')->after('password');
            $table->string('subscription_status', 20)->default('none')->after('locale');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_status');
            $table->json('alert_filters')->nullable()->after('subscription_expires_at');
            $table->unsignedSmallInteger('check_interval_minutes')->default(30)->after('alert_filters');
            $table->json('quiet_hours')->nullable()->after('check_interval_minutes');
            $table->string('ai_token_hash', 64)->nullable()->unique()->after('quiet_hours');
            $table->text('ai_token')->nullable()->after('ai_token_hash');
            $table->string('webhook_url', 500)->nullable()->after('ai_token');
            $table->text('webhook_secret')->nullable()->after('webhook_url');
        });

        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('label', 120)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_m')->default(1000);
            $table->boolean('alerts_enabled')->default(true);
            $table->timestamps();
            $table->index(['latitude', 'longitude']);
        });

        Schema::table('installations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('alert_deliveries', function (Blueprint $table) {
            // Gebundelde en toestelloze meldingen hebben (nog) geen installatie.
            $table->foreignId('installation_id')->nullable()->change();
            $table->foreignId('user_id')->nullable()->after('installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('alert_deliveries', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropConstrainedForeignId('place_id');
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('installations', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::dropIfExists('places');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ai_token_hash']);
            $table->dropColumn(['locale', 'subscription_status', 'subscription_expires_at', 'alert_filters', 'check_interval_minutes', 'quiet_hours', 'ai_token_hash', 'ai_token', 'webhook_url', 'webhook_secret']);
        });
    }
};
