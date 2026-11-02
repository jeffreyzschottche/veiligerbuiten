<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    protected $fillable = ['name', 'label', 'latitude', 'longitude', 'radius_m', 'color', 'alerts_enabled'];

    /** Zelfde standaard als de kolom, zodat een net aangemaakte plek ook meteen een kleur heeft. */
    protected $attributes = ['color' => 'ink'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_m' => 'integer',
            'alerts_enabled' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
