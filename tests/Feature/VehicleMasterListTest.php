<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleMasterListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        VehicleType::create(['code' => 'BH', 'name' => 'BACKHOE']);
        VehicleType::create(['code' => 'DT', 'name' => 'DUMP TRUCK']);
    }

    public function test_guest_cannot_access_vehicles_index(): void
    {
        $response = $this->get(route('vehicles.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_vehicles_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('vehicles.index'));
        $response->assertOk();
        $response->assertSee('Vehicle Master List');
    }

    public function test_vehicles_index_shows_vehicles(): void
    {
        $backhoe = VehicleType::where('code', 'BH')->first();
        Vehicle::create([
            'equipment_code' => 'BH 5',
            'vehicle_type_id' => $backhoe->id,
            'model' => 'CAT 320',
            'plate_number' => 'ABC-1234',
            'gps_status' => 'YES',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('vehicles.index'));

        $response->assertOk();
        $response->assertSee('BH 5');
        $response->assertSee('CAT 320');
        $response->assertSee('ABC-1234');
    }

    public function test_vehicles_index_can_search(): void
    {
        Vehicle::create(['equipment_code' => 'BH 5', 'gps_status' => 'YES', 'created_by' => $this->user->id, 'location' => 'Site A']);
        Vehicle::create(['equipment_code' => 'DT 01', 'gps_status' => 'NO', 'created_by' => $this->user->id, 'location' => 'Yard']);

        $response = $this->actingAs($this->user)->get(route('vehicles.index', ['search' => 'BH']));

        $response->assertOk();
        $response->assertSee('BH 5');
        $response->assertDontSee('DT 01');
    }

    public function test_vehicles_index_can_filter_by_gps_status(): void
    {
        Vehicle::create(['equipment_code' => 'BH 5', 'gps_status' => 'YES', 'created_by' => $this->user->id]);
        Vehicle::create(['equipment_code' => 'DT 01', 'gps_status' => 'NO', 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('vehicles.index', ['gps_status' => 'YES']));

        $response->assertOk();
        $response->assertSee('BH 5');
        $response->assertDontSee('DT 01');
    }

    public function test_authenticated_user_can_view_create_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('vehicles.create'));

        $response->assertOk();
        $response->assertSee('Equipment Code');
        $response->assertSee('GPS Status');
        $response->assertSee('BACKHOE');
    }

    public function test_can_create_vehicle_with_valid_data(): void
    {
        $backhoe = VehicleType::where('code', 'BH')->first();

        $response = $this->actingAs($this->user)->post(route('vehicles.store'), [
            'equipment_code' => 'BH 10',
            'vehicle_type_id' => $backhoe->id,
            'model' => 'KOMATSU PC200',
            'plate_number' => 'XYZ-9999',
            'date_acquired' => '2024-01-15',
            'fuel_min' => '16',
            'fuel_max' => '20',
            'fuel_unit' => 'LIT/HR',
            'status_value' => '0.8',
            'status_label' => 'RUNNING',
            'location' => 'Project Site A',
            'project_code' => 'PRJ-2026-001',
            'operator_driver' => 'Juan Dela Cruz',
            'helper' => null,
            'gps_status' => 'YES',
            'notes' => null,
        ]);

        $response->assertRedirect(route('vehicles.index'));

        $this->assertDatabaseHas('vehicles', [
            'equipment_code' => 'BH 10',
            'model' => 'KOMATSU PC200',
            'gps_status' => 'YES',
            'fuel_min' => 16,
            'fuel_max' => 20,
            'fuel_unit' => 'LIT/HR',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_store_requires_equipment_code(): void
    {
        $response = $this->actingAs($this->user)->post(route('vehicles.store'), [
            'gps_status' => 'NO',
        ]);

        $response->assertSessionHasErrors('equipment_code');
    }

    public function test_equipment_code_must_be_unique(): void
    {
        Vehicle::create(['equipment_code' => 'BH 5', 'gps_status' => 'YES', 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->post(route('vehicles.store'), [
            'equipment_code' => 'BH 5',
            'gps_status' => 'NO',
        ]);

        $response->assertSessionHasErrors('equipment_code');
    }

    public function test_gps_status_must_be_valid_enum(): void
    {
        $response = $this->actingAs($this->user)->post(route('vehicles.store'), [
            'equipment_code' => 'BH 99',
            'gps_status' => 'INVALID_VALUE',
        ]);

        $response->assertSessionHasErrors('gps_status');
    }

    public function test_can_edit_vehicle(): void
    {
        $vehicle = Vehicle::create(['equipment_code' => 'BH 5', 'gps_status' => 'YES', 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('vehicles.edit', $vehicle));

        $response->assertOk();
        $response->assertSee('BH 5');
    }

    public function test_can_update_vehicle(): void
    {
        $vehicle = Vehicle::create([
            'equipment_code' => 'BH 5',
            'gps_status' => 'YES',
            'created_by' => $this->user->id,
            'location' => 'Old Site',
        ]);

        $response = $this->actingAs($this->user)->put(route('vehicles.update', $vehicle), [
            'equipment_code' => 'BH 5',
            'gps_status' => 'EXPIRED',
            'location' => 'New Site',
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'gps_status' => 'EXPIRED', 'location' => 'New Site']);
    }

    public function test_can_delete_vehicle(): void
    {
        $vehicle = Vehicle::create(['equipment_code' => 'BH 5', 'gps_status' => 'NO', 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('vehicles.destroy', $vehicle));

        $response->assertRedirect(route('vehicles.index'));
        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    public function test_vehicle_model_fuel_display_attribute(): void
    {
        $vehicle = new Vehicle(['fuel_min' => 16, 'fuel_max' => 20, 'fuel_unit' => 'LIT/HR']);
        $this->assertSame('16–20 LIT/HR', $vehicle->fuel_display);

        $vehicle2 = new Vehicle(['fuel_min' => 20, 'fuel_max' => 20, 'fuel_unit' => 'LIT/HR']);
        $this->assertSame('20 LIT/HR', $vehicle2->fuel_display);

        $vehicle3 = new Vehicle(['fuel_min' => null, 'fuel_max' => null]);
        $this->assertNull($vehicle3->fuel_display);
    }

    public function test_vehicle_model_status_display_attribute(): void
    {
        $vehicle = new Vehicle(['status_value' => 0.8, 'status_label' => 'RUNNING']);
        $this->assertSame('0.8 / RUNNING', $vehicle->status_display);

        $vehicle2 = new Vehicle(['status_value' => null, 'status_label' => null]);
        $this->assertNull($vehicle2->status_display);
    }

    public function test_vehicle_model_gps_status_color_attribute(): void
    {
        $yes = new Vehicle(['gps_status' => 'YES']);
        $this->assertSame('bg-emerald-100', $yes->gps_status_color['bg']);

        $expired = new Vehicle(['gps_status' => 'EXPIRED']);
        $this->assertSame('bg-red-100', $expired->gps_status_color['bg']);

        $checkup = new Vehicle(['gps_status' => 'FOR_CHECKUP']);
        $this->assertSame('bg-amber-100', $checkup->gps_status_color['bg']);
    }

    public function test_vehicle_model_gps_status_label_attribute(): void
    {
        $v = new Vehicle(['gps_status' => 'FOR_CHECKUP']);
        $this->assertSame('FOR CHECKUP', $v->gps_status_label);

        $v2 = new Vehicle(['gps_status' => 'YES']);
        $this->assertSame('YES', $v2->gps_status_label);
    }

    public function test_update_equipment_code_unique_ignores_self(): void
    {
        $vehicle = Vehicle::create(['equipment_code' => 'BH 5', 'gps_status' => 'YES', 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->put(route('vehicles.update', $vehicle), [
            'equipment_code' => 'BH 5', // same code — should pass
            'gps_status' => 'YES',
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_vehicle_type_seeder_creates_known_types(): void
    {
        $this->assertDatabaseHas('vehicle_types', ['code' => 'BH', 'name' => 'BACKHOE']);
        $this->assertDatabaseHas('vehicle_types', ['code' => 'DT', 'name' => 'DUMP TRUCK']);
    }

    public function test_can_create_vehicle_with_image_upload(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('excavator.jpg', 640, 480);

        $response = $this->actingAs($this->user)->post(route('vehicles.store'), [
            'equipment_code' => 'EX 99',
            'gps_status' => 'YES',
            'image' => $image,
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $vehicle = Vehicle::where('equipment_code', 'EX 99')->first();
        $this->assertNotNull($vehicle);
        $this->assertNotNull($vehicle->image);
        Storage::disk('public')->assertExists($vehicle->image);
    }

    public function test_can_update_vehicle_with_image_upload(): void
    {
        Storage::fake('public');
        $vehicle = Vehicle::create([
            'equipment_code' => 'EX 98',
            'gps_status' => 'YES',
            'created_by' => $this->user->id,
        ]);

        $image = UploadedFile::fake()->image('excavator2.jpg', 640, 480);

        $response = $this->actingAs($this->user)->put(route('vehicles.update', $vehicle), [
            'equipment_code' => 'EX 98',
            'gps_status' => 'YES',
            'image' => $image,
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $vehicle->refresh();
        $this->assertNotNull($vehicle->image);
        Storage::disk('public')->assertExists($vehicle->image);
    }

    public function test_can_upload_image_directly(): void
    {
        Storage::fake('public');
        $vehicle = Vehicle::create([
            'equipment_code' => 'EX 97',
            'gps_status' => 'YES',
            'created_by' => $this->user->id,
        ]);

        $image = UploadedFile::fake()->image('quick.jpg', 640, 480);

        $response = $this->actingAs($this->user)->post(route('vehicles.uploadImage', $vehicle), [
            'image' => $image,
        ]);

        $response->assertSessionHas('success');
        $vehicle->refresh();
        $this->assertNotNull($vehicle->image);
        Storage::disk('public')->assertExists($vehicle->image);
    }
}
