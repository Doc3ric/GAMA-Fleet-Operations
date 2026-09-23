<?php

namespace Tests\Feature;

use App\Models\DriverTrip;
use App\Models\Location;
use App\Models\LocationAlias;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Admin Specialist', 'email' => 'admin@gama.com']);
        $this->operator = User::factory()->operator()->create(['name' => 'Operator User', 'email' => 'operator@gama.com']);
        $this->driver = User::factory()->driver()->create(['name' => 'Juan Driver', 'email' => 'juan@gama.com']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('locations.index'))->assertRedirect(route('login'));
        $this->get(route('locations.create'))->assertRedirect(route('login'));
        $this->get(route('locations.map'))->assertRedirect(route('login'));
    }

    public function test_driver_role_cannot_access_location_directory(): void
    {
        $this->actingAs($this->driver)->get(route('locations.index'))->assertForbidden();
        $this->actingAs($this->driver)->get(route('locations.create'))->assertForbidden();
        $this->actingAs($this->driver)->get(route('locations.map'))->assertForbidden();
    }

    public function test_operator_and_admin_can_access_location_directory(): void
    {
        $this->actingAs($this->operator)->get(route('locations.index'))
            ->assertOk()
            ->assertSee('Location Directory');

        $this->actingAs($this->admin)->get(route('locations.index'))
            ->assertOk()
            ->assertSee('Location Directory');
    }

    public function test_can_create_location_with_aliases_and_image(): void
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('entrance.jpg', 640, 480);

        $response = $this->actingAs($this->admin)->post(route('locations.store'), [
            'code' => 'G2',
            'official_name' => 'GAMA FARM 2',
            'type' => Location::TYPE_FARM,
            'latitude' => 14.8450000,
            'longitude' => 120.8120000,
            'address' => 'KM 48 MacArthur Highway, Malolos, Bulacan',
            'barangay' => 'San Juan',
            'municipality' => 'Malolos City',
            'province' => 'Bulacan',
            'status' => Location::STATUS_ACTIVE,
            'notes' => 'Main gate near the highway',
            'image' => $image,
            'aliases' => ['GAMA 2', 'FARM 2', 'GAMA FARM2', 'G2 FARM'],
        ]);

        $location = Location::where('code', 'G2')->first();
        $this->assertNotNull($location);
        $this->assertEquals('GAMA FARM 2', $location->official_name);
        $this->assertEquals(4, $location->aliases()->count());

        $this->assertNotNull($location->image_path);
        Storage::disk('public')->assertExists($location->image_path);

        $response->assertRedirect(route('locations.show', $location));
    }

    public function test_prevent_duplicate_active_code(): void
    {
        Location::create([
            'code' => 'G2',
            'official_name' => 'GAMA FARM 2',
            'type' => Location::TYPE_FARM,
            'latitude' => 14.845,
            'longitude' => 120.812,
            'address' => 'Farm 2 Road',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('locations.store'), [
            'code' => 'G2',
            'official_name' => 'ANOTHER GAMA FARM 2',
            'type' => Location::TYPE_FARM,
            'latitude' => 14.800,
            'longitude' => 120.800,
            'address' => 'Sample Address',
            'status' => Location::STATUS_ACTIVE,
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_prevent_ambiguous_active_aliases_validation(): void
    {
        $farm1 = Location::create([
            'code' => 'G1',
            'official_name' => 'GAMA FARM 1',
            'type' => Location::TYPE_FARM,
            'latitude' => 14.86,
            'longitude' => 120.83,
            'address' => 'Farm 1 Road',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        LocationAlias::create([
            'location_id' => $farm1->id,
            'alias' => 'FARM 1',
        ]);

        // Attempting to create Farm 2 with alias "FARM 1" must be blocked (Adjustment 3)
        $response = $this->actingAs($this->admin)->post(route('locations.store'), [
            'code' => 'G2',
            'official_name' => 'GAMA FARM 2',
            'type' => Location::TYPE_FARM,
            'latitude' => 14.84,
            'longitude' => 120.81,
            'address' => 'Farm 2 Road',
            'status' => Location::STATUS_ACTIVE,
            'aliases' => ['GAMA 2', 'FARM 1'],
        ]);

        $response->assertSessionHasErrors(['aliases']);
        $this->assertNull(Location::where('code', 'G2')->first());
    }

    public function test_coordinates_must_be_valid_geographic_range(): void
    {
        $response = $this->actingAs($this->admin)->post(route('locations.store'), [
            'code' => 'TEST',
            'official_name' => 'INVALID COORDS LOCATION',
            'type' => Location::TYPE_OTHER,
            'latitude' => 150.00, // Invalid latitude (> 90)
            'longitude' => 120.00,
            'address' => 'Sample',
            'status' => Location::STATUS_ACTIVE,
        ]);

        $response->assertSessionHasErrors(['latitude']);
    }

    public function test_can_update_location_and_sync_aliases(): void
    {
        $location = Location::create([
            'code' => 'WH1',
            'official_name' => 'DEPOT WAREHOUSE',
            'type' => Location::TYPE_WAREHOUSE,
            'latitude' => 14.65,
            'longitude' => 121.05,
            'address' => 'Old Address',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        LocationAlias::create(['location_id' => $location->id, 'alias' => 'OLD ALIAS']);

        $response = $this->actingAs($this->admin)->put(route('locations.update', $location), [
            'code' => 'WH1',
            'official_name' => 'CENTRAL LOGISTICS DEPOT',
            'type' => Location::TYPE_WAREHOUSE,
            'latitude' => 14.651,
            'longitude' => 121.052,
            'address' => 'New Updated Address',
            'status' => Location::STATUS_ACTIVE,
            'aliases' => ['NEW ALIAS 1', 'NEW ALIAS 2'],
        ]);

        $response->assertRedirect(route('locations.show', $location));

        $location->refresh();
        $this->assertEquals('CENTRAL LOGISTICS DEPOT', $location->official_name);
        $this->assertEquals('New Updated Address', $location->address);

        $aliases = $location->aliases->pluck('alias')->toArray();
        $this->assertContains('NEW ALIAS 1', $aliases);
        $this->assertContains('NEW ALIAS 2', $aliases);
        $this->assertNotContains('OLD ALIAS', $aliases);
    }

    public function test_deactivate_location_when_referenced_by_trips(): void
    {
        $location = Location::create([
            'code' => 'G2',
            'official_name' => 'GAMA FARM 2',
            'type' => Location::TYPE_FARM,
            'latitude' => 14.84,
            'longitude' => 120.81,
            'address' => 'Farm 2 Road',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $vehicleType = VehicleType::create(['code' => 'TR', 'name' => 'TRUCK']);
        $vehicle = Vehicle::create([
            'equipment_code' => 'TR-10',
            'vehicle_type_id' => $vehicleType->id,
            'model' => 'ISUZU',
            'created_by' => $this->admin->id,
        ]);

        // Create historical trip referencing this location
        $trip = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $vehicle->id,
            'trip_date' => '2026-09-10',
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5,
            'origin_longitude' => 121.0,
            'destination_address' => 'G2',
            'location_id' => $location->id,
            'status' => DriverTrip::STATUS_COMPLETED,
        ]);

        // Attempt to delete location
        $response = $this->actingAs($this->admin)->delete(route('locations.destroy', $location));
        $response->assertRedirect(route('locations.index'));

        // Location must NOT be deleted, but marked Inactive (Adjustment 7)
        $location->refresh();
        $this->assertFalse($location->isActive());
        $this->assertEquals(Location::STATUS_INACTIVE, $location->status);

        // Historical trip is completely intact!
        $trip->refresh();
        $this->assertEquals($location->id, $trip->location_id);
    }

    public function test_can_export_locations_to_excel(): void
    {
        Location::create([
            'code' => 'G2',
            'official_name' => 'GAMA FARM 2',
            'type' => Location::TYPE_FARM,
            'latitude' => 14.84,
            'longitude' => 120.81,
            'address' => 'Farm 2 Road',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('locations.exportExcel'));
        $response->assertOk();
        $this->assertTrue($response->headers->get('content-disposition') !== null);
    }

    public function test_can_download_import_template(): void
    {
        $response = $this->actingAs($this->admin)->get(route('locations.downloadTemplate'));
        $response->assertOk();
    }

    public function test_can_create_location_with_area_consultant_and_contact_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('locations.store'), [
            'code' => 'TESTLOC',
            'official_name' => 'TEST LOCATION WITH CONSULTANT',
            'type' => Location::TYPE_OFFICE,
            'latitude' => 8.1500000,
            'longitude' => 124.8500000,
            'address' => 'Test Address, Bukidnon',
            'status' => Location::STATUS_ACTIVE,
            'area_consultant' => 'Juan Dela Cruz',
            'contact_number' => '09171234567',
        ]);

        $location = Location::where('code', 'TESTLOC')->first();
        $this->assertNotNull($location);
        $this->assertEquals('Juan Dela Cruz', $location->area_consultant);
        $this->assertEquals('09171234567', $location->contact_number);
        $response->assertRedirect(route('locations.show', $location));
    }

    public function test_can_update_location_area_consultant_and_contact_number(): void
    {
        $location = Location::create([
            'code' => 'UPD1',
            'official_name' => 'UPDATE TEST LOCATION',
            'type' => Location::TYPE_OFFICE,
            'latitude' => 8.15,
            'longitude' => 124.85,
            'address' => 'Initial Address',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('locations.update', $location), [
            'code' => 'UPD1',
            'official_name' => 'UPDATE TEST LOCATION',
            'type' => Location::TYPE_OFFICE,
            'latitude' => 8.15,
            'longitude' => 124.85,
            'address' => 'Initial Address',
            'status' => Location::STATUS_ACTIVE,
            'area_consultant' => 'Maria Santos',
            'contact_number' => '09181234567',
        ]);

        $response->assertRedirect(route('locations.show', $location));
        $location->refresh();
        $this->assertEquals('Maria Santos', $location->area_consultant);
        $this->assertEquals('09181234567', $location->contact_number);
    }

    public function test_area_consultant_and_contact_number_are_optional(): void
    {
        $response = $this->actingAs($this->admin)->post(route('locations.store'), [
            'code' => 'NOCONTACT',
            'official_name' => 'LOCATION WITHOUT CONSULTANT',
            'type' => Location::TYPE_FARM,
            'latitude' => 8.10,
            'longitude' => 124.80,
            'address' => 'Remote Farm, Bukidnon',
            'status' => Location::STATUS_ACTIVE,
            // area_consultant and contact_number intentionally omitted
        ]);

        $location = Location::where('code', 'NOCONTACT')->first();
        $this->assertNotNull($location);
        $this->assertNull($location->area_consultant);
        $this->assertNull($location->contact_number);
        $response->assertRedirect(route('locations.show', $location));
    }

    public function test_wrapped_longitude_from_multi_world_map_panning_is_automatically_normalized(): void
    {
        $response = $this->actingAs($this->admin)->post(route('locations.store'), [
            'code' => 'ANK',
            'official_name' => 'ANAKCIANO',
            'type' => Location::TYPE_FARM,
            'latitude' => 8.4161577,
            'longitude' => 1204.8222579, // Multi-world wrapped longitude from Leaflet (124.8222579 + 3 * 360)
            'address' => 'Sayre Hwy, Manolo Fortich, Bukidnon',
            'status' => Location::STATUS_ACTIVE,
        ]);

        $response->assertSessionHasNoErrors();
        $location = Location::where('code', 'ANK')->first();
        $this->assertNotNull($location);
        $this->assertEquals(8.4161577, $location->latitude);
        $this->assertEquals(124.8222579, $location->longitude);
    }

    public function test_operator_can_delete_unreferenced_location(): void
    {
        $location = Location::create([
            'code' => 'DEL-OP',
            'official_name' => 'LOCATION TO DELETE BY OPERATOR',
            'type' => Location::TYPE_WAREHOUSE,
            'latitude' => 8.12,
            'longitude' => 124.75,
            'address' => 'Test Address, Bukidnon',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $this->operator->id,
        ]);

        $response = $this->actingAs($this->operator)->delete(route('locations.destroy', $location));
        $response->assertRedirect(route('locations.index'));
        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_driver_cannot_delete_location(): void
    {
        $location = Location::create([
            'code' => 'DEL-DRV',
            'official_name' => 'LOCATION ATTEMPT BY DRIVER',
            'type' => Location::TYPE_WAREHOUSE,
            'latitude' => 8.12,
            'longitude' => 124.75,
            'address' => 'Test Address, Bukidnon',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->driver)->delete(route('locations.destroy', $location));
        $response->assertForbidden();
        $this->assertDatabaseHas('locations', ['id' => $location->id]);
    }
}
