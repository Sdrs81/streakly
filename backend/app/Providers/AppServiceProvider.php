<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private const AUTH_ATTEMPTS_PER_MINUTE = 10;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Separate buckets so failed logins never lock out registration (and vice versa).
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(self::AUTH_ATTEMPTS_PER_MINUTE)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(self::AUTH_ATTEMPTS_PER_MINUTE)
            ->by($request->ip().'|'.mb_strtolower((string) $request->input('email'))));
    }
}
