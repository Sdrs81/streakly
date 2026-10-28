<?php

namespace App\Providers;

use App\Support\EmailNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private const AUTH_ATTEMPTS_PER_MINUTE = 10;

    private const LOGIN_ATTEMPTS_PER_IP_PER_MINUTE = 30;

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
        // The per-IP cap stops one client from spraying a password across many emails.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(self::AUTH_ATTEMPTS_PER_MINUTE)->by($this->loginThrottleKey($request)),
            Limit::perMinute(self::LOGIN_ATTEMPTS_PER_IP_PER_MINUTE)->by($request->ip()),
        ]);
    }

    // Runs before validation, so the email can still be a non-string payload.
    private function loginThrottleKey(Request $request): string
    {
        $email = $request->input('email');

        return $request->ip().'|'.(is_string($email) ? EmailNormalizer::normalize($email) : '');
    }
}
