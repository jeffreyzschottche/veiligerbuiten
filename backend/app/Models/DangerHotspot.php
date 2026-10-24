<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Een plek waar de afgelopen weken herhaaldelijk iets ernstigs gebeurde. */
class DangerHotspot extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'category', 'latitude', 'longitude', 'radius_m', 'incidents_count', 'days_count', 'score', 'last_incident_at', 'computed_at'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'score' => 'float',
            'last_incident_at' => 'datetime',
            'computed_at' => 'datetime',
        ];
    }

    /** Compact voor de sync: elke byte telt bij duizenden telefoons. */
    public function toSync(): array
    {
        return [
            'key' => $this->key,
            'category' => $this->category,
            'latitude' => round($this->latitude, 5),
            'longitude' => round($this->longitude, 5),
            'radius' => $this->radius_m,
            'count' => $this->incidents_count,
            'days' => $this->days_count,
            'lastAt' => $this->last_incident_at->toIso8601String(),
        ];
    }
}
