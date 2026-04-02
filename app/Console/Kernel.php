<?php namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        'App\Console\Commands\Inspire',                // было
        'App\Console\Commands\GenerateWeeklyReports',  // новая
        'App\Console\Commands\GenerateGroupStats',     // новая
    ];

    protected function schedule(Schedule $schedule)
    {
        // как было — каждый час
        $schedule->command('inspire')->hourly();

        // раз в неделю по понедельникам
        if (method_exists($schedule, 'weeklyOn')) {
            $schedule->command('reports:weekly')->weeklyOn(1, '02:00');
            $schedule->command('groupstats:weekly')->weeklyOn(1, '02:10');
        } else {
            // если очень старый планировщик — cron-правила
            $schedule->command('reports:weekly')->cron('0 2 * * 1');
            $schedule->command('groupstats:weekly')->cron('10 2 * * 1');
        }
    }
}
