<?php

use App\Http\Controllers\McpController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/mcp/{token?}', [McpController::class, 'handle'])->where('token', 'vb_[A-Za-z0-9]+');
