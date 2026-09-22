<?php

namespace Tests\Feature;

use App\Models\LongIdlingRecord;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LongIdlingImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_download_import_template(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.downloadTemplate'));

        $response->assertOk();
        $this->assertTrue(str_contains(
            $response->headers->get('content-disposition', ''),
            'Long-Idling-Import-Template.xlsx'
        ));
    }

    public function test_import_csv_data_with_template_headers(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $csvContent = "Device Name,IMEI,Model,State,Start time,End Time,Coordinates,Address,Stay time\n";
        $csvContent .= "GAMA-01,865968052144112,JM01,Idling,18:41:28,20:27:16,\"8.472287, 124.654321\",\"Lapasan, Cagayan de Oro\",01:45:48\n";
        $csvContent .= "GAMA-02,865968052144999,JM02,Stopped,14:00:00,15:15:30,\"14.5995, 120.9842\",\"Manila, Philippines\",01:15:30\n";

        $file = UploadedFile::fake()->createWithContent('idling_report.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('reports.importData', $report), [
            'file' => $file,
            'mode' => 'append',
        ]);

        $response->assertRedirect(route('reports.show', $report));
        $response->assertSessionHas('success', 'Successfully imported 2 record(s).');

        $this->assertDatabaseHas('long_idling_records', [
            'report_id' => $report->id,
            'device_name' => 'GAMA-01',
            'imei' => '865968052144112',
            'model' => 'JM01',
            'state' => 'Idling',
            'start_time' => '18:41:28',
            'end_time' => '20:27:16',
            'stay_time' => '01:45:48',
            'latitude' => 8.472287,
            'longitude' => 124.654321,
            'address' => 'Lapasan, Cagayan de Oro',
        ]);

        $this->assertDatabaseHas('long_idling_records', [
            'report_id' => $report->id,
            'device_name' => 'GAMA-02',
            'state' => 'Stopped',
            'stay_time' => '01:15:30',
        ]);
    }

    public function test_import_auto_calculates_stay_time_if_omitted(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $csvContent = "Device Name,IMEI,Model,State,Start time,End Time,Coordinates,Address,Stay time\n";
        $csvContent .= "GAMA-03,123456789012345,GPS,Idling,10:00:00,11:30:15,\"8.472287, 124.654321\",\"Lapasan\",\n";

        $file = UploadedFile::fake()->createWithContent('no_stay_time.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('reports.importData', $report), [
            'file' => $file,
            'mode' => 'append',
        ]);

        $response->assertRedirect(route('reports.show', $report));
        $this->assertDatabaseHas('long_idling_records', [
            'report_id' => $report->id,
            'device_name' => 'GAMA-03',
            'stay_time' => '01:30:15',
        ]);
    }

    public function test_import_replace_mode_clears_previous_records(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        LongIdlingRecord::create([
            'report_id' => $report->id,
            'device_name' => 'OLD-RECORD',
            'imei' => '111111111111111',
            'model' => 'OLD',
        ]);

        $this->assertEquals(1, $report->longIdlingRecords()->count());

        $csvContent = "Device Name,IMEI,Model,State,Start time,End Time,Coordinates,Address,Stay time\n";
        $csvContent .= "NEW-RECORD,999999999999999,NEW,Idling,12:00:00,13:00:00,\"8.472287, 124.654321\",\"CDO\",01:00:00\n";

        $file = UploadedFile::fake()->createWithContent('replace_report.csv', $csvContent);

        $response = $this->actingAs($this->user)->post(route('reports.importData', $report), [
            'file' => $file,
            'mode' => 'replace',
        ]);

        $response->assertRedirect(route('reports.show', $report));
        $this->assertDatabaseMissing('long_idling_records', [
            'report_id' => $report->id,
            'device_name' => 'OLD-RECORD',
        ]);
        $this->assertDatabaseHas('long_idling_records', [
            'report_id' => $report->id,
            'device_name' => 'NEW-RECORD',
        ]);
        $this->assertEquals(1, $report->longIdlingRecords()->count());
    }

    public function test_import_rejects_invalid_file_type(): void
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->user)->post(route('reports.importData', $report), [
            'file' => $file,
            'mode' => 'append',
        ]);

        $response->assertSessionHas('error');
    }
}
