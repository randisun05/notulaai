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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Public QR check-in page (no login): a meeting room's worth of phones share one
        // Wi-Fi IP, so this is generous per IP but still stops scripted flooding.
        RateLimiter::for('attendance', fn (Request $request) => Limit::perMinute(120)->by('attendance:'.$request->ip()));

        // Shared budget for every endpoint that calls an LLM (see config/ai.php).
        RateLimiter::for('ai', function (Request $request) {
            $config = config('ai.rate_limits');
            $user = $request->user();
            $key = $user?->id ?: $request->ip();

            $limits = [
                Limit::perMinute($config['per_minute'])->by("ai:min:{$key}"),
                Limit::perDay($config['per_day'])->by("ai:day:{$key}"),
            ];

            if ($user?->unit_id) {
                $limits[] = Limit::perDay($config['per_unit_per_day'])->by("ai:unit:{$user->unit_id}");
            }

            return $limits;
        });
    }
}
