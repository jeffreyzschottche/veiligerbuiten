<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentVote extends Model
{
    protected $fillable = ['incident_id', 'installation_id', 'vote', 'latitude', 'longitude', 'distance_m'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }
}
