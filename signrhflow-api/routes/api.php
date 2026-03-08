<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\RequireApiTokenAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware(RequireApiTokenAuth::class)->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::apiResource('employees', EmployeeController::class)->only(['index', 'store', 'show']);
Route::apiResource('contracts', ContractController::class)->only(['index', 'store', 'show']);
Route::get('contracts/{contract}/pdf', [ContractController::class, 'pdf'])->name('contracts.pdf');
Route::post('webhooks/autentique', [WebhookController::class, 'autentique']);
