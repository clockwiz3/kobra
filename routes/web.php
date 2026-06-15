<?php

use Illuminate\Support\Facades\Route;

/*
 * SPA host: все не-API маршруты отдают Vue-приложение (resources/views/app.blade.php).
 * Исключения: /api/* (routes/api.php) и /up (health-check) обрабатываются отдельно.
 */
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|up).*$')
    ->name('spa');
