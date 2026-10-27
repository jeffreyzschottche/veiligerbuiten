<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // stripe, app_store, play_store of revenuecat (cadeau uit RevenueCat); null = geen of een cadeau van ons.
            $table->string('billing_provider', 16)->nullable()->after('subscription_expires_at');
        });

        DB::table('users')->whereNotNull('stripe_subscription_id')->update(['billing_provider' => 'stripe']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('billing_provider');
        });
    }
};
