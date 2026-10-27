<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /** Standaard: alles behalve losse info-meldingen. */
    public const DEFAULT_ALERT_FILTERS = [
        'categories' => ['brand', 'geweld', 'intimidatie', 'medisch', 'onveilig', 'verkeer', 'blokkade', 'weer', 'stoffen', 'drukte', 'dier', 'anders'],
        'min_level' => 'warn',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'alert_filters',
        'check_interval_minutes',
        'quiet_hours',
        'webhook_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'ai_token',
        'ai_token_hash',
        'webhook_secret',
        'stripe_customer_id',
        'stripe_subscription_id',
        'billing_provider',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'subscription_expires_at' => 'datetime',
        'alert_filters' => 'array',
        'quiet_hours' => 'array',
        'check_interval_minutes' => 'integer',
        'ai_token' => 'encrypted',
        'webhook_secret' => 'encrypted',
    ];

    protected $appends = ['subscribed'];

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification(welcome: $this->wasRecentlyCreated));
    }

    public function reports()
    {
        return $this->hasMany(IncidentReport::class);
    }

    public function places()
    {
        return $this->hasMany(Place::class);
    }

    public function installations()
    {
        return $this->hasMany(Installation::class);
    }

    public function hasActiveSubscription(): bool
    {
        if (config('veiligbuiten.billing_bypass') || $this->is_admin) {
            return true;
        }

        return $this->subscription_expires_at?->isFuture() ?? false;
    }

    /**
     * Eén abonnement per account. Loopt er al toegang via een ander kanaal (website, winkel of
     * cadeau), dan negeren we een einde of opzegging van dit kanaal, en neemt een nieuwe betaling
     * het alleen over van een cadeau of als die langer doorloopt.
     */
    public function acceptsBillingFrom(string $provider, bool $live, Carbon $expiresAt): bool
    {
        $runningElsewhere = $this->billing_provider !== $provider
            && in_array($this->subscription_status, ['active', 'cancelled', 'billing_issue', 'gift'], true)
            && ($this->subscription_expires_at?->isFuture() ?? false);

        return ! $runningElsewhere || ($live && ($this->subscription_status === 'gift' || $expiresAt->gt($this->subscription_expires_at)));
    }

    /** Waar het lopende, betaalde abonnement loopt: stripe, app_store, play_store of revenuecat. */
    public function paidVia(): ?string
    {
        return $this->billing_provider !== null && $this->subscription_status !== 'gift' && ($this->subscription_expires_at?->isFuture() ?? false)
            ? $this->billing_provider
            : null;
    }

    public function getSubscribedAttribute(): bool
    {
        return $this->hasActiveSubscription();
    }

    public function alertFilters(): array
    {
        return [...self::DEFAULT_ALERT_FILTERS, ...($this->alert_filters ?? [])];
    }

    /** Maakt een nieuw persoonlijk AI-token; het oude werkt direct niet meer. */
    public function rotateAiToken(): string
    {
        $token = 'vb_'.Str::random(40);
        $this->forceFill(['ai_token' => $token, 'ai_token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    public static function findByAiToken(?string $token): ?self
    {
        return $token ? self::where('ai_token_hash', hash('sha256', $token))->first() : null;
    }

    public function isQuietNow(): bool
    {
        $quiet = $this->quiet_hours;
        if (! ($quiet['enabled'] ?? false)) {
            return false;
        }

        $now = now(config('veiligbuiten.timezone'))->format('H:i');
        [$from, $to] = [$quiet['from'] ?? '23:00', $quiet['to'] ?? '07:00'];

        return $from <= $to ? ($now >= $from && $now < $to) : ($now >= $from || $now < $to);
    }
}
