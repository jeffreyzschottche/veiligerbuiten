<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirQualityStation extends Model
{
    protected $fillable = [
        'station_number', 'location', 'municipality', 'organisation', 'latitude', 'longitude',
        'components', 'active', 'last_catalogued_at', 'last_measured_at',
    ];

    protected function casts(): array
    {
        return ['components' => 'array', 'active' => 'boolean', 'last_catalogued_at' => 'datetime', 'last_measured_at' => 'datetime'];
    }
}
