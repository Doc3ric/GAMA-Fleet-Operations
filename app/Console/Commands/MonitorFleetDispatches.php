<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DispatchGpsTrackingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MonitorFleetDispatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fleet:monitor-dispatches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automated fleet dispatch monitoring: compare live TrackSolid GPS positions against assigned itineraries, detect route deviations, missed departures, unexpected stops, and generate in-app alerts.';

    public function __construct(protected DispatchGpsTrackingService $trackingService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Automated Fleet Dispatch Monitoring sweep...');
        $now = Carbon::now();

        $stats = $this->trackingService->monitorActiveDispatches($now);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Active Dispatches Scanned', $stats['scanned']],
                ['Normal (On Schedule / In Corridor)', $stats['normal']],
                ['Possible Exceptions', $stats['possible_exceptions']],
                ['Confirmed Exceptions', $stats['confirmed_exceptions']],
                ['GPS Monitoring Unavailable', $stats['gps_unavailable']],
                ['New Alert Events Created', $stats['events_created']],
            ]
        );

        $this->info("Completed monitoring sweep at {$now->format('Y-m-d H:i:s')}.");

        return self::SUCCESS;
    }
}
