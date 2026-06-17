<?php

namespace App\Providers;

use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\CoupleContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Request-fresh носитель текущего shard (наполняется middleware ResolveCoupleContext).
        $this->app->singleton(CoupleContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Стабильные type-строки для полиморфных связей (outbox notifiable, Sanctum tokenable).
        Relation::enforceMorphMap([
            'user' => User::class,
            'waitlist' => WaitlistEntry::class,
        ]);

        // Listener CreateGamificationProfile авто-регистрируется Laravel (discovery в app/Listeners),
        // поэтому вручную через Event::listen НЕ регистрируем — иначе двойной вызов.

        // Очереди: сбрасываем CoupleContext перед каждой job (изоляция на переиспользуемом воркере).
        Queue::before(function (): void {
            app(CoupleContext::class)->clear();
        });

        // Rate limit логина: 5/мин по (email + ip). При превышении — WARN-лог и 429 (security NFR).
        RateLimiter::for('login', function (Request $request) {
            $key = mb_strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key)->response(function () use ($request) {
                Log::warning('[RateLimiter.login] throttled', ['ip' => $request->ip()]);

                return response()->json(['message' => 'Слишком много попыток. Попробуйте позже.'], 429);
            });
        });
    }
}
