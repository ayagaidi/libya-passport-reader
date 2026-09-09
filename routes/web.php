<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'Libya Passport Reader',
    'status' => 'development',
    'api' => '/api/v1/meta',
    'docs' => '/docs',
    'openapi' => '/openapi.yaml',
]));

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'libya-passport-reader',
]));

Route::get('/docs', fn () => redirect('/docs/'));

Route::get('/openapi.yaml', fn () => response()->file(
    base_path('openapi.yaml'),
    ['Content-Type' => 'application/yaml; charset=UTF-8']
));
