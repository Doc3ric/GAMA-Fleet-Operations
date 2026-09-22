<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VehicleImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        VehicleType::firstOrCreate(['code' => 'BH'], ['code' => 'BH', 'name' => 'BACKHOE']);
        VehicleType::firstOrCreate(['code' => 'DT'], ['code' => 'DT', 'name' => 'DUMP TRUCK']);
    }

    public function test_can_download_vehicle_import_template(): void
    {
        $response = $this->actingAs($this->user)->get(route('vehicles.downloadTemplate'));

        $response->assertOk();
        $this->assertTrue(str_contains(
            $response->headers->get('content-disposition', ''),
            'Vehicle-Master-List-Template.xlsx'
        ));
    }

    public function test_can_import_vehicles_from_csv_with_template_headers(): void
    {
        $csvContent = "EQUIPMENT CODE,VEHICLE TYPE,MODEL,PLATE NUMBER,DATE ACQUIRED,FUEL,STATUS,LOCATION,PROJECT CODE,OPERATOR/DRIVER,HELPER,GPS STATUS\n";
        $csvContent .= "BH 5,BACKHOE,CAT 320,ABC-1234,2024-01-15,16-20 / LIT/HR,0.8 / RUNNING,Project Site A,PRJ-2026-001,Juan Dela Cruz,Pedro Santos,YES\n";
        $csvContent .= "DT 01,DUMP TRUCK,HINO 700,XYZ-5678,2023-08-20,25-30 / LIT/HR,1.0 / RUNNING,Main Yard,PRJ-2026-002,Mario Gomez,Jose Ramos,FOR CHECKUP\n";

        $file = UploadedFile::fake()->createWithContent('vehicles.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('vehicles.importData'), [
            'file' => $file,
            'mode' => 'update',
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', [
            'equipment_code' => 'BH 5',
            'model' => 'CAT 320',
            'plate_number' => 'ABC-1234',
            'fuel_min' => 16,
            'fuel_max' => 20,
            'fuel_unit' => 'LIT/HR',
            'status_value' => 0.8,
            'status_label' => 'RUNNING',
            'location' => 'Project Site A',
            'project_code' => 'PRJ-2026-001',
            'operator_driver' => 'Juan Dela Cruz',
            'helper' => 'Pedro Santos',
            'gps_status' => 'YES',
        ]);

        $this->assertDatabaseHas('vehicles', [
            'equipment_code' => 'DT 01',
            'model' => 'HINO 700',
            'plate_number' => 'XYZ-5678',
            'fuel_min' => 25,
            'fuel_max' => 30,
            'fuel_unit' => 'LIT/HR',
            'status_value' => 1.0,
            'status_label' => 'RUNNING',
            'location' => 'Main Yard',
            'project_code' => 'PRJ-2026-002',
            'operator_driver' => 'Mario Gomez',
            'helper' => 'Jose Ramos',
            'gps_status' => 'FOR_CHECKUP',
        ]);
    }

    public function test_import_update_mode_updates_existing_vehicle(): void
    {
        $vehicle = Vehicle::create([
            'equipment_code' => 'BH 5',
            'location' => 'Old Site',
            'gps_status' => 'NO',
            'created_by' => $this->user->id,
        ]);

        $csvContent = "EQUIPMENT CODE,VEHICLE TYPE,MODEL,PLATE NUMBER,DATE ACQUIRED,FUEL,STATUS,LOCATION,PROJECT CODE,OPERATOR/DRIVER,HELPER,GPS STATUS\n";
        $csvContent .= "BH 5,BACKHOE,CAT 320,ABC-1234,2024-01-15,16-20 / LIT/HR,0.8 / RUNNING,New Site,PRJ-2026-001,Juan,Pedro,YES\n";

        $file = UploadedFile::fake()->createWithContent('vehicles.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('vehicles.importData'), [
            'file' => $file,
            'mode' => 'update',
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $this->assertSame(1, Vehicle::where('equipment_code', 'BH 5')->count());
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'location' => 'New Site',
            'gps_status' => 'YES',
        ]);
    }

    public function test_import_append_mode_skips_existing_vehicle(): void
    {
        Vehicle::create([
            'equipment_code' => 'BH 5',
            'location' => 'Original Site',
            'gps_status' => 'NO',
            'created_by' => $this->user->id,
        ]);

        $csvContent = "EQUIPMENT CODE,VEHICLE TYPE,MODEL,PLATE NUMBER,DATE ACQUIRED,FUEL,STATUS,LOCATION,PROJECT CODE,OPERATOR/DRIVER,HELPER,GPS STATUS\n";
        $csvContent .= "BH 5,BACKHOE,CAT 320,ABC-1234,2024-01-15,16-20 / LIT/HR,0.8 / RUNNING,New Site,PRJ-2026-001,Juan,Pedro,YES\n";
        $csvContent .= "EX 01,EXCAVATOR,KOMATSU,EFG-9999,2024-02-01,18 / LIT/HR,STANDBY,Yard,PRJ-2026-002,Roberto,,NO\n";

        $file = UploadedFile::fake()->createWithContent('vehicles.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('vehicles.importData'), [
            'file' => $file,
            'mode' => 'append',
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $this->assertDatabaseHas('vehicles', [
            'equipment_code' => 'BH 5',
            'location' => 'Original Site',
            'gps_status' => 'NO',
        ]);
        $this->assertDatabaseHas('vehicles', [
            'equipment_code' => 'EX 01',
            'location' => 'Yard',
        ]);
    }

    public function test_import_replace_mode_clears_old_records(): void
    {
        Vehicle::create([
            'equipment_code' => 'OLD 01',
            'location' => 'Old Site',
            'gps_status' => 'NO',
            'created_by' => $this->user->id,
        ]);

        $csvContent = "EQUIPMENT CODE,VEHICLE TYPE,MODEL,PLATE NUMBER,DATE ACQUIRED,FUEL,STATUS,LOCATION,PROJECT CODE,OPERATOR/DRIVER,HELPER,GPS STATUS\n";
        $csvContent .= "BH 5,BACKHOE,CAT 320,ABC-1234,2024-01-15,16-20 / LIT/HR,0.8 / RUNNING,Site A,PRJ-2026-001,Juan,Pedro,YES\n";

        $file = UploadedFile::fake()->createWithContent('vehicles.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('vehicles.importData'), [
            'file' => $file,
            'mode' => 'replace',
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $this->assertDatabaseMissing('vehicles', ['equipment_code' => 'OLD 01']);
        $this->assertDatabaseHas('vehicles', ['equipment_code' => 'BH 5']);
    }

    public function test_import_rejects_invalid_file_types(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->user)->post(route('vehicles.importData'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $response->assertSessionHas('error');
    }

    public function test_import_vehicles_auto_links_with_existing_devices(): void
    {
        // 1. Existing unlinked device
        $device = Device::create([
            'device_name' => 'BH 5',
            'imei' => '865135060611777',
            'model' => 'X3',
            'raw_expiration' => 'Expired',
            'created_by' => $this->user->id,
        ]);

        $this->assertNull($device->vehicle_id);

        // 2. Import CSV containing matching vehicle BH 5
        $csvContent = "EQUIPMENT CODE,VEHICLE TYPE,MODEL,PLATE NUMBER,DATE ACQUIRED,FUEL,STATUS,LOCATION,PROJECT CODE,OPERATOR/DRIVER,HELPER,GPS STATUS\n";
        $csvContent .= "BH 5,BACKHOE,CAT 320,ABC-1234,2024-01-15,16-20 / LIT/HR,0.8 / RUNNING,Site A,PRJ-2026-001,Juan,Pedro,YES\n";

        $file = UploadedFile::fake()->createWithContent('vehicles.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('vehicles.importData'), [
            'file' => $file,
            'mode' => 'update',
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $response->assertSessionHas('success');

        // 3. Verify vehicle was created and device linked to it
        $vehicle = Vehicle::where('equipment_code', 'BH 5')->first();
        $this->assertNotNull($vehicle);

        $device->refresh();
        $this->assertEquals($vehicle->id, $device->vehicle_id);

        // 4. Verify vehicle status was synced to EXPIRED based on device
        $this->assertEquals('EXPIRED', $vehicle->gps_status);
    }
}
