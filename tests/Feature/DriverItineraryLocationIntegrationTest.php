<?php

namespace Tests\Feature;

use App\Models\DriverTrip;
use App\Models\Location;
use App\Models\LocationAlias;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\LocationRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DriverItineraryLocationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $driver;

    protected Vehicle $vehicle;

    protected Location $farm2;

    protected Location $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Admin Specialist', 'email' => 'admin@gama.com']);
        $this->driver = User::factory()->driver()->create(['name' => 'Juan Driver', 'email' => 'driver@gama.com']);

        $vehicleType = VehicleType::create(['code' => 'DT', 'name' => 'DUMP TRUCK']);
        $this->vehicle = Vehicle::create([
            'equipment_code' => 'DT-01',
            'vehicle_type_id' => $vehicleType->id,
            'model' => 'ISUZU GIGA',
            'plate_number' => 'NBD-1234',
            'created_by' => $this->admin->id,
        ]);

        // Official Location: GAMA FARM 2
        $this->farm2 = Location::create([
            'code' => 'G2',
            'official_name' => 'GAMA FARM 2',
            'type' => Location::TYPE_FARM,
            'latitude' => 14.8450000,
            'longitude' => 120.8120000,
            'address' => 'Farm 2 Road, Malolos, Bulacan',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        foreach (['G2', 'GAMA 2', 'FARM 2', 'GAMA FARM2', 'G2 FARM'] as $alias) {
            LocationAlias::create([
                'location_id' => $this->farm2->id,
                'alias' => $alias,
            ]);
        }

        // Official Location: CENTRAL WAREHOUSE
        $this->warehouse = Location::create([
            'code' => 'CWH',
            'official_name' => 'CENTRAL WAREHOUSE',
            'type' => Location::TYPE_WAREHOUSE,
            'latitude' => 14.6500000,
            'longitude' => 121.0500000,
            'address' => 'Central Depot, Quezon City',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        LocationAlias::create([
            'location_id' => $this->warehouse->id,
            'alias' => 'CWH',
        ]);
    }

    public function test_itinerary_index_displays_recognition_statuses_and_preserves_raw_entry(): void
    {
        // Trip 1: Exact match "G2"
        $trip1 = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => '2026-09-16',
            'time_in' => '08:00:00',
            'origin_latitude' => 14.50,
            'origin_longitude' => 121.00,
            'destination_address' => 'G2',
            'status' => DriverTrip::STATUS_COMPLETED,
        ]);

        // Trip 2: Fuzzy candidate "GAMA FRM 2"
        $trip2 = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => '2026-09-16',
            'time_in' => '09:00:00',
            'origin_latitude' => 14.50,
            'origin_longitude' => 121.00,
            'destination_address' => 'GAMA FRM 2',
            'status' => DriverTrip::STATUS_COMPLETED,
        ]);

        // Trip 3: Unknown "BODEGA"
        $trip3 = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => '2026-09-16',
            'time_in' => '10:00:00',
            'origin_latitude' => 14.50,
            'origin_longitude' => 121.00,
            'destination_address' => 'BODEGA',
            'status' => DriverTrip::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($this->admin)->get(route('itineraries.index'));
        $response->assertOk();

        // 1. Raw driver entries are preserved and visible
        $response->assertSee('G2');
        $response->assertSee('GAMA FRM 2');
        $response->assertSee('BODEGA');

        // 2. Recognition statuses are visible
        $response->assertSee('RECOGNIZED');
        $response->assertSee('GAMA FARM 2');
        $response->assertSee('POSSIBLE MATCH');
        $response->assertSee('UNKNOWN LOCATION');

        // 3. Confirm in database raw text was NOT overwritten
        $this->assertEquals('G2', $trip1->fresh()->destination_address);
        $this->assertEquals('GAMA FRM 2', $trip2->fresh()->destination_address);
        $this->assertEquals('BODEGA', $trip3->fresh()->destination_address);
    }

    public function test_resolve_unknown_destination_workflow_links_location_and_learns_alias(): void
    {
        // Trip with unknown destination "BODEGA"
        $trip = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => '2026-09-16',
            'time_in' => '10:00:00',
            'origin_latitude' => 14.50,
            'origin_longitude' => 121.00,
            'destination_address' => 'BODEGA',
            'status' => DriverTrip::STATUS_COMPLETED,
        ]);

        // Second past trip with same destination "BODEGA"
        $pastTrip = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => '2026-09-15',
            'time_in' => '11:00:00',
            'origin_latitude' => 14.50,
            'origin_longitude' => 121.00,
            'destination_address' => 'BODEGA',
            'status' => DriverTrip::STATUS_COMPLETED,
        ]);

        // Operator resolves trip to CENTRAL WAREHOUSE
        $response = $this->actingAs($this->admin)->post(route('itineraries.resolveLocation', $trip), [
            'location_id' => $this->warehouse->id,
            'save_as_alias' => 1,
            'backfill_historical' => 1,
        ]);

        $response->assertSessionHas('success');

        $trip->refresh();
        $pastTrip->refresh();

        // 1. Raw destination_address is NOT overwritten!
        $this->assertEquals('BODEGA', $trip->destination_address);
        $this->assertEquals('BODEGA', $pastTrip->destination_address);

        // 2. Authoritative location_id is linked
        $this->assertEquals($this->warehouse->id, $trip->location_id);
        $this->assertEquals($this->warehouse->id, $pastTrip->location_id);

        // 3. "BODEGA" is now a registered alias of Central Warehouse
        $this->assertTrue(
            $this->warehouse->aliases()->where('alias', 'BODEGA')->exists(),
            'Alias BODEGA should be registered under Central Warehouse'
        );

        // 4. Future trip with "BODEGA" is automatically recognized
        $futureTrip = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => '2026-09-17',
            'time_in' => '14:00:00',
            'origin_latitude' => 14.50,
            'origin_longitude' => 121.00,
            'destination_address' => 'BODEGA',
            'status' => DriverTrip::STATUS_COMPLETED,
        ]);

        $service = app(LocationRecognitionService::class);
        $res = $service->recognizeForTrip($futureTrip);

        $this->assertEquals(
            LocationRecognitionService::STATUS_RECOGNIZED,
            $res['status'],
            'Subsequent BODEGA entry must be automatically RECOGNIZED!'
        );
        $this->assertEquals($this->warehouse->id, $res['location']?->id);
    }

    public function test_itinerary_show_displays_recognized_location_details(): void
    {
        $trip = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => '2026-09-16',
            'time_in' => '08:00:00',
            'origin_latitude' => 14.50,
            'origin_longitude' => 121.00,
            'destination_latitude' => 14.845,
            'destination_longitude' => 120.812,
            'destination_address' => 'G2',
            'status' => DriverTrip::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($this->admin)->get(route('itineraries.show', $trip));
        $response->assertOk();

        $response->assertSee('G2');
        $response->assertSee('RECOGNIZED');
        $response->assertSee('GAMA FARM 2');
        $response->assertSee('Farm 2 Road, Malolos, Bulacan');
    }
}
