<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CategoryConfig;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'categories' => CategoryConfig::all(),
            'statuses' => CategoryConfig::STATUSES,
        ]);
    }
}
