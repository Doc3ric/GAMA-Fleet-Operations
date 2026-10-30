<?php

namespace App\Models;

use Database\Factories\MileageReconciliationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MileageReconciliation extends Model
{
    /** @use HasFactory<MileageReconciliationFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_FOR_REVIEW = 'FOR_REVIEW';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_FOR_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'reconciliation_number',
        'dispatch_id',
        'vehicle_id',
        'trip_date',
        'is_gps_vehicle',
        'planned_distance_km',
        'starting_odometer',
        'ending_odometer',
        'actual_odometer_km',
        'gps_reported_km',
        'odometer_gps_difference_km',
        'variance_km',
        'variance_percentage',
        'status',
        'has_exceptions',
        'notes',
        'submitted_by',
        'submitted_at',
        'reviewer_id',
        'reviewed_at',
        'reviewer_remarks',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'trip_date' => 'date',
        'is_gps_vehicle' => 'boolean',
        'planned_distance_km' => 'float',
        'starting_odometer' => 'float',
        'ending_odometer' => 'float',
        'actual_odometer_km' => 'float',
        'gps_reported_km' => 'float',
        'odometer_gps_difference_km' => 'float',
        'variance_km' => 'float',
        'variance_percentage' => 'float',
        'has_exceptions' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(FleetDispatch::class, 'dispatch_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(MileageEvidence::class, 'mileage_reconciliation_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ReconciliationException::class, 'mileage_reconciliation_id');
    }

    public function openExceptions(): HasMany
    {
        return $this->hasMany(ReconciliationException::class, 'mileage_reconciliation_id')->where('status', ReconciliationException::STATUS_OPEN);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(MileageAuditLog::class, 'mileage_reconciliation_id')->latest();
    }

    public function gpsRecords(): HasMany
    {
        return $this->hasMany(GpsReportRecord::class, 'mileage_reconciliation_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isForReview(): bool
    {
        return $this->status === self::STATUS_FOR_REVIEW;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Compute actual odometer KM, variance KM, variance percentage, and GPS difference.
     * Safe against division by zero and null values.
     */
    public function computeCalculations(): void
    {
        $start = $this->starting_odometer !== null ? (float) $this->starting_odometer : 0.0;
        $end = $this->ending_odometer !== null ? (float) $this->ending_odometer : 0.0;
        $planned = $this->planned_distance_km !== null ? (float) $this->planned_distance_km : 0.0;

        $actual = round($end - $start, 2);
        $this->actual_odometer_km = $actual;

        $variance = round($actual - $planned, 2);
        $this->variance_km = $variance;

        if ($planned > 0) {
            $this->variance_percentage = round(($variance / $planned) * 100, 2);
        } else {
            $this->variance_percentage = null;
        }

        if ($this->gps_reported_km !== null) {
            $gps = (float) $this->gps_reported_km;
            $this->odometer_gps_difference_km = round(abs($actual - $gps), 2);
        } else {
            $this->odometer_gps_difference_km = null;
        }
    }

    /**
     * Scope for records with open exceptions.
     *
     * @param  Builder<MileageReconciliation>  $query
     */
    public function scopeWithExceptions(Builder $query): void
    {
        $query->where('has_exceptions', true);
    }

    /**
     * Scope for records pending review.
     *
     * @param  Builder<MileageReconciliation>  $query
     */
    public function scopePendingReview(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_FOR_REVIEW]);
    }

    /**
     * Scope for approved records.
     *
     * @param  Builder<MileageReconciliation>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', self::STATUS_APPROVED);
    }
}
