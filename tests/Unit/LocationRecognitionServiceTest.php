<?php

namespace Tests\Unit;

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

class LocationRecognitionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LocationRecognitionService $service;

    protected Location $farm2;

    protected Location $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LocationRecognitionService;

        $admin = User::factory()->admin()->create();

        // Setup GAMA FARM 2 (Code: G2)
        $this->farm2 = Location::create([
            'code' => 'G2',
            'official_name' => 'GAMA FARM 2',
            'type' => Location::TYPE_FARM,
            'latitude' => 14.8450000,
            'longitude' => 120.8120000,
            'address' => 'Farm 2 Road, Malolos, Bulacan',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        foreach (['G2', 'GAMA 2', 'FARM 2', 'GAMA FARM2', 'G2 FARM'] as $alias) {
            LocationAlias::create([
                'location_id' => $this->farm2->id,
                'alias' => $alias,
            ]);
        }

        // Setup CENTRAL WAREHOUSE (Code: CWH)
        $this->warehouse = Location::create([
            'code' => 'CWH',
            'official_name' => 'CENTRAL WAREHOUSE',
            'type' => Location::TYPE_WAREHOUSE,
            'latitude' => 14.6500000,
            'longitude' => 121.0500000,
            'address' => 'Central Depot, Quezon City',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        foreach (['CWH', 'WAREHOUSE', 'MAIN BODEGA', 'CENTRAL WH'] as $alias) {
            LocationAlias::create([
                'location_id' => $this->warehouse->id,
                'alias' => $alias,
            ]);
        }
    }

    public function test_exact_code_recognition(): void
    {
        $res = $this->service->recognize('G2');
        $this->assertEquals(LocationRecognitionService::STATUS_RECOGNIZED, $res['status']);
        $this->assertEquals($this->farm2->id, $res['location']?->id);
        $this->assertEquals('exact_code', $res['match_type']);
        $this->assertEquals(100, $res['confidence']);
    }

    public function test_exact_official_name_recognition(): void
    {
        $res = $this->service->recognize('GAMA FARM 2');
        $this->assertEquals(LocationRecognitionService::STATUS_RECOGNIZED, $res['status']);
        $this->assertEquals($this->farm2->id, $res['location']?->id);
    }

    public function test_alias_recognition(): void
    {
        $res = $this->service->recognize('FARM 2');
        $this->assertEquals(LocationRecognitionService::STATUS_RECOGNIZED, $res['status']);
        $this->assertEquals($this->farm2->id, $res['location']?->id);
        $this->assertEquals('exact_alias', $res['match_type']);
    }

    public function test_case_insensitive_matching(): void
    {
        $resLowerCode = $this->service->recognize('g2');
        $this->assertEquals(LocationRecognitionService::STATUS_RECOGNIZED, $resLowerCode['status']);
        $this->assertEquals($this->farm2->id, $resLowerCode['location']?->id);

        $resLowerAlias = $this->service->recognize('farm 2');
        $this->assertEquals(LocationRecognitionService::STATUS_RECOGNIZED, $resLowerAlias['status']);
        $this->assertEquals($this->farm2->id, $resLowerAlias['location']?->id);

        $resLowerName = $this->service->recognize('central warehouse');
        $this->assertEquals(LocationRecognitionService::STATUS_RECOGNIZED, $resLowerName['status']);
        $this->assertEquals($this->warehouse->id, $resLowerName['location']?->id);
    }

    public function test_normalized_matching_with_punctuation_and_spaces(): void
    {
        // "G-2" should resolve to G2
        $resHyphen = $this->service->recognize('G-2');
        $this->assertEquals(LocationRecognitionService::STATUS_RECOGNIZED, $resHyphen['status']);
        $this->assertEquals($this->farm2->id, $resHyphen['location']?->id);

        // Multiple spaces: "GAMA   FARM  2"
        $resSpaces = $this->service->recognize('GAMA   FARM  2');
        $this->assertEquals(LocationRecognitionService::STATUS_RECOGNIZED, $resSpaces['status']);
        $this->assertEquals($this->farm2->id, $resSpaces['location']?->id);
    }

    public function test_unknown_destination(): void
    {
        $res = $this->service->recognize('UNKNOWN UNLISTED BARRACKS');
        $this->assertEquals(LocationRecognitionService::STATUS_UNKNOWN, $res['status']);
        $this->assertNull($res['location']);
        $this->assertNull($res['suggested_location']);
    }

    public function test_controlled_fuzzy_matching_produces_possible_match_and_never_auto_assigns(): void
    {
        // "GAMA FRM 2" is very similar to "GAMA FARM 2"
        $res = $this->service->recognize('GAMA FRM 2');

        $this->assertEquals(LocationRecognitionService::STATUS_POSSIBLE_MATCH, $res['status']);
        // Adjustment 2: Fuzzy matching must NEVER automatically assign location
        $this->assertNull($res['location'], 'Fuzzy match must NEVER auto-assign location_id!');
        $this->assertNotNull($res['suggested_location']);
        $this->assertEquals($this->farm2->id, $res['suggested_location']->id);
        $this->assertGreaterThanOrEqual(75, $res['confidence']);
    }

    public function test_explicit_location_id_takes_absolute_precedence(): void
    {
        // Even if text is random, explicit location_id yields RECOGNIZED
        $res = $this->service->recognize('RANDOM OLD TEXT', $this->farm2->id);

        $this->assertEquals(LocationRecognitionService::STATUS_RECOGNIZED, $res['status']);
        $this->assertEquals($this->farm2->id, $res['location']?->id);
        $this->assertEquals('explicit', $res['match_type']);
    }

    public function test_prevent_ambiguous_active_aliases(): void
    {
        // "G2" is already an active alias of farm2
        $conflict = $this->service->findConflictingLocationForAlias('G2');
        $this->assertNotNull($conflict);
        $this->assertEquals($this->farm2->id, $conflict->id);

        // When checking for farm2 itself, it's not a conflict
        $noSelfConflict = $this->service->findConflictingLocationForAlias('G2', $this->farm2->id);
        $this->assertNull($noSelfConflict);

        // Brand new alias has no conflict
        $cleanAlias = $this->service->findConflictingLocationForAlias('NEW UNUSED ALIAS');
        $this->assertNull($cleanAlias);
    }

    public function test_backfill_historical_trips_preserves_raw_destination_text(): void
    {
        $vehicleType = VehicleType::create(['code' => 'TR', 'name' => 'TRUCK']);
        $driver = User::factory()->driver()->create();
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::create([
            'equipment_code' => 'TR-99',
            'vehicle_type_id' => $vehicleType->id,
            'model' => 'ISUZU',
            'created_by' => $admin->id,
        ]);

        // Create 2 past trips with destination_address "BODEGA" and null location_id
        $trip1 = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trip_date' => '2026-09-15',
            'time_in' => '08:00:00',
            'origin_latitude' => 14.50,
            'origin_longitude' => 121.00,
            'destination_address' => 'BODEGA',
            'location_id' => null,
            'status' => DriverTrip::STATUS_COMPLETED,
        ]);

        $trip2 = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trip_date' => '2026-09-16',
            'time_in' => '09:00:00',
            'origin_latitude' => 14.50,
            'origin_longitude' => 121.00,
            'destination_address' => 'BODEGA',
            'location_id' => null,
            'status' => DriverTrip::STATUS_COMPLETED,
        ]);

        $count = $this->service->backfillHistoricalTrips('BODEGA', $this->warehouse);
        $this->assertEquals(2, $count);

        $trip1->refresh();
        $trip2->refresh();

        // Confirm location_id was backfilled
        $this->assertEquals($this->warehouse->id, $trip1->location_id);
        $this->assertEquals($this->warehouse->id, $trip2->location_id);

        // Confirm raw destination_address was strictly preserved!
        $this->assertEquals('BODEGA', $trip1->destination_address);
        $this->assertEquals('BODEGA', $trip2->destination_address);
    }
}
