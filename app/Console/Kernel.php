<?php

namespace App\Console;

use App\Jobs\SendMeetingReminders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
          // TAMBAHKAN JADWAL INI
            // $schedule->job(new SendMeetingReminders)
            //         ->dailyAt('07:00') // Jalankan setiap hari jam 7 pagi
            //         ->timezone('Asia/Jakarta');

            $schedule->job(new \App\Jobs\SendMeetingReminders)->everyMinute();
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
