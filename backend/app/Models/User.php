<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /** Standaard: alles behalve losse info-meldingen. */
    public const DEFAULT_ALERT_FILTERS = [
        'categories' => ['brand', 'geweld', 'onveilig', 'verkeer', 'blokkade', 'weer', 'stoffen', 'drukte', 'dier', 'anders'],
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
