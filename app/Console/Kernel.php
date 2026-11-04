<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // تذكير قبل بداية الدوام بـ10 دقائق وعند نهايته (يحتاج cron: * * * * * php artisan schedule:run)
        $schedule->command('attendance:shift-reminders')->everyMinute()->withoutOverlapping(5);

        // طابور النشر: بدون عامل دائم على الاستضافة — الـ cron بيفضّيه كل دقيقة
        $schedule->command('queue:work database --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);

        $schedule->command('publishing:license-alerts')->dailyAt('09:00')->timezone(config('app.business_timezone'));
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
