<?php

namespace App\Providers;

use App\Services\AI\Gemini\GeminiKeyPool;
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
        // Built per resolve (not a singleton) so config changes in tests apply.
        $this->app->bind(GeminiKeyPool::class, fn () => GeminiKeyPool::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

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
