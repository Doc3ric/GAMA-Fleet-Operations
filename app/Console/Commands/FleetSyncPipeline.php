<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdvancedItinerary;
use App\Models\Device;
use App\Models\Vehicle;
use App\Services\DispatchMonitoringService;
use App\Services\MileageReconciliationService;
use App\Services\VehicleDeviceLinkService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FleetSyncPipeline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fleet:sync-pipeline 
                            {--limit=25 : Maximum number of itineraries to convert into dispatches} 
                            {--user-id=1 : User ID to attribute generated dispatches to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize GPS device links, bridge unassigned itineraries into fleet dispatches, and initialize mileage reconciliations.';

    public function __construct(
        protected VehicleDeviceLinkService $linkService,
        protected DispatchMonitoringService $dispatchService,
        protected MileageReconciliationService $reconciliationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('===========================================================');
        $this->info('   GAMA Fleet Operations Management — Workflow Pipeline Sync   ');
        $this->info('===========================================================');

        $userId = (int) $this->option('user-id');
        $limit = (int) $this->option('limit');

        // Step 1: Link Vehicles & Devices
        $this->newLine();
        $this->line('<fg=cyan;options=bold>Step 1: Synchronizing Vehicles and GPS Devices...</>');
        $syncResult = $this->linkService->syncAll($userId);
        $this->line("  ✓ Newly linked: <fg=green>{$syncResult['linked']}</>");
        $this->line("  ✓ GPS statuses updated: <fg=green>{$syncResult['updated']}</>");
        $this->line("  ✓ Total Vehicles: <fg=yellow>{$syncResult['total_vehicles']}</> | Total Devices: <fg=yellow>{$syncResult['total_devices']}</>");

        // Step 2: Bridge Itineraries to Dispatches and Reconciliations
        $this->newLine();
        $this->line('<fg=cyan;options=bold>Step 2: Bridging Itineraries into Fleet Dispatches...</>');
        $itineraries = AdvancedItinerary::with(['vehicle', 'legs'])
            ->whereDoesntHave('dispatches')
            ->latest('itinerary_date')
            ->limit($limit)
            ->get();

        $dispatchedCount = 0;
        foreach ($itineraries as $itinerary) {
            $vehicle = $itinerary->vehicle;
            if (! $vehicle) {
                continue;
            }

            $date = $itinerary->itinerary_date ?: Carbon::today();
            $plannedDep = $date->copy()->setHour(8)->setMinute(0);

            $firstLeg = $itinerary->legs->first();
            $lastLeg = $itinerary->legs->last();

            $startingPoint = $firstLeg?->origin ?? 'Cagayan de Oro Main Yard';
            $destination = $lastLeg?->destination ?? ($firstLeg?->destination ?? ($itinerary->destination ?: 'Regional Depot'));
            $plannedDistance = max(0.0, (float) $itinerary->total_distance);
            $verifiedPlannedDistance = $plannedDistance > 0 ? $plannedDistance : null;

            $dispatch = $this->dispatchService->createDispatch([
                'vehicle_id' => $vehicle->id,
                'advanced_itinerary_id' => $itinerary->id,
                'trip_date' => $date->format('Y-m-d'),
                'driver_name' => $itinerary->driver_name ?: 'Assigned Fleet Driver',
                'starting_point' => $startingPoint,
                'destination' => $destination,
                'planned_route' => $itinerary->title ?: ($itinerary->destination ?: 'Approved Distribution Route'),
                'planned_distance_km' => $plannedDistance,
                'planned_departure_time' => $plannedDep,
                'grace_period_minutes' => 15,
                'notes' => "Auto-generated from Itinerary #{$itinerary->id}",
            ], $userId);

            $verifiedOdometerReadings = $itinerary->verifiedOdometerReadings();
            $this->reconciliationService->createReconciliation($dispatch, [
                'planned_distance_km' => $verifiedPlannedDistance,
                'starting_odometer' => $verifiedOdometerReadings['starting_odometer'] ?? null,
                'ending_odometer' => $verifiedOdometerReadings['ending_odometer'] ?? null,
                'notes' => $verifiedPlannedDistance !== null
                    ? 'Generated from operational itinerary.'
                    : 'Generated from operational itinerary. Planned distance is pending a verified route measurement.',
            ], $userId);

            $dispatchedCount++;
        }

        $this->line("  ✓ Generated <fg=green>{$dispatchedCount}</> active dispatches and initialized reconciliations.");

        // Step 3: Pipeline Status Overview
        $this->newLine();
        $this->line('<fg=cyan;options=bold>Step 3: Pipeline Status Overview</>');

        $vehiclesWithDevice = Vehicle::whereHas('device')->count();
        $totalVehicles = Vehicle::count();
        $devicesWithImei = Device::whereNotNull('imei')->where('imei', '!=', '')->count();
        $totalDevices = Device::count();

        $dispatchStats = $this->dispatchService->getStatistics();
        $reconciliationStats = $this->reconciliationService->getStatistics();

        $itinerariesCount = AdvancedItinerary::count();
        $finalizedItineraries = AdvancedItinerary::where('status', AdvancedItinerary::STATUS_FINALIZED)->count();
        $fuelPoChecked = AdvancedItinerary::where('po_checked', true)->count();

        $this->table(
            ['Pipeline Module', 'Current Status / Metrics', 'Operational Readiness'],
            [
                ['Vehicle Master', "{$vehiclesWithDevice} / {$totalVehicles} linked to GPS trackers", $vehiclesWithDevice > 0 ? 'READY' : 'NEEDS SETUP'],
                ['GPS Device Registry', "{$devicesWithImei} / {$totalDevices} valid IMEIs registered", $devicesWithImei > 0 ? 'READY' : 'NEEDS SETUP'],
                ['Advanced Itineraries', "{$finalizedItineraries} finalized / {$itinerariesCount} total itineraries", $finalizedItineraries > 0 ? 'ACTIVE' : 'DRAFT'],
                ['Fuel PO Checklist', "{$fuelPoChecked} issued POs / {$itinerariesCount} planned trips", 'ACTIVE'],
                ['Fleet Dispatch Monitoring', "{$dispatchStats['total_trips']} trips ({$dispatchStats['on_time_rate']}% On-Time Dispatch Rate)", 'ACTIVE'],
                ['Mileage Reconciliation', "{$reconciliationStats['total_requiring_reconciliation']} reconciliations ({$reconciliationStats['pending_reviews']} pending review)", 'ACTIVE'],
                ['Live GPS Map & Replay', 'Available at /gps-map and /trip-history', 'OPERATIONAL'],
            ]
        );

        $this->info('===========================================================');
        $this->info('   Workflow Pipeline is fully connected and operational!    ');
        $this->info('===========================================================');

        return Command::SUCCESS;
    }
}
