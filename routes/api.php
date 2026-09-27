<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider / Application builder and all
| of them will be assigned to the "api" middleware group.
|
*/

// Version 1 API Routes (/api/v1/...)
Route::prefix('v1')
    ->as('v1.')
    ->group(base_path('routes/api/v1.php'));
