<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_devices_index(): void
    {
        $response = $this->get(route('devices.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_devices_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('GPS Device Monitoring');
    }

    public function test_devices_index_displays_devices_and_expiration_badges(): void
    {
        // Device expiring in 20 days (e.g. GAJ-5943 from user's example)
        $expiringDate = Carbon::today()->addDays(20);
        $device = Device::factory()->create([
            'device_name' => 'GAJ-5943',
            'imei' => '865135060611447',
            'model' => 'X3',
            'expiration_date' => $expiringDate,
            'raw_expiration' => $expiringDate->format('Y-m-d').'(Expires in 20 days)',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('GAJ-5943');
        $response->assertSee('865135060611447');
        $response->assertSee('Expires in 20 days');
        $response->assertSee('GPS Renewal / Budget Request Notice');
    }

    public function test_expiring_soon_filter_isolates_30_day_devices(): void
    {
        // Expiring in 15 days
        Device::factory()->create([
            'device_name' => 'EXPIRING-UNIT',
            'expiration_date' => Carbon::today()->addDays(15),
            'created_by' => $this->user->id,
        ]);

        // Active (90 days left)
        Device::factory()->create([
            'device_name' => 'ACTIVE-UNIT',
            'expiration_date' => Carbon::today()->addDays(90),
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('devices.index', ['status' => 'expiring_soon']));
        $response->assertOk();
        $response->assertSee('EXPIRING-UNIT');
        $response->assertDontSee('ACTIVE-UNIT');
    }

    public function test_user_can_create_device_manually(): void
    {
        $response = $this->actingAs($this->user)->post(route('devices.store'), [
            'device_name' => 'KAU-6358',
            'imei' => '865135061382021',
            'model' => 'X3',
            'activated_date' => '2025-07-30',
            'sales_time' => '2025-07-30',
            'sim' => '9327925146',
            'expiration_date' => '2027-08-06',
            'group_name' => 'Default Group',
            'iccid' => '8963032424860427516',
            'imsi' => '515039237445659',
            'mileage' => 7464.03,
        ]);

        $response->assertRedirect(route('devices.index'));
        $this->assertDatabaseHas('devices', [
            'device_name' => 'KAU-6358',
            'imei' => '865135061382021',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_user_can_update_device(): void
    {
        $device = Device::factory()->create(['created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->put(route('devices.update', $device), [
            'device_name' => 'UPDATED-NAME',
            'imei' => $device->imei,
            'model' => 'VG01U',
            'mileage' => 25000.50,
        ]);

        $response->assertRedirect(route('devices.index'));
        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'device_name' => 'UPDATED-NAME',
            'model' => 'VG01U',
        ]);
    }

    public function test_user_can_delete_device(): void
    {
        $device = Device::factory()->create(['created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('devices.destroy', $device));
        $response->assertRedirect(route('devices.index'));
        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }

    public function test_user_can_download_device_template(): void
    {
        $response = $this->actingAs($this->user)->get(route('devices.downloadTemplate'));
        $response->assertOk();
        $this->assertTrue(
            str_contains($response->headers->get('content-disposition'), 'GPS-Devices-Import-Template.xlsx')
        );
    }

    public function test_user_can_import_csv_with_tracksolid_expiration_format(): void
    {
        $csvContent = implode("\n", [
            'Device Name,IMEI,Model,Activated Date,Sales Time,SIM,User Expiration Date,Group,ICCID,IMSI,Mileage',
            'GAJ-5943,865135060611447,X3,2025-04-25,2025-04-25,9982474024,2026-10-04(Expires in 20 days),Default Group,89630324227008132231,515039232540055,19228.42',
            'KAR 7806,865135061409585,X3,2026-05-20,2026-05-20,9761426565,2027-05-02,Default Group,89630324227008132232,515039232540056,1429.62',
            'test-expired,865135063557668,X3,2022-06-23,2022-06-23,9660510502,Expired,Default Group,89630324227008132235,515039232540059,14615.76',
        ]);

        $file = UploadedFile::fake()->createWithContent('devices.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('devices.importData'), [
            'file' => $file,
            'mode' => 'update',
        ]);

        $response->assertRedirect(route('devices.index'));

        // Verify GAJ-5943 was imported with clean date extracted from "2026-10-04(Expires in 20 days)"
        $gaj = Device::where('imei', '865135060611447')->first();
        $this->assertNotNull($gaj);
        $this->assertEquals('GAJ-5943', $gaj->device_name);
        $this->assertEquals('2026-10-04', $gaj->expiration_date->format('Y-m-d'));
        $this->assertEquals(19228.42, $gaj->mileage);

        // Verify test-expired was imported with raw_expiration 'Expired'
        $testExpired = Device::where('imei', '865135063557668')->first();
        $this->assertNotNull($testExpired);
        $this->assertEquals('test-expired', $testExpired->device_name);
        $this->assertEquals('Expired', $testExpired->raw_expiration);
    }

    public function test_user_can_generate_devices_pdf_report(): void
    {
        Device::factory()->count(3)->create(['created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('devices.pdf'));
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), '.pdf'));
    }

    public function test_user_can_export_devices_excel(): void
    {
        Device::factory()->count(3)->create(['created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('devices.export'));
        $response->assertOk();
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), '.xlsx'));
    }

    public function test_import_devices_auto_links_with_vehicles_and_syncs_gps_status(): void
    {
        // 1. Create a vehicle with plate GAJ-5943 and NO gps status
        $vehicle = Vehicle::create([
            'equipment_code' => 'BT 01',
            'plate_number' => 'GAJ-5943',
            'gps_status' => 'NO',
            'created_by' => $this->user->id,
        ]);

        // 2. Import CSV containing device matching GAJ-5943
        $csvContent = implode("\n", [
            'Device Name,IMEI,Model,Activated Date,Sales Time,SIM,User Expiration Date,Group,ICCID,IMSI,Mileage',
            'GAJ-5943,865135060611447,X3,2025-04-25,2025-04-25,9982474024,2026-10-04(Expires in 20 days),Default Group,89630324227008132231,515039232540055,19228.42',
        ]);

        $file = UploadedFile::fake()->createWithContent('devices.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('devices.importData'), [
            'file' => $file,
            'mode' => 'update',
        ]);

        $response->assertRedirect(route('devices.index'));
        $response->assertSessionHas('success');

        // 3. Verify device is linked to vehicle
        $device = Device::where('imei', '865135060611447')->first();
        $this->assertNotNull($device);
        $this->assertEquals($vehicle->id, $device->vehicle_id);

        // 4. Verify vehicle gps_status synced to YES (active/expiring in 20 days)
        $vehicle->refresh();
        $this->assertEquals('YES', $vehicle->gps_status);
    }

    public function test_manual_sync_vehicles_links_unpaired_records_and_updates_status(): void
    {
        // 1. Unlinked vehicle
        $vehicle = Vehicle::create([
            'equipment_code' => 'BT 3',
            'plate_number' => 'KAS-3432',
            'gps_status' => 'NO',
            'created_by' => $this->user->id,
        ]);

        // 2. Unlinked device with combined name "BT3 - KAS-3432" and expired status
        $device = Device::create([
            'device_name' => 'BT3 - KAS-3432',
            'imei' => '865135060611999',
            'raw_expiration' => 'Expired',
            'created_by' => $this->user->id,
        ]);

        $this->assertNull($device->vehicle_id);

        // 3. Hit syncVehicles endpoint
        $response = $this->actingAs($this->user)->post(route('devices.syncVehicles'));
        $response->assertRedirect(route('devices.index'));
        $response->assertSessionHas('success');

        // 4. Verify paired and status updated to EXPIRED
        $device->refresh();
        $vehicle->refresh();

        $this->assertEquals($vehicle->id, $device->vehicle_id);
        $this->assertEquals('EXPIRED', $vehicle->gps_status);
    }

    public function test_device_show_page_displays_linked_vehicle_details(): void
    {
        $vehicle = Vehicle::create([
            'equipment_code' => 'SV 18',
            'plate_number' => 'KAU 4688',
            'gps_status' => 'YES',
            'operator_driver' => 'Driver Test',
            'created_by' => $this->user->id,
        ]);

        $device = Device::create([
            'device_name' => 'SV 18 - KAU 4688',
            'imei' => '865135060611888',
            'vehicle_id' => $vehicle->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('devices.show', $device));
        $response->assertOk();
        $response->assertSee('Auto-Linked Vehicle');
        $response->assertSee('SV 18');
        $response->assertSee('KAU 4688');
        $response->assertSee('Driver Test');
    }

    public function test_vehicle_show_page_displays_linked_gps_tracker_details(): void
    {
        $vehicle = Vehicle::create([
            'equipment_code' => 'SV 18',
            'plate_number' => 'KAU 4688',
            'gps_status' => 'YES',
            'created_by' => $this->user->id,
        ]);

        $device = Device::create([
            'device_name' => 'SV 18 - KAU 4688',
            'imei' => '865135060611888',
            'vehicle_id' => $vehicle->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('vehicles.show', $vehicle));
        $response->assertOk();
        $response->assertSee('Auto-Linked GPS Tracker');
        $response->assertSee('SV 18 - KAU 4688');
        $response->assertSee('865135060611888');
    }
}
