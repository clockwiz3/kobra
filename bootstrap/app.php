<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Канал-роуты + /broadcasting/auth под токен-гвардом Sanctum (SPA шлёт Bearer,
    // не сессию) — presence-канал couple.{id} авторизуется по членству в паре.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        attributes: ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // За Caddy (прод на Tailscale) доверяем X-Forwarded-* — иначе Laravel
        // считает запрос http и ломает secure-куки/редиректы. В dev заголовков нет → no-op.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'couple.context' => \App\Http\Middleware\ResolveCoupleContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
