<?php

namespace App\Providers;

use App\Services\VerifierAttestationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
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
        RateLimiter::for('ctf-attestations', function (Request $request): Limit {
            return Limit::perMinute(20)
                ->by((string) $request->ip())
                ->response(function (Request $request): JsonResponse {
                    app(VerifierAttestationService::class)->recordRateLimited($request);

                    return response()->json(['accepted' => false, 'reason' => 'rate_limited'], 429);
                });
        });
    }
}
