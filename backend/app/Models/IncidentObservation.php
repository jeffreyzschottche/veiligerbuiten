<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentObservation extends Model
{
    protected $fillable = [
        'incident_id', 'installation_id', 'source', 'source_reference', 'kind', 'status',
        'category', 'subtype', 'description', 'latitude', 'longitude', 'observed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float', 'longitude' => 'float', 'observed_at' => 'datetime', 'metadata' => 'array',
        ];
    }
}
