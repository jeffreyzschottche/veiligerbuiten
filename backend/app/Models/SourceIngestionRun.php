<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceIngestionRun extends Model
{
    protected $fillable = [
        'source', 'status', 'records_seen', 'incidents_created', 'incidents_updated',
        'error_message', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
