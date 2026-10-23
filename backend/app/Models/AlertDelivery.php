<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertDelivery extends Model
{
    protected $fillable = [
        'incident_id',
        'installation_id',
        'event_key',
        'level',
        'payload',
        'reason',
        'status',
        'sent_at',
        'opened_at',
        'failed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'failed_at' => 'datetime',
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
}
