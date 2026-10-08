<?php

namespace Tests\Feature;

use App\Livewire\LongIdlingTable;
use App\Models\AdvancedItinerary;
use App\Models\LongIdlingRecord;
use App\Models\Report;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LongIdlingReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('GAMA');
        $response->assertSee('Fleet Operations');
        $response->assertSee('Fuel PO & Consumption Overview', false);
        $response->assertSee('fuelPoTrendChart');
        $response->assertDontSee('Recent Reports');
        $response->assertDontSee('items logged');
        $response->assertViewHas('fuelChartData');
    }

    public function test_dashboard_fuel_chart_aggregates_recent_itineraries(): void
    {
        $vehicle = Vehicle::factory()->create();

        // Create an itinerary for today
        AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'itinerary_date' => now()->toDateString(),
            'total_distance' => 120.0,
            'fuel_liters_required' => 35.0,
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertOk();

        $chartData = $response->viewData('fuelChartData');
        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('7d', $chartData);
        $this->assertArrayHasKey('14d', $chartData);
        $this->assertArrayHasKey('30d', $chartData);

        // Verify the 7d period aggregates the created itinerary
        $this->assertEquals(35.0, $chartData['7d']['total_liters']);
        $this->assertEquals(120.0, $chartData['7d']['total_distance']);
        $this->assertEquals(1, $chartData['7d']['total_count']);
        $this->assertEquals(round(120.0 / 35.0, 2), $chartData['7d']['avg_efficiency']);
    }

    public function test_authenticated_user_can_view_reports_index(): void
    {
        Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertSee(now()->format('F j, Y'));
    }

    public function test_authenticated_user_can_create_report(): void
    {
        $date = now()->toDateString();

        $response = $this->actingAs($this->user)->post(route('reports.store'), [
            'report_date' => $date,
            'remarks' => 'Test daily report',
        ]);

        $report = Report::whereDate('report_date', $date)->first();
        $this->assertNotNull($report);
        $this->assertEquals($date, $report->report_date->format('Y-m-d'));
        $this->assertEquals('draft', $report->status);
        $this->assertEquals($this->user->id, $report->created_by);
        $response->assertRedirect(route('reports.show', $report));
    }

    public function test_duplicate_previous_report_copies_vehicles(): void
    {
        $prevReport = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->subDay()->toDateString(),
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        LongIdlingRecord::create([
            'report_id' => $prevReport->id,
            'device_name' => 'GAMA-TRUCK-01',
            'imei' => '987654321012345',
            'model' => 'Concox AT4',
            'start_time' => '08:00',
            'end_time' => '09:30',
            'stay_time' => '01:30:00',
            'address' => 'Yesterday Address',
        ]);

        $newDate = now()->toDateString();
        $this->actingAs($this->user)->post(route('reports.store'), [
            'report_date' => $newDate,
            'duplicate_from' => $prevReport->id,
        ]);

        $newReport = Report::whereDate('report_date', $newDate)->first();
        $this->assertNotNull($newReport);

        $this->assertDatabaseHas('long_idling_records', [
            'report_id' => $newReport->id,
            'device_name' => 'GAMA-TRUCK-01',
            'imei' => '987654321012345',
            'model' => 'Concox AT4',
            'start_time' => null,
            'address' => null,
        ]);
    }

    public function test_stay_time_calculation(): void
    {
        $stayTime = LongIdlingRecord::calculateStayTime('08:00', '09:15');
        $this->assertEquals('01:15:00', $stayTime);

        // Stay time with seconds (e.g. 12:30:21 to 13:45:50 -> 01:15:29)
        $stayTimeWithSeconds = LongIdlingRecord::calculateStayTime('12:30:21', '13:45:50');
        $this->assertEquals('01:15:29', $stayTimeWithSeconds);

        // Overnight stay
        $stayTimeOvernight = LongIdlingRecord::calculateStayTime('23:30', '01:00');
        $this->assertEquals('01:30:00', $stayTimeOvernight);

        // Overnight stay with seconds
        $stayTimeOvernightSeconds = LongIdlingRecord::calculateStayTime('23:59:50', '00:00:10');
        $this->assertEquals('00:00:20', $stayTimeOvernightSeconds);
    }

    public function test_can_toggle_report_completion_status(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->patch(route('reports.markComplete', $report));
        $this->assertEquals('completed', $report->fresh()->status);

        $this->actingAs($this->user)->patch(route('reports.markDraft', $report));
        $this->assertEquals('draft', $report->fresh()->status);
    }

    public function test_can_export_report_to_excel(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.exportExcel', $report));
        $response->assertOk();
    }

    public function test_can_generate_pdf_report(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'GAMA-001',
            'imei' => '123456789012301',
            'model' => 'Hikvision DS-MH2111',
            'start_time' => '10:00',
            'end_time' => '11:15',
            'stay_time' => '01:15:00',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'address' => 'Manila City Hall',
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.generatePdf', $report));
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_can_generate_pdf_report_with_large_dataset_without_screenshots(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        for ($i = 1; $i <= 50; $i++) {
            LongIdlingRecord::create([
                'report_id' => $report->id,
                'device_name' => 'TRUCK-'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'imei' => '86596805'.str_pad($i, 7, '0', STR_PAD_LEFT),
                'model' => 'JM01',
                'state' => 'Idling',
                'start_time' => '08:00:00',
                'end_time' => '09:30:00',
                'stay_time' => '01:30:00',
                'latitude' => 8.472287,
                'longitude' => 124.654321,
                'address' => 'Cagayan de Oro City',
                'sort_order' => $i,
            ]);
        }

        $response = $this->actingAs($this->user)->get(route('reports.generatePdf', $report));
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_can_generate_pdf_report_with_screenshots_attached(): void
    {
        Storage::fake('public');

        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        // Create a dummy image file in fake storage
        $imagePath = 'screenshots/'.$report->id.'/evidence.png';
        $file = UploadedFile::fake()->image('evidence.png', 400, 300);
        Storage::disk('public')->put($imagePath, file_get_contents($file->getRealPath()));

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'FLAGGED-VEHICLE-01',
            'imei' => '865968050000001',
            'model' => 'JM01',
            'state' => 'Idling',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'stay_time' => '02:00:00',
            'latitude' => 8.472287,
            'longitude' => 124.654321,
            'address' => 'Macabalan Port, Cagayan de Oro City',
            'image' => $imagePath,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.generatePdf', $report));
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_livewire_spreadsheet_table_workflow(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id])
            ->assertCount('rows', 0)
            ->call('addRow')
            ->assertCount('rows', 1)
            ->call('addRow')
            ->assertCount('rows', 2)
            ->set('rows.0.device_name', 'TEST-DEVICE-01')
            ->set('rows.0.imei', '111222333444555')
            ->set('rows.0.model', 'GPS Tracker v2')
            ->set('rows.0.start_time', '09:00')
            ->set('rows.0.end_time', '10:45')
            ->call('saveAll');

        $this->assertDatabaseHas('long_idling_records', [
            'report_id' => $report->id,
            'device_name' => 'TEST-DEVICE-01',
            'stay_time' => '01:45:00',
        ]);
    }

    public function test_livewire_spreadsheet_with_seconds_and_coordinates(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id])
            ->call('addRow')
            ->set('rows.0.device_name', 'GAMA-TRUCK-01')
            ->set('rows.0.imei', '987654321098765')
            ->set('rows.0.model', 'Concox AT4')
            ->set('rows.0.start_time', '12:30:21')
            ->set('rows.0.end_time', '13:45:50')
            ->set('rows.0.coordinates', '14.599512, 120.984221')
            ->assertSet('rows.0.stay_time', '01:15:29')
            ->call('saveAll');

        $this->assertDatabaseHas('long_idling_records', [
            'report_id' => $report->id,
            'device_name' => 'GAMA-TRUCK-01',
            'start_time' => '12:30:21',
            'end_time' => '13:45:50',
            'stay_time' => '01:15:29',
            'latitude' => 14.599512,
            'longitude' => 120.984221,
        ]);
    }

    public function test_image_upload_endpoint(): void
    {
        Storage::fake('public');

        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $record = LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'TEST-DEVICE',
            'imei' => '123',
            'model' => 'GPS',
        ]);

        $file = UploadedFile::fake()->image('screenshot.png');

        $response = $this->actingAs($this->user)->postJson(route('records.uploadImage', $record), [
            'image' => $file,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $record->refresh();
        $this->assertNotNull($record->image);
        Storage::disk('public')->assertExists($record->image);
    }

    public function test_upload_screenshot_for_unsaved_row(): void
    {
        Storage::fake('public');

        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $file = UploadedFile::fake()->image('screenshot.png');

        $response = $this->actingAs($this->user)->postJson(route('reports.uploadScreenshot', $report), [
            'image' => $file,
            'record_id' => 'null', // simulating string from JS for unsaved row
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $imagePath = $response->json('image');
        $this->assertNotNull($imagePath);
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_upload_screenshot_for_existing_row(): void
    {
        Storage::fake('public');

        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $record = LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'GAMA-01',
            'imei' => '123456',
            'model' => 'GPS',
        ]);

        $file = UploadedFile::fake()->image('screenshot.png');

        $response = $this->actingAs($this->user)->postJson(route('reports.uploadScreenshot', $report), [
            'image' => $file,
            'record_id' => $record->id,
        ]);

        $response->assertOk();
        $record->refresh();
        $this->assertNotNull($record->image);
        Storage::disk('public')->assertExists($record->image);
    }

    public function test_livewire_update_and_remove_row_image(): void
    {
        Storage::fake('public');

        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id])
            ->call('addRow')
            ->call('updateRowImage', 0, 'http://localhost/storage/screenshots/test.png', 'screenshots/test.png')
            ->assertSet('rows.0.image', 'screenshots/test.png')
            ->assertSet('rows.0.image_url', 'http://localhost/storage/screenshots/test.png')
            ->assertSet('rows.0.dirty', true)
            ->call('removeRowImage', 0)
            ->assertSet('rows.0.image', null)
            ->assertSet('rows.0.image_url', null);
    }

    public function test_livewire_saves_row_with_uploaded_image(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id])
            ->call('addRow')
            ->set('rows.0.device_name', 'GAMA-CAR-01')
            ->call('updateRowImage', 0, 'http://localhost/storage/test.png', 'screenshots/test.png')
            ->call('saveAll');

        $this->assertDatabaseHas('long_idling_records', [
            'report_id' => $report->id,
            'device_name' => 'GAMA-CAR-01',
            'image' => 'screenshots/test.png',
        ]);
    }

    public function test_livewire_save_all_feedback_and_dirty_state(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id])
            ->assertSee('All changes saved')
            ->call('addRow')
            ->set('rows.0.device_name', 'TEST-DEVICE-01')
            ->assertSee('Unsaved changes')
            ->call('saveAll')
            ->assertSet('savedMessage', '1 record(s) saved successfully.')
            ->assertSee('1 record(s) saved successfully.')
            ->assertSee('All changes saved');
    }

    public function test_can_sort_records_by_most_and_less_stay_time(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'DEVICE-A',
            'imei' => '111111111111111',
            'model' => 'Model-1',
            'stay_time' => '00:45:00',
            'sort_order' => 0,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'DEVICE-B',
            'imei' => '222222222222222',
            'model' => 'Model-2',
            'stay_time' => '02:30:00',
            'sort_order' => 1,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'DEVICE-C',
            'imei' => '333333333333333',
            'model' => 'Model-3',
            'stay_time' => '01:15:00',
            'sort_order' => 2,
        ]);

        // Most Stay Time (Highest to Lowest)
        $testDesc = Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id])
            ->call('setSort', 'stay_time_desc');

        $rowsDesc = $testDesc->viewData('filteredRows');
        $this->assertSame('02:30:00', $rowsDesc[0]['stay_time']);
        $this->assertSame('DEVICE-B', $rowsDesc[0]['device_name']);
        $this->assertSame('01:15:00', $rowsDesc[1]['stay_time']);
        $this->assertSame('DEVICE-C', $rowsDesc[1]['device_name']);
        $this->assertSame('00:45:00', $rowsDesc[2]['stay_time']);
        $this->assertSame('DEVICE-A', $rowsDesc[2]['device_name']);

        // Less Stay Time (Lowest to Highest)
        $testAsc = Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id])
            ->call('setSort', 'stay_time_asc');

        $rowsAsc = $testAsc->viewData('filteredRows');
        $this->assertSame('00:45:00', $rowsAsc[0]['stay_time']);
        $this->assertSame('DEVICE-A', $rowsAsc[0]['device_name']);
        $this->assertSame('01:15:00', $rowsAsc[1]['stay_time']);
        $this->assertSame('DEVICE-C', $rowsAsc[1]['device_name']);
        $this->assertSame('02:30:00', $rowsAsc[2]['stay_time']);
        $this->assertSame('DEVICE-B', $rowsAsc[2]['device_name']);
    }

    public function test_can_sort_records_alphabetically_by_device_name(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'Zebra Truck',
            'imei' => '111111111111111',
            'model' => 'Model-1',
            'stay_time' => '01:00:00',
            'sort_order' => 0,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'Alpha Van',
            'imei' => '222222222222222',
            'model' => 'Model-2',
            'stay_time' => '01:00:00',
            'sort_order' => 1,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'Bravo Pickup',
            'imei' => '333333333333333',
            'model' => 'Model-3',
            'stay_time' => '01:00:00',
            'sort_order' => 2,
        ]);

        // A -> Z
        $testAsc = Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id])
            ->call('setSort', 'device_asc');

        $rowsAsc = $testAsc->viewData('filteredRows');
        $this->assertSame('Alpha Van', $rowsAsc[0]['device_name']);
        $this->assertSame('Bravo Pickup', $rowsAsc[1]['device_name']);
        $this->assertSame('Zebra Truck', $rowsAsc[2]['device_name']);

        // Z -> A
        $testDesc = Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id])
            ->call('setSort', 'device_desc');

        $rowsDesc = $testDesc->viewData('filteredRows');
        $this->assertSame('Zebra Truck', $rowsDesc[0]['device_name']);
        $this->assertSame('Bravo Pickup', $rowsDesc[1]['device_name']);
        $this->assertSame('Alpha Van', $rowsDesc[2]['device_name']);
    }

    public function test_toggle_sort_cycles_correctly(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id])
            ->assertSet('sortBy', 'default')
            ->call('toggleSort', 'stay_time')
            ->assertSet('sortBy', 'stay_time_desc')
            ->call('toggleSort', 'stay_time')
            ->assertSet('sortBy', 'stay_time_asc')
            ->call('toggleSort', 'stay_time')
            ->assertSet('sortBy', 'default')
            ->call('toggleSort', 'device_name')
            ->assertSet('sortBy', 'device_asc')
            ->call('toggleSort', 'device_name')
            ->assertSet('sortBy', 'device_desc')
            ->call('toggleSort', 'device_name')
            ->assertSet('sortBy', 'default');
    }

    public function test_duration_filter_filters_rows(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'SHORT-IDLE',
            'imei' => '111111111111111',
            'model' => 'Model-1',
            'stay_time' => '00:30:00',
            'sort_order' => 0,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'MEDIUM-IDLE',
            'imei' => '222222222222222',
            'model' => 'Model-2',
            'stay_time' => '01:30:00',
            'sort_order' => 1,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'LONG-IDLE',
            'imei' => '333333333333333',
            'model' => 'Model-3',
            'stay_time' => '02:45:00',
            'sort_order' => 2,
        ]);

        $test = Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id]);

        $this->assertCount(3, $test->viewData('filteredRows'));

        // >= 1 Hour
        $test->call('setFilterDuration', '1h');
        $this->assertCount(2, $test->viewData('filteredRows'));

        // >= 2 Hours
        $test->call('setFilterDuration', '2h');
        $this->assertCount(1, $test->viewData('filteredRows'));
        $this->assertSame('LONG-IDLE', $test->viewData('filteredRows')[0]['device_name']);
    }

    public function test_can_generate_pdf_and_excel_with_sort_parameter(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'B-TRUCK',
            'imei' => '111111111111111',
            'model' => 'Model-1',
            'stay_time' => '00:30:00',
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'A-TRUCK',
            'imei' => '222222222222222',
            'model' => 'Model-2',
            'stay_time' => '02:30:00',
        ]);

        // PDF with sort
        $pdfResponse = $this->actingAs($this->user)->get(route('reports.generatePdf', [$report, 'sort' => 'stay_time_desc']));
        $pdfResponse->assertOk();
        $this->assertEquals('application/pdf', $pdfResponse->headers->get('content-type'));

        // Excel with sort
        $excelResponse = $this->actingAs($this->user)->get(route('reports.exportExcel', [$report, 'sort' => 'device_asc']));
        $excelResponse->assertOk();
    }

    public function test_can_generate_pdf_for_selected_ids_only(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        $rec1 = LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'BT-01',
            'imei' => '111111111111111',
            'model' => 'Model-1',
            'stay_time' => '00:30:00',
        ]);

        $rec2 = LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'BT-06',
            'imei' => '222222222222222',
            'model' => 'Model-2',
            'stay_time' => '02:30:00',
        ]);

        $rec3 = LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'BT-07',
            'imei' => '333333333333333',
            'model' => 'Model-3',
            'stay_time' => '01:00:00',
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.generatePdf', [
            'report' => $report,
            'ids' => "{$rec2->id},{$rec3->id}",
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('Long-Idling-Report-'.$report->report_date->format('Y-m-d').'-Selected-2.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_can_generate_pdf_for_specific_device_only(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'BT-01',
            'imei' => '111111111111111',
            'model' => 'Model-1',
            'stay_time' => '00:30:00',
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'BT-06',
            'imei' => '222222222222222',
            'model' => 'Model-2',
            'stay_time' => '02:30:00',
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.generatePdf', [
            'report' => $report,
            'device' => 'BT-06',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('BT-06', (string) $response->headers->get('content-disposition'));
    }

    public function test_livewire_can_filter_by_device_name_dropdown(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'BT-01',
            'imei' => '111111111111111',
            'model' => 'Model-1',
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'BT-06',
            'imei' => '222222222222222',
            'model' => 'Model-2',
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'BT-06',
            'imei' => '333333333333333',
            'model' => 'Model-2',
        ]);

        $test = Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id]);

        $this->assertCount(3, $test->viewData('filteredRows'));
        $this->assertEquals(['BT-01' => 1, 'BT-06' => 2], $test->get('deviceList'));

        $test->set('filterDevice', 'BT-06');
        $this->assertCount(2, $test->viewData('filteredRows'));
        $this->assertSame('BT-06', $test->viewData('filteredRows')[0]['device_name']);
        $this->assertSame('BT-06', $test->viewData('filteredRows')[1]['device_name']);
    }

    public function test_livewire_can_select_checklist_and_build_pdf_url(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $rec1 = LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'BT-01',
            'imei' => '111111111111111',
            'model' => 'Model-1',
        ]);

        $rec2 = LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'BT-06',
            'imei' => '222222222222222',
            'model' => 'Model-2',
        ]);

        $test = Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, ['reportId' => $report->id]);

        // Default pdfUrl has no ids
        $this->assertStringNotContainsString('ids=', $test->get('pdfUrl'));

        // Select row 2
        $test->set('selectedIds', [$rec2->id]);
        $this->assertStringContainsString('ids='.$rec2->id, $test->get('pdfUrl'));

        // Select all filtered
        $test->call('selectAllFiltered');
        $this->assertEquals([$rec1->id, $rec2->id], $test->get('selectedIds'));
        $this->assertTrue($test->get('selectAll'));
        $this->assertStringContainsString('ids='.$rec1->id.'%2C'.$rec2->id, $test->get('pdfUrl'));

        // Clear selection
        $test->call('clearSelection');
        $this->assertEmpty($test->get('selectedIds'));
        $this->assertFalse($test->get('selectAll'));
    }

    public function test_authenticated_user_can_view_reports_range(): void
    {
        Report::create([
            'report_type' => 'long_idling',
            'report_date' => '2026-09-22',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        Report::create([
            'report_type' => 'long_idling',
            'report_date' => '2026-09-24',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.range', [
            'start_date' => '2026-09-22',
            'end_date' => '2026-09-24',
        ]));

        $response->assertOk();
        $response->assertSee('Long Idling Multi-Date Explorer');
    }

    public function test_livewire_can_load_records_across_multiple_dates_in_range_mode(): void
    {
        $report1 = Report::create([
            'report_type' => 'long_idling',
            'report_date' => '2026-09-22',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        $rec1 = LongIdlingRecord::create([
            'report_id' => $report1->id,
            'device_name' => 'BT-06',
            'imei' => '111111111111111',
            'model' => 'Model-1',
            'driver_name' => 'Driver John',
            'stay_time' => '01:30:00',
        ]);

        $report2 = Report::create([
            'report_type' => 'long_idling',
            'report_date' => '2026-09-24',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        $rec2 = LongIdlingRecord::create([
            'report_id' => $report2->id,
            'device_name' => 'BT-08',
            'imei' => '222222222222222',
            'model' => 'Model-2',
            'driver_name' => 'Driver Alex',
            'stay_time' => '02:00:00',
        ]);

        $test = Livewire::actingAs($this->user)
            ->test(LongIdlingTable::class, [
                'startDate' => '2026-09-22',
                'endDate' => '2026-09-24',
                'dateMode' => 'range',
            ]);

        $test->assertCount('rows', 2);
        $this->assertTrue($test->get('hasMultipleDates'));

        // Checklist selection across different dates
        $test->set('selectedIds', [$rec1->id, $rec2->id]);
        $this->assertStringContainsString('across 2 dates', $test->get('selectedDatesSummary'));
        $this->assertStringContainsString('ids='.$rec1->id.'%2C'.$rec2->id, $test->get('pdfUrl'));
    }

    public function test_can_generate_pdf_with_selected_ids_across_different_dates(): void
    {
        $report1 = Report::create([
            'report_type' => 'long_idling',
            'report_date' => '2026-09-22',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        $rec1 = LongIdlingRecord::create([
            'report_id' => $report1->id,
            'device_name' => 'BT-06',
            'imei' => '111111111111111',
            'model' => 'Model-1',
            'driver_name' => 'Driver John',
            'stay_time' => '01:30:00',
        ]);

        $report2 = Report::create([
            'report_type' => 'long_idling',
            'report_date' => '2026-09-24',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        $rec2 = LongIdlingRecord::create([
            'report_id' => $report2->id,
            'device_name' => 'BT-08',
            'imei' => '222222222222222',
            'model' => 'Model-2',
            'driver_name' => 'Driver Alex',
            'stay_time' => '02:00:00',
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.generateRangePdf', [
            'ids' => "{$rec1->id},{$rec2->id}",
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('Long-Idling-Report-2026-09-22-to-2026-09-24-Selected-2.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_can_generate_pdf_for_date_range(): void
    {
        $report1 = Report::create([
            'report_type' => 'long_idling',
            'report_date' => '2026-09-22',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report1->id,
            'device_name' => 'BT-06',
            'imei' => '111111111111111',
            'model' => 'Model-1',
            'driver_name' => 'Driver John',
            'stay_time' => '01:30:00',
        ]);

        $report2 = Report::create([
            'report_type' => 'long_idling',
            'report_date' => '2026-09-24',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report2->id,
            'device_name' => 'BT-08',
            'imei' => '222222222222222',
            'model' => 'Model-2',
            'driver_name' => 'Driver Alex',
            'stay_time' => '02:00:00',
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.generateRangePdf', [
            'start_date' => '2026-09-22',
            'end_date' => '2026-09-24',
            'device' => 'BT-06',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('Long-Idling-Report-2026-09-22-to-2026-09-24-BT-06.pdf', (string) $response->headers->get('content-disposition'));
    }
}
