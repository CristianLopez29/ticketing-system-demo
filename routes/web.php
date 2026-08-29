<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

// This is a JSON API with no frontend: the root previously rendered a "welcome" Blade view
// that does not exist in this repo, so every hit on / returned a 500.
Route::get('/', function (): JsonResponse {
    return new JsonResponse([
        'name' => config('app.name'),
        'status' => 'ok',
        'documentation' => url('/api/documentation'),
        'health' => url('/api/health'),
        'readiness' => url('/api/readiness'),
    ]);
});

// Laravel's auth redirect resolves a route named "login"; without it an unauthenticated
// non-JSON request throws instead of answering.
Route::get('/login', function (): JsonResponse {
    return new JsonResponse(['message' => 'Use POST /api/login'], 405);
})->name('login');
