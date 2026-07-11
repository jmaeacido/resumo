<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $rootUrl = rtrim((string) config('app.url'), '/');

        if ($rootUrl !== '' && ! app()->environment('testing')) {
            URL::forceRootUrl($rootUrl);
        }

        Vite::prefetch(concurrency: 3);

        RateLimiter::for('analyze', function (Request $request) {
            return Limit::perMinute(8)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many analysis requests. Please wait a minute and try again.',
                    ], 429);
                });
        });

        RateLimiter::for('butler', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Butler is receiving too many messages. Please slow down.',
                    ], 429);
                });
        });
    }
}
