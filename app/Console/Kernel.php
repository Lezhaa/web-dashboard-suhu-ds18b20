<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\FetchThingSpeakScheduled::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // PRODUCTION - jam sebenarnya
        $schedule->command('suhu:fetch-auto')->dailyAt('08:00')->timezone('Asia/Jakarta');
        $schedule->command('suhu:fetch-auto')->dailyAt('12:00')->timezone('Asia/Jakarta');
        $schedule->command('suhu:fetch-auto')->dailyAt('20:00')->timezone('Asia/Jakarta');

        // DEVELOPMENT - jam testing (beda dari production)
        if (app()->environment('local')) {
            $schedule->command('suhu:fetch-auto')->everyMinute();
        }
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}