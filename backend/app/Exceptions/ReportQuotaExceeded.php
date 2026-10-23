<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use RuntimeException;

class ReportQuotaExceeded extends RuntimeException
{
    public function __construct(public readonly int $limit, public readonly Carbon $resetsAt)
    {
        parent::__construct("Daily report limit of {$limit} reached.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => __('reports.quota_exceeded', ['limit' => $this->limit]),
            'code' => 'report_quota_exceeded',
            'quota' => ['limit' => $this->limit, 'remaining' => 0, 'resets_at' => $this->resetsAt->toIso8601String()],
        ], 429);
    }
}
