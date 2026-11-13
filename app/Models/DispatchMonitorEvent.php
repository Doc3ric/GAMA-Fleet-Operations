<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchMonitorEvent extends Model
{
    use HasFactory;

    public const EVENT_ROUTE_DEVIATION = 'ROUTE_DEVIATION';

    public const EVENT_MISSED_DEPARTURE = 'MISSED_DEPARTURE';

    public const EVENT_UNEXPECTED_STOP = 'UNEXPECTED_STOP';

    public const EVENT_DELAYED_ARRIVAL = 'DELAYED_ARRIVAL';

    public const EVENT_GPS_UNAVAILABLE = 'GPS_UNAVAILABLE';

    public const SEVERITY_INFO = 'INFO';

    public const SEVERITY_WARNING = 'WARNING';

    public const SEVERITY_DANGER = 'DANGER';

    public const STATUS_POSSIBLE = 'POSSIBLE';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_RESOLVED = 'RESOLVED';

    public const STATUS_WAIVED = 'WAIVED';

    /** @var list<string> */
    protected $fillable = [
        'dispatch_id',
        'vehicle_id',
        'event_type',
        'severity',
        'status',
        'latitude',
        'longitude',
        'speed_kmh',
        'deviation_meters',
        'duration_minutes',
        'description',
        'metadata',
        'resolved_at',
        'resolved_by',
        'resolution_remarks',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'speed_kmh' => 'float',
        'deviation_meters' => 'float',
        'duration_minutes' => 'integer',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(FleetDispatch::class, 'dispatch_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isPossible(): bool
    {
        return $this->status === self::STATUS_POSSIBLE;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
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
            'resolved_at' => Carbon::now(),
            'resolution_remarks' => $remarks,
        ]);
    }

    public function waive(int $userId, ?string $remarks = null): void
    {
        $this->update([
            'status' => self::STATUS_WAIVED,
            'resolved_by' => $userId,
            'resolved_at' => Carbon::now(),
            'resolution_remarks' => $remarks,
        ]);
    }

    /**
     * Scope for active events (possible or confirmed).
     *
     * @param  Builder<DispatchMonitorEvent>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_POSSIBLE, self::STATUS_CONFIRMED]);
    }

    /**
     * Scope for confirmed events only.
     *
     * @param  Builder<DispatchMonitorEvent>  $query
     */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope for possible events only.
     *
     * @param  Builder<DispatchMonitorEvent>  $query
     */
    public function scopePossible(Builder $query): void
    {
        $query->where('status', self::STATUS_POSSIBLE);
    }
}
