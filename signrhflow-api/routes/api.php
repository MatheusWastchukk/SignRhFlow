<?php

use App\Http\Controllers\ContractController;
use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('employees', EmployeeController::class)->only(['index', 'store', 'show']);
Route::apiResource('contracts', ContractController::class)->only(['index', 'store', 'show']);
