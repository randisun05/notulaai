<?php

namespace App\Console;

use App\Jobs\EscalateOverdueTasks;
use App\Jobs\SendMeetingReminders;
use App\Jobs\SendTaskDeadlineReminders;
use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $timezone = Setting::current()->timezone ?? 'Asia/Jakarta';

        $schedule->job(new SendMeetingReminders)
            ->dailyAt('07:00')
            ->timezone($timezone);

        $schedule->job(new SendTaskDeadlineReminders)
            ->dailyAt('07:30')
            ->timezone($timezone);

        $schedule->job(new EscalateOverdueTasks)
            ->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
