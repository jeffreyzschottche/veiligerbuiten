<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Een belangrijke gebeurtenis voor de beheerder: een betaling, een bron die faalt, een fout. */
class OpsEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'level', 'title', 'message', 'data', 'fingerprint', 'occurrences', 'last_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'last_at' => 'datetime'];
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'level' => $this->level,
            'title' => $this->title,
            'message' => $this->message,
            'count' => $this->occurrences,
            'at' => $this->created_at->toIso8601String(),
            'last_at' => $this->last_at->toIso8601String(),
            'data' => $this->data,
        ];
    }
}
