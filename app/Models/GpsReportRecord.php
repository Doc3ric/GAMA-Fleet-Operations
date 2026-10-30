<?php

namespace App\Models;

use Database\Factories\GpsReportRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsReportRecord extends Model
{
    /** @use HasFactory<GpsReportRecordFactory> */
    use HasFactory;

    public const STATUS_MATCHED = 'MATCHED';

    public const STATUS_AMBIGUOUS = 'AMBIGUOUS';

    public const STATUS_UNMATCHED = 'UNMATCHED';

    public const STATUS_DUPLICATE = 'DUPLICATE';

    /** @var list<string> */
    protected $fillable = [
        'gps_report_import_id',
        'device_name',
        'imei',
        'vehicle_id',
        'start_time',
        'end_time',
        'run_distance_km',
        'start_odometer',
        'end_odometer',
        'raw_data',
        'match_status',
        'mileage_reconciliation_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'run_distance_km' => 'float',
        'start_odometer' => 'float',
        'end_odometer' => 'float',
        'raw_data' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(GpsReportImport::class, 'gps_report_import_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(MileageReconciliation::class, 'mileage_reconciliation_id');
    }
}
