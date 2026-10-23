<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationEvent extends Model
{
    protected $fillable = ['installation_id', 'incident_id', 'action', 'reason', 'context'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }
}
