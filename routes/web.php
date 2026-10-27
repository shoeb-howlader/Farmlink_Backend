<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storage/{path}', function (string $path) {
    if (str_contains($path, '..')) {
        abort(400, 'Invalid path');
    }

    $cleanPath = ltrim($path, '/');
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    if (! $disk->exists($cleanPath)) {
        abort(404);
    }

    $response = $disk->response($cleanPath);
    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
    $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' https://*.farmlinkcare.com https://farmlinkcare.com http://localhost:*");

    return $response;
})->where('path', '.*');
