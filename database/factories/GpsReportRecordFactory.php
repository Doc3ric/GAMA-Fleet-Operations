<?php

namespace Database\Factories;

use App\Models\GpsReportImport;
use App\Models\GpsReportRecord;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GpsReportRecord>
 */
class GpsReportRecordFactory extends Factory
{
    protected $model = GpsReportRecord::class;

    public function definition(): array
    {
        $startTime = Carbon::today()->setHour(8)->setMinute(0);
        $endTime = Carbon::today()->setHour(12)->setMinute(30);

        return [
            'gps_report_import_id' => GpsReportImport::factory(),
            'device_name' => 'BT-01',
            'imei' => fake()->numerify('86821004#######'),
            'vehicle_id' => Vehicle::factory(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'run_distance_km' => 124.5,
            'start_odometer' => 50000.0,
            'end_odometer' => 50124.5,
            'raw_data' => ['source' => 'TrackSolid Pro'],
            'match_status' => GpsReportRecord::STATUS_MATCHED,
            'mileage_reconciliation_id' => null,
        ];
    }
}
