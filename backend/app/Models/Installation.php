<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Installation extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'last_latitude',
        'last_longitude',
        'last_location_at',
        'last_location_accuracy_m',
        'alert_preferences',
        'last_seen_at',
        'trust_score',
        'blocked_at',
        'push_token',
        'push_platform',
    ];

    protected function casts(): array
    {
        return [
            'alert_preferences' => 'array',
            'last_latitude' => 'float',
            'last_longitude' => 'float',
            'last_seen_at' => 'datetime',
            'last_location_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function votes()
    {
        return $this->hasMany(IncidentVote::class);
    }

    public function reports()
    {
        return $this->hasMany(IncidentReport::class);
    }

    public function observations()
    {
        return $this->hasMany(IncidentObservation::class);
    }

    public function alertDeliveries()
    {
        return $this->hasMany(AlertDelivery::class);
    }
}
