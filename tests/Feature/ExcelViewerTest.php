<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\ExcelViewer;
use App\Models\Device;
use App\Models\DriverTrip;
use App\Models\FuelConsumptionTest;
use App\Models\Report;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExcelViewerTest extends TestCase
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

    protected function getXlsxFixture(): UploadedFile
    {
        $fixturePath = base_path('tests/Fixtures/test_sample.xlsx');
        $this->assertFileExists($fixturePath, 'XLSX fixture must exist for test.');

        return UploadedFile::fake()->createWithContent(
            'test_sample.xlsx',
            file_get_contents($fixturePath)
        );
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('excel-viewer.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_excel_viewer_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('excel-viewer.index'));
        $response->assertOk();
        $response->assertSee('Excel Viewer');
        $response->assertSeeLivewire(ExcelViewer::class);
    }

    public function test_invalid_file_extension_is_rejected(): void
    {
        $invalidFile = UploadedFile::fake()->create('malicious.php', 100, 'text/x-php');

        Livewire::actingAs($this->user)
            ->test(ExcelViewer::class)
            ->set('file', $invalidFile)
            ->assertHasErrors(['file' => 'extensions']);
    }

    public function test_corrupted_file_is_handled_gracefully(): void
    {
        $corruptFile = UploadedFile::fake()->create('corrupted.xlsb', 50, 'application/octet-stream');

        Livewire::actingAs($this->user)
            ->test(ExcelViewer::class)
            ->set('file', $corruptFile)
            ->assertSet('rows', [])
            ->assertSee('Failed to process spreadsheet');
    }

    public function test_real_xlsb_file_upload_and_worksheet_detection(): void
    {
        $file = $this->getXlsbFixture();

        $component = Livewire::actingAs($this->user)
            ->test(ExcelViewer::class)
            ->set('file', $file)
            ->assertHasNoErrors()
            ->assertSet('originalName', 'Sept_19 2026 EV 10 Itinerary.xlsb')
            ->assertSet('extension', 'xlsb')
            ->assertCount('sheets', 6);

        // Check that all 6 sheet names are detected
        $sheets = $component->get('sheets');
        $sheetNames = array_column($sheets, 'name');
        $this->assertContains('DIESEL', $sheetNames);
        $this->assertContains('WEEKLY', $sheetNames);
        $this->assertContains('ITINERARY', $sheetNames);
        $this->assertContains('FIS', $sheetNames);
        $this->assertContains('Sheet1', $sheetNames);
        $this->assertContains('Sheet3', $sheetNames);
    }

    public function test_real_xlsb_worksheet_data_can_be_read_and_viewed(): void
    {
        $file = $this->getXlsbFixture();

        $component = Livewire::actingAs($this->user)
            ->test(ExcelViewer::class)
            ->set('file', $file);

        // Default sheet is DIESEL
        $component->assertSee('DIESEL MONITORING REPORT');

        // Select ITINERARY sheet
        $sheets = $component->get('sheets');
        $itinerarySheet = collect($sheets)->firstWhere('name', 'ITINERARY');
        $this->assertNotNull($itinerarySheet);

        $component->call('selectSheet', $itinerarySheet['id'])
            ->assertSee('WEEKLY ITINERARY REPORT')
            ->assertSee('EV 10')
            ->assertSee('NDY 5123');
    }

    public function test_xlsx_file_upload_and_worksheet_detection(): void
    {
        $file = $this->getXlsxFixture();

        $component = Livewire::actingAs($this->user)
            ->test(ExcelViewer::class)
            ->set('file', $file)
            ->assertHasNoErrors()
            ->assertSet('originalName', 'test_sample.xlsx')
            ->assertSet('extension', 'xlsx')
            ->assertCount('sheets', 2);

        $sheets = $component->get('sheets');
        $sheetNames = array_column($sheets, 'name');
        $this->assertContains('Summary', $sheetNames);
        $this->assertContains('Vehicle Data', $sheetNames);

        // Verify summary data is visible
        $component->assertSee('Total Vehicles');
        $component->assertSee('42');

        // Switch to Vehicle Data sheet
        $vehicleSheet = collect($sheets)->firstWhere('name', 'Vehicle Data');
        $component->call('selectSheet', $vehicleSheet['id'])
            ->assertSee('Toyota Hilux')
            ->assertSee('ABC-123')
            ->assertSee('Operational');
    }

    public function test_empty_cells_preserve_column_alignment(): void
    {
        $file = $this->getXlsbFixture();

        $component = Livewire::actingAs($this->user)
            ->test(ExcelViewer::class)
            ->set('file', $file);

        $rows = $component->get('rows');
        $this->assertNotEmpty($rows);

        // Verify all rows have the identical number of columns
        $expectedCols = count($rows[0]);
        foreach ($rows as $row) {
            $this->assertCount($expectedCols, $row, 'Every row must have the exact same column count to maintain tabular alignment.');
        }
    }

    public function test_search_filters_rows_across_columns(): void
    {
        $file = $this->getXlsbFixture();

        $component = Livewire::actingAs($this->user)
            ->test(ExcelViewer::class)
            ->set('file', $file);

        // Select ITINERARY sheet
        $sheets = $component->get('sheets');
        $itinerarySheet = collect($sheets)->firstWhere('name', 'ITINERARY');
        $component->call('selectSheet', $itinerarySheet['id']);

        // Search for specific plate number
        $component->set('search', 'NDY 5123')
            ->assertSee('NDY 5123')
            ->assertDontSee('TANKER REFUEL');

        // Search for non-existent text
        $component->set('search', 'NON_EXISTENT_SEARCH_STRING_XYZ')
            ->assertSee('No rows match your search');
    }

    public function test_pagination_controls_and_page_switching(): void
    {
        $file = $this->getXlsbFixture();

        $component = Livewire::actingAs($this->user)
            ->test(ExcelViewer::class)
            ->set('file', $file);

        // Set perPage to 10
        $component->set('perPage', 10)
            ->assertSet('page', 1);

        // Navigate to page 2
        $component->call('gotoPage', 2)
            ->assertSet('page', 2);

        // Previous page
        $component->call('previousPage')
            ->assertSet('page', 1);
    }

    public function test_authenticated_user_can_download_original_workbook(): void
    {
        $file = $this->getXlsbFixture();

        $component = Livewire::actingAs($this->user)
            ->test(ExcelViewer::class)
            ->set('file', $file);

        $fileId = $component->get('fileId');
        $this->assertNotNull($fileId);

        $response = $this->actingAs($this->user)->get(route('excel-viewer.download', $fileId));
        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename="Sept_19 2026 EV 10 Itinerary.xlsb"');
    }

    public function test_unauthorized_user_cannot_download_other_users_file(): void
    {
        $otherUser = User::factory()->create();

        // Create metadata belonging to otherUser
        $fileId = 'test-file-id-123';
        Storage::disk('local')->put("excel-viewer/{$fileId}.json", json_encode([
            'id' => $fileId,
            'file_name' => "{$fileId}.xlsb",
            'original_name' => 'private_report.xlsb',
            'extension' => 'xlsb',
            'uploaded_by' => $otherUser->id,
        ]));
        Storage::disk('local')->put("excel-viewer/{$fileId}.xlsb", 'dummy content');

        // Current user tries to download
        $response = $this->actingAs($this->user)->get(route('excel-viewer.download', $fileId));
        $response->assertForbidden();
    }

    public function test_downloading_non_existent_file_returns_404(): void
    {
        $response = $this->actingAs($this->user)->get(route('excel-viewer.download', 'non-existent-uuid'));
        $response->assertNotFound();
    }

    public function test_existing_fleet_database_records_are_not_modified(): void
    {
        $vehiclesCount = Vehicle::count();
        $tripsCount = DriverTrip::count();
        $reportsCount = Report::count();
        $devicesCount = Device::count();
        $fuelTestsCount = FuelConsumptionTest::count();

        $file = $this->getXlsbFixture();

        Livewire::actingAs($this->user)
            ->test(ExcelViewer::class)
            ->set('file', $file)
            ->call('selectSheet', 'xl/worksheets/sheet3.bin')
            ->set('search', 'EV 10')
            ->call('clearFile');

        $this->assertEquals($vehiclesCount, Vehicle::count(), 'Vehicles table must not be modified.');
        $this->assertEquals($tripsCount, DriverTrip::count(), 'Driver trips table must not be modified.');
        $this->assertEquals($reportsCount, Report::count(), 'Reports table must not be modified.');
        $this->assertEquals($devicesCount, Device::count(), 'Devices table must not be modified.');
        $this->assertEquals($fuelTestsCount, FuelConsumptionTest::count(), 'Fuel tests table must not be modified.');
    }
}
