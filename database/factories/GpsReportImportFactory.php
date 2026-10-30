<?php

namespace Database\Factories;

use App\Models\GpsReportImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GpsReportImport>
 */
class GpsReportImportFactory extends Factory
{
    protected $model = GpsReportImport::class;

    public function definition(): array
    {
        return [
            'filename' => 'TrackSolid_Report_20261016.xlsx',
            'file_path' => 'gps-imports/report-'.fake()->uuid().'.xlsx',
            'file_size' => 1024 * 85,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'total_rows' => 10,
            'matched_rows' => 9,
            'unmatched_rows' => 1,
            'duplicate_rows' => 0,
            'status' => GpsReportImport::STATUS_COMMITTED,
            'import_summary' => ['processed' => 10, 'matched' => 9],
            'created_by' => User::factory(),
        ];
    }
}
