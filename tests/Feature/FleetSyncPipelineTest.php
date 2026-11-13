<?php

namespace Tests\Feature;

use App\Models\AdvancedItinerary;
use App\Models\FleetDispatch;
use App\Models\MileageReconciliation;
use App\Models\ReconciliationException;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleDeviceLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FleetSyncPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_keeps_reconciliation_pending_without_verified_odometer_readings(): void
    {
        $operator = User::factory()->operator()->create();
        $vehicle = Vehicle::factory()->create(['created_by' => $operator->id]);
        AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'created_by' => $operator->id,
            'itinerary_date' => now()->toDateString(),
            'total_distance' => 120,
            'status' => AdvancedItinerary::STATUS_DRAFT,
            'start_odo' => 25000,
            'end_odo' => null,
        ]);

        $this->mockDeviceLinkSync($operator);

        $this->artisan('fleet:sync-pipeline', ['--user-id' => $operator->id])
            ->assertSuccessful();

        $dispatch = FleetDispatch::query()->sole();
        $reconciliation = $dispatch->reconciliation;

        $this->assertSame(120.0, $dispatch->planned_distance_km);
        $this->assertNotNull($reconciliation);
        $this->assertNull($reconciliation->starting_odometer);
        $this->assertNull($reconciliation->ending_odometer);
        $this->assertNull($reconciliation->actual_odometer_km);
        $this->assertNull($reconciliation->variance_km);
        $this->assertSame(MileageReconciliation::STATUS_DRAFT, $reconciliation->status);
        $this->assertDatabaseHas('reconciliation_exceptions', [
            'mileage_reconciliation_id' => $reconciliation->id,
            'exception_type' => ReconciliationException::TYPE_MISSING_ODOMETER,
        ]);
    }

    public function test_pipeline_accepts_valid_odometer_readings_from_a_finalized_itinerary(): void
    {
        $operator = User::factory()->operator()->create();
        $vehicle = Vehicle::factory()->create(['created_by' => $operator->id]);
        AdvancedItinerary::factory()->finalized()->create([
            'vehicle_id' => $vehicle->id,
            'created_by' => $operator->id,
            'itinerary_date' => now()->toDateString(),
            'total_distance' => 80,
            'start_odo' => 12500,
            'end_odo' => 12580,
        ]);

        $this->mockDeviceLinkSync($operator);

        $this->artisan('fleet:sync-pipeline', ['--user-id' => $operator->id])
            ->assertSuccessful();

        $reconciliation = FleetDispatch::query()->sole()->reconciliation;

        $this->assertSame(12500.0, $reconciliation->starting_odometer);
        $this->assertSame(12580.0, $reconciliation->ending_odometer);
        $this->assertSame(80.0, $reconciliation->actual_odometer_km);
        $this->assertSame(0.0, $reconciliation->variance_km);
    }

    public function test_pipeline_does_not_invent_a_planned_distance_when_itinerary_distance_is_missing(): void
    {
        $operator = User::factory()->operator()->create();
        $vehicle = Vehicle::factory()->create(['created_by' => $operator->id]);
        AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'created_by' => $operator->id,
            'itinerary_date' => now()->toDateString(),
            'total_distance' => null,
        ]);

        $this->mockDeviceLinkSync($operator);

        $this->artisan('fleet:sync-pipeline', ['--user-id' => $operator->id])
            ->assertSuccessful();

        $dispatch = FleetDispatch::query()->sole();
        $reconciliation = $dispatch->reconciliation;

        $this->assertSame(0.0, $dispatch->planned_distance_km);
        $this->assertStringContainsString('pending a verified route measurement', $reconciliation->notes);
        $this->assertNull($reconciliation->planned_distance_km);
        $this->assertNull($reconciliation->actual_odometer_km);
        $this->assertNull($reconciliation->variance_km);
    }

    private function mockDeviceLinkSync(User $operator): void
    {
        $service = Mockery::mock(VehicleDeviceLinkService::class);
        $service->shouldReceive('syncAll')
            ->once()
            ->with($operator->id)
            ->andReturn([
                'linked' => 0,
                'updated' => 0,
                'total_vehicles' => 1,
                'total_devices' => 0,
            ]);

        $this->app->instance(VehicleDeviceLinkService::class, $service);
    }
}
