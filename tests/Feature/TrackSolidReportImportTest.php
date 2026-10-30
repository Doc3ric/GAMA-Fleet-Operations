<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\FleetDispatch;
use App\Models\GpsReportRecord;
use App\Models\MileageReconciliation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TrackSolidReportImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TrackSolidReportImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Vehicle $vehicle1;

    protected Vehicle $vehicle2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->operator = User::factory()->operator()->create();

        $this->vehicle1 = Vehicle::factory()->create([
            'equipment_code' => 'BT-101',
            'plate_number' => 'NBC-1234',
            'created_by' => $this->admin->id,
        ]);

        Device::factory()->create([
            'vehicle_id' => $this->vehicle1->id,
            'imei' => '868210041111111',
            'device_name' => 'BT-101',
            'created_by' => $this->admin->id,
        ]);

        $this->vehicle2 = Vehicle::factory()->create([
            'equipment_code' => 'BT-102',
            'plate_number' => 'NBC-5678',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_preview_tracksolid_report_parsing(): void
    {
        $csvContent = implode("\n", [
            'Device Name,IMEI,Start Time,End Time,Distance',
            'BT-101,868210041111111,2026-10-16 08:00:00,2026-10-16 12:00:00,125.5',
            'UNKNOWN-99,868210049999999,2026-10-16 09:00:00,2026-10-16 13:00:00,80.0',
        ]);

        $file = UploadedFile::fake()->createWithContent('report.csv', $csvContent);

        $response = $this->actingAs($this->operator)
            ->post(route('tracksolid-reports.preview'), [
                'report_file' => $file,
            ]);

        $response->assertOk()
            ->assertViewIs('tracksolid-reports.preview')
            ->assertSee('report.csv')
            ->assertSee('BT-101')
            ->assertSee('125.5');
    }

    public function test_duplicate_rows_detected_and_flagged(): void
    {
        $service = app(TrackSolidReportImportService::class);

        $csvContent = implode("\n", [
            'Device Name,IMEI,Start Time,End Time,Distance',
            'BT-101,868210041111111,2026-10-16 08:00:00,2026-10-16 12:00:00,125.5',
            // Exactly duplicate row
            'BT-101,868210041111111,2026-10-16 08:00:00,2026-10-16 12:00:00,125.5',
        ]);

        $file = UploadedFile::fake()->createWithContent('report_dup.csv', $csvContent);
        $parsed = $service->parse($file);

        $this->assertEquals(2, $parsed['total_rows']);
        $this->assertEquals(1, $parsed['matched_count']);
        $this->assertEquals(1, $parsed['duplicate_count']);
        $this->assertEquals(GpsReportRecord::STATUS_DUPLICATE, $parsed['rows'][1]['match_status']);
    }

    public function test_commit_tracksolid_report_links_gps_distance_to_reconciliation(): void
    {
        $service = app(TrackSolidReportImportService::class);
        $today = Carbon::today()->toDateString();

        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->vehicle1->id,
            'trip_date' => $today,
            'created_by' => $this->admin->id,
        ]);

        $reconciliation = MileageReconciliation::factory()->create([
            'dispatch_id' => $dispatch->id,
            'vehicle_id' => $this->vehicle1->id,
            'trip_date' => $today,
            'starting_odometer' => 1000.0,
            'ending_odometer' => 1125.0,
            'actual_odometer_km' => 125.0,
            'gps_reported_km' => null,
            'status' => MileageReconciliation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);

        $csvContent = implode("\n", [
            'Device Name,IMEI,Start Time,End Time,Distance',
            "BT-101,868210041111111,{$today} 08:00:00,{$today} 12:00:00,124.8",
        ]);

        $file = UploadedFile::fake()->createWithContent('report.csv', $csvContent);
        $parsed = $service->parse($file);

        $import = $service->commit($file, $parsed, $this->operator->id, true);

        $this->assertDatabaseHas('gps_report_imports', [
            'id' => $import->id,
            'matched_rows' => 1,
        ]);

        $reconciliation->refresh();
        $this->assertEquals(124.8, $reconciliation->gps_reported_km);
        $this->assertEquals(0.2, $reconciliation->odometer_gps_difference_km);
    }

    public function test_approved_reconciliations_are_not_silently_overwritten_by_import(): void
    {
        $service = app(TrackSolidReportImportService::class);
        $today = Carbon::today()->toDateString();

        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->vehicle1->id,
            'trip_date' => $today,
            'created_by' => $this->admin->id,
        ]);

        $reconciliation = MileageReconciliation::factory()->approved()->create([
            'dispatch_id' => $dispatch->id,
            'vehicle_id' => $this->vehicle1->id,
            'trip_date' => $today,
            'gps_reported_km' => 100.0,
            'created_by' => $this->admin->id,
        ]);

        $csvContent = implode("\n", [
            'Device Name,IMEI,Start Time,End Time,Distance',
            "BT-101,868210041111111,{$today} 08:00:00,{$today} 12:00:00,150.0",
        ]);

        $file = UploadedFile::fake()->createWithContent('report.csv', $csvContent);
        $parsed = $service->parse($file);

        $service->commit($file, $parsed, $this->operator->id, true);

        $reconciliation->refresh();
        // Preserves original approved value!
        $this->assertEquals(100.0, $reconciliation->gps_reported_km);
    }
}
