<?php

namespace App\Models;

use Database\Factories\MileageAuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MileageAuditLog extends Model
{
    /** @use HasFactory<MileageAuditLogFactory> */
    use HasFactory;

    public const ACTION_CREATED = 'CREATED';

    public const ACTION_UPDATED = 'UPDATED';

    public const ACTION_SUBMITTED = 'SUBMITTED';

    public const ACTION_APPROVED = 'APPROVED';

    public const ACTION_REJECTED = 'REJECTED';

    public const ACTION_EVIDENCE_UPLOADED = 'EVIDENCE_UPLOADED';

    public const ACTION_GPS_LINKED = 'GPS_LINKED';

    public const ACTION_EXCEPTION_RESOLVED = 'EXCEPTION_RESOLVED';

    public const ACTION_REOPENED = 'REOPENED';

    /** @var list<string> */
    protected $fillable = [
        'mileage_reconciliation_id',
        'dispatch_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'remarks',
        'ip_address',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(MileageReconciliation::class, 'mileage_reconciliation_id');
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(FleetDispatch::class, 'dispatch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Record an audit log entry.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function record(
        string $action,
        ?int $reconciliationId = null,
        ?int $dispatchId = null,
        ?int $userId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $remarks = null,
        ?string $ipAddress = null
    ): self {
        return self::create([
            'mileage_reconciliation_id' => $reconciliationId,
            'dispatch_id' => $dispatchId,
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'remarks' => $remarks,
            'ip_address' => $ipAddress ?? request()->ip(),
        ]);
    }
}
