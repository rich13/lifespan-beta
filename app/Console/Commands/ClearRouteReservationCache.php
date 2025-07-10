<?php

namespace App\Console\Commands;

use App\Services\RouteReservationService;
use Illuminate\Console\Command;

class ClearRouteReservationCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route-reservation:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the route reservation cache';

    /**
     * Execute the console command.
     */
    public function handle(RouteReservationService $service): int
    {
        $service->clearCache();
        
        $this->info('Route reservation cache cleared successfully.');
        
        // Show current reserved names for verification
        $reservedNames = $service->getReservedNamesForDisplay();
        $this->info('Current reserved route names:');
        $this->table(['Reserved Names'], array_map(fn($name) => [$name], $reservedNames));
        
        return Command::SUCCESS;
    }
} 