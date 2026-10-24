<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Gratis toegang zonder Stripe, bijvoorbeeld voor het testaccount van Apple en Google,
 * voor jezelf of voor een tester. Raakt een lopend Stripe-abonnement niet aan.
 */
class GiftSubscription extends Command
{
    protected $signature = 'veiligbuiten:gift {email} {--days=365 : Hoe lang} {--revoke : Toegang weer intrekken}';

    protected $description = 'Geeft een account gratis toegang (of trekt die in).';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Geen account met dat e-mailadres.');

            return self::FAILURE;
        }
        if ($user->stripe_subscription_id && in_array($user->subscription_status, ['active', 'cancelled', 'billing_issue'], true)) {
            $this->error('Dit account heeft een lopend Stripe-abonnement; dat beheer je in Stripe.');

            return self::FAILURE;
        }

        $user->forceFill($this->option('revoke')
            ? ['subscription_status' => 'expired', 'subscription_expires_at' => now()]
            : ['subscription_status' => 'gift', 'subscription_expires_at' => now()->addDays((int) $this->option('days'))])->save();
        $this->info($this->option('revoke') ? "Account #{$user->id}: geen toegang meer." : "Account #{$user->id}: toegang tot {$user->subscription_expires_at->format('d-m-Y')}.");

        return self::SUCCESS;
    }
}
