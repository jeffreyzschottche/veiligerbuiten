<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'category', 'subtype', 'description', 'status', 'source', 'source_reference', 'latitude',
        'longitude', 'confirmations_count', 'resolved_count', 'last_confirmed_at', 'expires_at',
        'severity', 'confidence', 'incident_type', 'geometry', 'resolved_at', 'visibility',
        'first_observed_at', 'last_activity_at', 'archived_at', 'location_precision_m', 'risk_flags',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'last_confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
            'resolved_at' => 'datetime',
            'first_observed_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'archived_at' => 'datetime',
            'geometry' => 'array',
            'risk_flags' => 'array',
            'confidence' => 'float',
        ];
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
