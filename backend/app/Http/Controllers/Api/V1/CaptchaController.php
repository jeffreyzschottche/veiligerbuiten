<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ProofOfWorkCaptcha;
use Illuminate\Http\JsonResponse;

class CaptchaController extends Controller
{
    public function show(ProofOfWorkCaptcha $captcha): JsonResponse
    {
        return response()->json(['data' => $captcha->challenge()]);
    }
}
