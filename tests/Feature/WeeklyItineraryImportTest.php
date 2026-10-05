<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\WeeklyItineraryTemplateExport;
use App\Models\AdvancedItinerary;
use App\Models\Device;
use App\Models\DriverTrip;
use App\Models\FuelConsumptionTest;
use App\Models\Report;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class WeeklyItineraryImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Storage::fake('local');
    }

    protected function getXlsbFixture(): UploadedFile
    {
        $fixturePath = base_path('tests/Fixtures/Sept_19 2026 EV 10 Itinerary.xlsb');
        if (! file_exists($fixturePath)) {
            $fixturePath = 'C:/Downloads/Sept_19 2026 EV 10 Itinerary.xlsb';
        }

        $this->assertFileExists($fixturePath, 'Representative XLSB fixture must exist for test.');

        return UploadedFile::fake()->createWithContent(
            'Sept_19 2026 EV 10 Itinerary.xlsb',
            file_get_contents($fixturePath)
        );
    }

    protected function createSampleXlsx(array $rows = []): UploadedFile
    {
        $fileName = 'sample_weekly_itinerary.xlsx';
        Excel::store(new WeeklyItineraryTemplateExport, $fileName, 'local');
        $fullPath = Storage::disk('local')->path($fileName);

        return UploadedFile::fake()->createWithContent(
            'Weekly_Itinerary_Report.xlsx',
            file_get_contents($fullPath)
        );
    }

    public function test_unauthenticated_user_cannot_access_import_endpoints(): void
    {
        $this->get(route('fuel-po.import.template'))->assertRedirect(route('login'));
        $this->post(route('fuel-po.import.preview'))->assertRedirect(route('login'));
        $this->post(route('fuel-po.import.confirm'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_download_blank_template(): void
    {
        $response = $this->actingAs($this->user)->get(route('fuel-po.import.template'));
        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=Weekly-Itinerary-Report-Template.xlsx');
    }

    public function test_invalid_file_extension_is_rejected(): void
    {
        $invalidFile = UploadedFile::fake()->create('malicious.php', 100, 'text/x-php');

        $response = $this->actingAs($this->user)
            ->postJson(route('fuel-po.import.preview'), [
                'file' => $invalidFile,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_corrupted_file_is_handled_gracefully(): void
    {
        $corruptFile = UploadedFile::fake()->create('corrupted.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response = $this->actingAs($this->user)
            ->postJson(route('fuel-po.import.preview'), [
                'file' => $corruptFile,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_real_xlsb_fixture_preview(): void
    {
        $vehicle = Vehicle::factory()->create([
            'equipment_code' => 'EV 10',
            'plate_number' => 'NDY 5123',
            'operator_driver' => 'Brandon Lee',
            'average_fuel_consumption' => 3.7,
            'created_by' => $this->user->id,
        ]);

        $file = $this->getXlsbFixture();

        $response = $this->actingAs($this->user)
            ->postJson(route('fuel-po.import.preview'), [
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('sheet_name', 'ITINERARY');
        $this->assertEquals(85.0, (float) $response->json('summary.total_distance'));
        $response->assertJsonPath('summary.total_legs', 1);
        $response->assertJsonPath('vehicle_match.is_matched', true);
        $response->assertJsonPath('vehicle_match.equipment_code', 'EV 10');
        $response->assertJsonPath('vehicle_match.plate_number', 'NDY 5123');
        $this->assertNotEmpty($response->json('import_token'));
    }

    public function test_xlsx_template_preview_and_confirm_import(): void
    {
        $vehicle = Vehicle::factory()->create([
            'equipment_code' => 'IV 5',
            'plate_number' => 'LAO 8559',
            'operator_driver' => 'BRANDON LEE',
            'average_fuel_consumption' => 3.7,
            'created_by' => $this->user->id,
        ]);

        $file = $this->createSampleXlsx();

        // 1. Preview
        $previewResp = $this->actingAs($this->user)
            ->postJson(route('fuel-po.import.preview'), [
                'file' => $file,
            ]);

        $previewResp->assertOk();
        $previewResp->assertJsonPath('success', true);
        $previewResp->assertJsonPath('vehicle_match.is_matched', true);
        $previewResp->assertJsonPath('header.driver_name', 'BRANDON LEE');

        $token = $previewResp->json('import_token');
        $extension = $previewResp->json('extension');

        // 2. Confirm
        $confirmResp = $this->actingAs($this->user)
            ->postJson(route('fuel-po.import.confirm'), [
                'import_token' => $token,
                'extension' => $extension,
                'vehicle_id' => $vehicle->id,
                'driver_name' => 'BRANDON LEE',
            ]);

        $confirmResp->assertOk();
        $confirmResp->assertJsonPath('success', true);
        $confirmResp->assertJsonPath('count', 1);

        // Verify database records
        $this->assertDatabaseHas('advanced_itineraries', [
            'vehicle_id' => $vehicle->id,
            'driver_name' => 'BRANDON LEE',
            'total_distance' => 9.0,
            'po_checked' => 0,
        ]);

        $itinerary = AdvancedItinerary::where('vehicle_id', $vehicle->id)->first();
        $this->assertNotNull($itinerary);
        $this->assertCount(1, $itinerary->legs);

        $leg = $itinerary->legs->first();
        $this->assertEquals(4330.0, $leg->start_odo);
        $this->assertEquals(4339.0, $leg->end_odo);
        $this->assertEquals(9.0, $leg->total_distance);
        $this->assertStringContainsString('PICKUP DRIED FISH', $leg->purpose);
    }

    public function test_threshold_fuel_liters_calculation(): void
    {
        // GAMA threshold formula check: 33 km / 3.7 km/l = 8.9189 -> 8.92 >= 0.10 threshold -> 9 Liters
        $calculated = AdvancedItinerary::calculateFuelLiters(33.0, 3.7);
        $this->assertEquals(9.0, $calculated, '33 KM with 3.7 KM/L must calculate to 9 Liters for PO.');

        // If decimal is under 0.10: 30 km / 3.7 km/l = 8.108 -> 8.11 -> 9 L
        // 8.05 L (e.g. 29.8 / 3.7 = 8.054 -> dec 0.05 < 0.10 -> floor 8 L)
        $floorCalc = AdvancedItinerary::calculateFuelLiters(29.8, 3.7);
        $this->assertEquals(8.0, $floorCalc);
    }

    public function test_unmatched_vehicle_can_be_imported_with_manual_fallback(): void
    {
        $file = $this->createSampleXlsx();

        // 1. Preview
        $previewResp = $this->actingAs($this->user)
            ->postJson(route('fuel-po.import.preview'), [
                'file' => $file,
            ]);

        $token = $previewResp->json('import_token');
        $extension = $previewResp->json('extension');

        // Confirm with manual vehicle info
        $confirmResp = $this->actingAs($this->user)
            ->postJson(route('fuel-po.import.confirm'), [
                'import_token' => $token,
                'extension' => $extension,
                'custom_equipment_code' => 'NEW-UNIT-99',
                'custom_plate_number' => 'XYZ-9999',
                'custom_average_consumption' => 4.2,
                'driver_name' => 'NEW DRIVER',
            ]);

        $confirmResp->assertOk();
        $confirmResp->assertJsonPath('success', true);

        // Verify newly created vehicle
        $this->assertDatabaseHas('vehicles', [
            'equipment_code' => 'NEW-UNIT-99',
            'plate_number' => 'XYZ-9999',
            'average_fuel_consumption' => 4.2,
        ]);
    }

    public function test_existing_fleet_tables_remain_unmodified(): void
    {
        $vehiclesCount = Vehicle::count();
        $tripsCount = DriverTrip::count();
        $reportsCount = Report::count();
        $devicesCount = Device::count();
        $fuelTestsCount = FuelConsumptionTest::count();

        $file = $this->createSampleXlsx();

        $this->actingAs($this->user)
            ->postJson(route('fuel-po.import.preview'), [
                'file' => $file,
            ]);

        $this->assertEquals($vehiclesCount, Vehicle::count());
        $this->assertEquals($tripsCount, DriverTrip::count());
        $this->assertEquals($reportsCount, Report::count());
        $this->assertEquals($devicesCount, Device::count());
        $this->assertEquals($fuelTestsCount, FuelConsumptionTest::count());
    }

    public function test_multi_date_report_creates_separate_po_records(): void
    {
        $vehicle = Vehicle::factory()->create([
            'equipment_code' => 'IV 5',
            'plate_number' => 'LAO 8559',
            'average_fuel_consumption' => 3.7,
            'created_by' => $this->user->id,
        ]);

        $customExport = new class implements FromArray, WithTitle
        {
            public function array(): array
            {
                return [
                    ['', '', '', '', 'WEEKLY ITINERARY REPORT', '', '', '', ''],
                    ['', '', '', '', '', '', '', '', ''],
                    ['NAME:', 'BRANDON LEE', '', '', '', '', '', '', ''],
                    ['FOR THE WEEK:', 'SEPTEMBER 15, 2026', '', '', '', '', '', '', ''],
                    ['PLATE NUMBER:', 'IV 5 - LAO 8559', '', '', '', '', '', '', ''],
                    ['', '', '', '', '', '', '', '', ''],
                    ['DATE', 'ORIGIN', 'START ODO', 'DESTINATION', 'END ODO', 'DISTANCE', 'TIME IN', 'TIME OUT', 'PURPOSE'],
                    ['2026-09-09', 'VHEL', 4330, 'DP BALOY', 4339, 9, '9:20 PM', '9:30 PM', 'PICKUP DRIED FISH'],
                    ['2026-09-10', 'DP BALOY', 4339, 'CAGAYAN', 4363, 24, '8:00 AM', '10:00 AM', 'DELIVER GOODS'],
                    ['TOTAL DISTANCE', 33, '', '', '', '', '', '', ''],
                ];
            }

            public function title(): string
            {
                return 'ITINERARY';
            }
        };

        $fileName = 'multi_date_itinerary.xlsx';
        Excel::store($customExport, $fileName, 'local');
        $fullPath = Storage::disk('local')->path($fileName);

        $file = UploadedFile::fake()->createWithContent('multi_date.xlsx', file_get_contents($fullPath));

        $previewResp = $this->actingAs($this->user)
            ->postJson(route('fuel-po.import.preview'), [
                'file' => $file,
            ]);

        $previewResp->assertOk();
        $previewResp->assertJsonPath('summary.total_dates', 2);
        $previewResp->assertJsonPath('summary.total_legs', 2);

        $token = $previewResp->json('import_token');
        $extension = $previewResp->json('extension');

        $confirmResp = $this->actingAs($this->user)
            ->postJson(route('fuel-po.import.confirm'), [
                'import_token' => $token,
                'extension' => $extension,
                'vehicle_id' => $vehicle->id,
            ]);

        $confirmResp->assertOk();
        $confirmResp->assertJsonPath('count', 2);

        $itineraries = AdvancedItinerary::where('vehicle_id', $vehicle->id)->orderBy('itinerary_date')->get();
        $this->assertCount(2, $itineraries);

        // Date 1: 2026-09-09, 9 KM, 3.7 KM/L -> 3 L (9 / 3.7 = 2.43 -> 3 L)
        $this->assertEquals('2026-09-09', $itineraries[0]->itinerary_date->format('Y-m-d'));
        $this->assertEquals(9.0, $itineraries[0]->total_distance);
        $this->assertEquals(3.0, $itineraries[0]->fuel_liters_required);

        // Date 2: 2026-09-10, 24 KM, 3.7 KM/L -> 7 L (24 / 3.7 = 6.49 -> 7 L)
        $this->assertEquals('2026-09-10', $itineraries[1]->itinerary_date->format('Y-m-d'));
        $this->assertEquals(24.0, $itineraries[1]->total_distance);
        $this->assertEquals(7.0, $itineraries[1]->fuel_liters_required);
    }
}
