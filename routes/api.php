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

// Dynamic Sitemap URLs for Nuxt SEO
Route::get('/sitemap-urls', [App\Http\Controllers\Api\V1\SitemapController::class, 'index'])->name('sitemap.urls');

// Public Contact Form (throttle:5,1 rate limiting)
Route::post('/contact', [\App\Http\Controllers\Api\V1\ContactController::class, 'send'])
    ->middleware('throttle:5,1')
    ->name('contact.send');

