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
        // $schedule->command('inspire')->hourly();
        
        // Warm Desert Island Discs cache daily at 2 AM
        $schedule->command('cache:warm-desert-island-discs')
            ->daily()
            ->at('02:00')
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');

        $this->commands = [
            Commands\ImportYaml::class,
            Commands\DatabaseCreateCommand::class,
            Commands\WarmDesertIslandDiscsCache::class,
        ];
    }
}
