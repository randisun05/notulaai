<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\EscalateOverdueTasks;
use App\Jobs\SendMeetingReminders;
use App\Jobs\SendTaskDeadlineReminders;
use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Inertia page-props + <link rel=preload> headers ride on top of the
        // default web group (was appended in the old app/Http/Kernel.php).
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Keeps the old api group's `throttle:api` (limiter defined in AppServiceProvider).
        $middleware->throttleApi();

        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);

        // Friendly, localized message when an AI (or any throttled) endpoint is
        // rate-limited, for both the axios calls and the Inertia POSTs.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;
            $message = 'Terlalu banyak permintaan dalam waktu singkat.'
                .($retryAfter ? " Coba lagi dalam {$retryAfter} detik." : ' Coba lagi sebentar lagi.');

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 429, $e->getHeaders());
            }

            if ($request->header('X-Inertia')) {
                return back()->with('error', $message);
            }

            return null;
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        // withSchedule() runs on every `artisan` invocation (Artisan::starting),
        // so this must not hard-fail when the DB is unreachable (CI, composer
        // install, a fresh container before migrate).
        $timezone = rescue(fn () => Setting::current()->timezone, 'Asia/Jakarta', report: false) ?: 'Asia/Jakarta';

        $schedule->job(new SendMeetingReminders)
            ->dailyAt('07:00')
            ->timezone($timezone);

        $schedule->job(new SendTaskDeadlineReminders)
            ->dailyAt('07:30')
            ->timezone($timezone);

        $schedule->job(new EscalateOverdueTasks)
            ->hourly();

        $schedule->command('meetings:fail-stuck', ['--minutes=10'])
            ->everyFiveMinutes()
            ->withoutOverlapping();
    })
    ->create();
