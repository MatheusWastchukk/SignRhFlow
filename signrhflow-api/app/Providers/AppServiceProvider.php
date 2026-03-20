<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        RateLimiter::for('api', function (Request $request): Limit {
            $perMinute = max(1, (int) config('signrhflow.api_rate_limit_per_minute', 120));

            return Limit::perMinute($perMinute)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request): Limit {
            $perMinute = max(1, (int) config('signrhflow.webhook_rate_limit_per_minute', 300));

            return Limit::perMinute($perMinute)->by($request->ip());
        });
    }
}
