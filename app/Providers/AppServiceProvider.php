<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Request-fresh носитель текущего shard (наполняется middleware ResolveCoupleContext).
        $this->app->singleton(\App\Support\CoupleContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Стабильные type-строки для полиморфных связей (outbox notifiable, Sanctum tokenable).
        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
            'user' => \App\Models\User::class,
            'waitlist' => \App\Models\WaitlistEntry::class,
        ]);

        // Listener CreateGamificationProfile авто-регистрируется Laravel (discovery в app/Listeners),
        // поэтому вручную через Event::listen НЕ регистрируем — иначе двойной вызов.
    }
}
