<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TelemetryController;
Route::get('/api/csrf', fn () => response()->json(['csrf_token'=>csrf_token()]));
Route::post('/api/login', [AuthController::class,'login'])->middleware('throttle:login');
Route::middleware(['auth','throttle:api'])->prefix('api')->group(function () {
    Route::get('/session',[AuthController::class,'session']);
    Route::post('/logout',[AuthController::class,'logout']);
    Route::get('/device',[TelemetryController::class,'device']);
    Route::get('/telemetry/latest',[TelemetryController::class,'latest']);
    Route::get('/telemetry/history',[TelemetryController::class,'history']);
    Route::get('/telemetry/chart',[TelemetryController::class,'chart']);
});
