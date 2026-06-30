<?php

use App\Http\Controllers\Api\AttentionLocationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\GeographyController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NnaRegistrationController;
use App\Http\Controllers\Api\OperativoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::apiResource('operativos', OperativoController::class);

        Route::get('/catalogs/types', [CatalogController::class, 'types']);
        Route::get('/catalogs/bundle', [CatalogController::class, 'bundle']);
        Route::get('/catalogs/{type}', [CatalogController::class, 'index']);
        Route::post('/catalogs/{type}', [CatalogController::class, 'store']);
        Route::put('/catalogs/{type}/{catalog}', [CatalogController::class, 'update']);
        Route::delete('/catalogs/{type}/{catalog}', [CatalogController::class, 'destroy']);

        Route::get('/geography/bundle', [GeographyController::class, 'bundle']);
        Route::get('/geography/estados', [GeographyController::class, 'estados']);
        Route::get('/geography/estados/{estado}/municipios', [GeographyController::class, 'municipios']);
        Route::get('/geography/municipios/{municipio}/parroquias', [GeographyController::class, 'parroquias']);

        Route::apiResource('attention-locations', AttentionLocationController::class)->except(['show']);

        Route::get('/nna', [NnaRegistrationController::class, 'index']);
        Route::post('/nna', [NnaRegistrationController::class, 'store']);
        Route::post('/nna/sync/batch', [NnaRegistrationController::class, 'syncBatch']);
        Route::get('/nna/{nnaRegistration}', [NnaRegistrationController::class, 'show']);
        Route::put('/nna/{nnaRegistration}', [NnaRegistrationController::class, 'update']);
        Route::delete('/nna/{nnaRegistration}', [NnaRegistrationController::class, 'destroy']);
        Route::post('/nna/{nnaRegistration}/photos', [NnaRegistrationController::class, 'uploadPhoto']);
    });
});
