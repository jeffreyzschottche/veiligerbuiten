<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\SourceStatusController;
use App\Http\Controllers\Api\V1\IncidentController;
use App\Http\Controllers\Api\V1\InstallationController;
use App\Http\Controllers\Api\V1\AlertDeliveryController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\CaptchaController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\AdminController;

Route::prefix('v1')->group(function () {

    // Health & status (no throttle needed)
    Route::get('/health', HealthController::class);
    Route::get('/sources/status', SourceStatusController::class);

    // Categories config
    Route::get('/categories', CategoryController::class)->middleware('throttle:60,1');

    // Auth routes
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:10,1'])
        ->name('verification.verify');

    // Authenticated user routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/email/resend', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:3,1');

        // Profile management
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
        Route::get('/profile/reports', [ProfileController::class, 'reports']);
    });

    // Installation (device) routes
    Route::post('/installations', [InstallationController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::put('/installations/preferences', [InstallationController::class, 'preferences'])
        ->middleware('throttle:20,1');
    Route::put('/installations/push-token', [InstallationController::class, 'pushToken'])
        ->middleware('throttle:10,1');
    Route::put('/installations/location', [InstallationController::class, 'location'])
        ->middleware('throttle:12,1');

    // Captcha
    Route::get('/captcha/challenge', [CaptchaController::class, 'show'])
        ->middleware('throttle:20,1');

    // Alert delivery tracking
    Route::post('/alert-deliveries/{alertDelivery}/opened', [AlertDeliveryController::class, 'opened'])
        ->middleware('throttle:30,1');

    // Incidents (read)
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/incidents', [IncidentController::class, 'index']);
        Route::get('/incidents.geojson', [IncidentController::class, 'geojson']);
        Route::get('/incidents/{incident}', [IncidentController::class, 'show']);
    });

    // Incidents (write)
    Route::post('/incidents/{incident}/confirm', [IncidentController::class, 'confirm'])
        ->middleware('throttle:30,1');
    Route::post('/incidents/{incident}/resolve', [IncidentController::class, 'resolve'])
        ->middleware('throttle:15,1');

    // Reports (new incidents)
    Route::post('/reports', [ReportController::class, 'store'])
        ->middleware('throttle:6,1');

    // Admin routes
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);

        Route::get('/incidents', [AdminController::class, 'incidents']);
        Route::put('/incidents/{incident}', [AdminController::class, 'updateIncident']);
        Route::delete('/incidents/{incident}', [AdminController::class, 'deleteIncident']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);

        Route::get('/installations', [AdminController::class, 'installations']);
        Route::post('/installations/{installation}/block', [AdminController::class, 'blockInstallation']);
        Route::post('/installations/{installation}/unblock', [AdminController::class, 'unblockInstallation']);

        Route::get('/reports/pending', [AdminController::class, 'pendingReports']);
        Route::post('/reports/{report}/approve', [AdminController::class, 'approveReport']);
        Route::post('/reports/{report}/reject', [AdminController::class, 'rejectReport']);
    });
});
