<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storage/{path}', function (string $path) {
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    if (! $disk->exists($path)) {
        abort(404);
    }

    $response = $disk->response($path);
    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
    $response->headers->remove('X-Frame-Options');
    $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' https://*.farmlinkcare.com https://farmlinkcare.com http://localhost:*");

    return $response;
})->where('path', '.*');
