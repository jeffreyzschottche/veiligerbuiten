<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentReport extends Model
{
    protected $fillable = [
        'incident_id',
        'installation_id',
        'user_id',
        'category',
        'subtype',
        'description',
        'latitude',
        'longitude',
        'user_latitude',
        'user_longitude',
        'location_accuracy_m',
        'status',
        'source',
        'moderation_flags',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'moderation_flags' => 'array',
            'reported_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function installation()
    {
        return $this->belongsTo(Installation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
