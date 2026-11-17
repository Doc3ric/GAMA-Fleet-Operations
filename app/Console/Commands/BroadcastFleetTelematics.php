<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FleetTelematicsBroadcaster;
use Illuminate\Console\Command;

class BroadcastFleetTelematics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fleet:broadcast-telematics
                            {--interval=15 : Broadcast loop interval in seconds}
                            {--loop : Run continuously in a daemon loop}
                            {--force : Force fresh TrackSolid API pull bypassing cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch live TrackSolid GPS positions and broadcast telematics to connected browsers via Laravel Reverb WebSockets.';

    public function __construct(
        protected FleetTelematicsBroadcaster $broadcaster
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $interval = max(5, (int) $this->option('interval'));
        $loop = (bool) $this->option('loop');
        $force = (bool) $this->option('force');

        $this->info('===========================================================');
        $this->info('   GAMA Fleet Telematics — Live WebSocket Broadcaster     ');
        $this->info('===========================================================');
        $this->line('Broadcasting via: <fg=cyan>Laravel Reverb</> on channel <fg=cyan>fleet-telematics</>');

        do {
            $this->line("\n[".now()->format('Y-m-d H:i:s').'] Fetching GPS positions and broadcasting...');

            $result = $this->broadcaster->broadcast(force: $force);
            $summary = $result['summary'];

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Monitored', $summary['total']],
                    ['Online / Connected', $summary['online']],
                    ['Moving (>= 3 km/h)', $summary['moving']],
                    ['Idling (Ignition ON)', $summary['idling']],
                    ['Parked (Ignition OFF)', $summary['parked']],
                    ['Offline / Stale', $summary['offline'] + $summary['stale']],
                    ['Unmapped Devices', $summary['unmapped']],
                    ['Active Issues / Expired', $summary['issues']],
                ]
            );

            if ($result['broadcasted']) {
                $this->info("✓ Successfully broadcasted telematics for {$summary['total']} vehicles at {$result['timestamp']}.");
            } else {
                $this->warn('! Telematics compiled but broadcast event dispatch encountered a warning.');
            }

            if ($loop) {
                $this->line("Sleeping for {$interval}s before next broadcast... (Press Ctrl+C to stop)");
                sleep($interval);
                $force = true; // In loop mode, force refresh each cycle
            }
        } while ($loop);

        return self::SUCCESS;
    }
}
