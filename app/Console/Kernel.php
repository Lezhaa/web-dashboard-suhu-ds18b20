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
        // PRODUCTION: Jalankan di jam-jam tertentu
        $schedule->command('suhu:fetch-auto')
            ->dailyAt('08:00')
            ->timezone('Asia/Jakarta')
            ->before(function () {
                \Log::info('🔔 Scheduler triggered at 08:00');
            })
            ->onSuccess(function () {
                \Log::info('✅ 08:00 fetch completed');
            })
            ->onFailure(function () {
                \Log::error('❌ 08:00 fetch failed');
            });
        
        $schedule->command('suhu:fetch-auto')
            ->dailyAt('12:00')
            ->timezone('Asia/Jakarta')
            ->before(function () {
                \Log::info('🔔 Scheduler triggered at 12:00');
            })
            ->onSuccess(function () {
                \Log::info('✅ 12:00 fetch completed');
            })
            ->onFailure(function () {
                \Log::error('❌ 12:00 fetch failed');
            });
        
        $schedule->command('suhu:fetch-auto')
            ->dailyAt('20:00')
            ->timezone('Asia/Jakarta')
            ->before(function () {
                \Log::info('🔔 Scheduler triggered at 20:00');
            })
            ->onSuccess(function () {
                \Log::info('✅ 20:00 fetch completed');
            })
            ->onFailure(function () {
                \Log::error('❌ 20:00 fetch failed');
            });
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}