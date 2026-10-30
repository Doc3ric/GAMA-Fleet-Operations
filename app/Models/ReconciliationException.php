<?php

namespace App\Models;

use Database\Factories\ReconciliationExceptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationException extends Model
{
    /** @use HasFactory<ReconciliationExceptionFactory> */
    use HasFactory;

    public const SEVERITY_INFO = 'INFO';

    public const SEVERITY_WARNING = 'WARNING';

    public const SEVERITY_DANGER = 'DANGER';

    public const STATUS_OPEN = 'OPEN';

    public const STATUS_RESOLVED = 'RESOLVED';

    public const STATUS_WAIVED = 'WAIVED';

    public const TYPE_MISSING_ODOMETER = 'MISSING_ODOMETER';

    public const TYPE_NEGATIVE_ODOMETER = 'NEGATIVE_ODOMETER';

    public const TYPE_MISSING_EVIDENCE = 'MISSING_EVIDENCE';

    public const TYPE_VARIANCE_THRESHOLD = 'VARIANCE_THRESHOLD';

    public const TYPE_GPS_DISCREPANCY = 'GPS_DISCREPANCY';

    public const TYPE_MISSING_GPS_REPORT = 'MISSING_GPS_REPORT';

    public const TYPE_UNREPORTED_DEPARTURE = 'UNREPORTED_DEPARTURE';

    public const TYPE_AMBIGUOUS_GPS_MATCH = 'AMBIGUOUS_GPS_MATCH';

    public const TYPE_UNMATCHED_DEVICE = 'UNMATCHED_DEVICE';

    public const TYPE_DUPLICATE_RECORD = 'DUPLICATE_RECORD';

    /** @var list<string> */
    protected $fillable = [
        'mileage_reconciliation_id',
        'dispatch_id',
        'exception_type',
        'severity',
        'description',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_remarks',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(MileageReconciliation::class, 'mileage_reconciliation_id');
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(FleetDispatch::class, 'dispatch_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isWaived(): bool
    {
        return $this->status === self::STATUS_WAIVED;
    }

    public function resolve(int $userId, ?string $remarks = null): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_remarks' => $remarks,
        ]);
    }

    public function waive(int $userId, ?string $remarks = null): void
    {
        $this->update([
            'status' => self::STATUS_WAIVED,
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_remarks' => $remarks,
        ]);
    }

    /**
     * @param  Builder<ReconciliationException>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN);
    }
}
