<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceRecord extends Model
{
    protected $fillable = [
        'source', 'external_id', 'payload_hash', 'payload', 'received_at', 'processed_at',
        'last_seen_at', 'missing_since', 'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array', 'received_at' => 'datetime', 'processed_at' => 'datetime',
            'last_seen_at' => 'datetime', 'missing_since' => 'datetime',
        ];
    }
}
