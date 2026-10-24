<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Een buurt waar de politie het afgelopen jaar opvallend veel geweld en straatroof
 * registreerde. Statistiek, geen actuele melding: daarom rustig in de app, nooit een pushbericht.
 */
class NeighbourhoodRisk extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'place', 'latitude', 'longitude', 'radius_m', 'counts', 'total', 'ratio', 'period_from', 'period_to', 'computed_at'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'counts' => 'array',
            'ratio' => 'float',
            'computed_at' => 'datetime',
        ];
    }

    /** Zelfde vorm als een bekende plek, met kind 'stats'; de app toont het als buurtcijfer. */
    public function toSync(): array
    {
        return [
            'key' => 'b'.$this->code,
            'kind' => 'stats',
            'category' => 'geweld',
            'latitude' => round($this->latitude, 5),
            'longitude' => round($this->longitude, 5),
            'radius' => $this->radius_m,
            'count' => $this->total,
            'name' => $this->name,
            'counts' => $this->counts,
        ];
    }
}
