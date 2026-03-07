<?php

use App\Http\Controllers\ContractController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::apiResource('employees', EmployeeController::class)->only(['index', 'store', 'show']);
Route::apiResource('contracts', ContractController::class)->only(['index', 'store', 'show']);
Route::get('contracts/{contract}/pdf', [ContractController::class, 'pdf'])->name('contracts.pdf');
Route::post('webhooks/autentique', [WebhookController::class, 'autentique']);
