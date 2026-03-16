<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\SigningController;
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
Route::apiResource('contracts', ContractController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
Route::get('contracts/{contract}/pdf', [ContractController::class, 'pdf'])->name('contracts.pdf');
Route::get('contracts/{contract}/pdf/inline', [ContractController::class, 'pdfInline'])->name('contracts.pdf.inline');
Route::get('signing/{token}/context', [SigningController::class, 'context']);
Route::post('signing/{token}/signer-data', [SigningController::class, 'signerData']);
Route::post('signing/{token}/sign', [SigningController::class, 'sign']);
Route::post('signing/{token}/finalize', [SigningController::class, 'finalize']);
Route::post('webhooks/autentique', [WebhookController::class, 'autentique']);
