<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

Route::withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::post('/api/validate', [ApiController::class, 'validate'])->name('api.validate');
    Route::get('/api/normal', [ApiController::class, 'normal'])->name('api.normal');
    Route::get('/api/slow', [ApiController::class, 'slow'])->name('api.slow');
    Route::get('/api/error', [ApiController::class, 'error'])->name('api.error');
    Route::get('/api/random', [ApiController::class, 'random'])->name('api.random');
    Route::get('/api/db', [ApiController::class, 'db'])->name('api.db');
});

Route::get('/', function () {
    return view('welcome');
});
Route::get('/metrics', [App\Http\Controllers\MetricsController::class, 'index']);
Route::get('/test', function() {
    return 'Hello World';
});