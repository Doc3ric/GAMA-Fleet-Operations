<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\FleetDispatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FleetDispatch extends Model
{
    /** @use HasFactory<FleetDispatchFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ON_TIME = 'ON_TIME';

    public const STATUS_DELAYED = 'DELAYED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ON_TIME,
        self::STATUS_DELAYED,
        self::STATUS_CANCELLED,
    ];

    public const SOURCE_AUTHORIZED_DISPATCH = 'authorized_dispatch';

    public const SOURCE_DRIVER_SUBMITTED = 'driver_submitted';

    public const SOURCE_GPS_MOVEMENT = 'gps_movement';

    /** @var list<string> */
    protected $fillable = [
        'trip_number',
        'advanced_itinerary_id',
        'vehicle_id',
        'driver_name',
        'trip_date',
        'starting_point',
        'destination',
        'planned_route',
        'planned_distance_km',
        'planned_departure_time',
        'actual_departure_time',
        'departure_source',
        'dispatch_status',
        'delay_minutes',
        'grace_period_minutes',
        'notes',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'trip_date' => 'date',
        'planned_distance_km' => 'float',
        'planned_departure_time' => 'datetime',
        'actual_departure_time' => 'datetime',
        'delay_minutes' => 'integer',
        'grace_period_minutes' => 'integer',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(AdvancedItinerary::class, 'advanced_itinerary_id');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(MileageReconciliation::class, 'dispatch_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ReconciliationException::class, 'dispatch_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(MileageAuditLog::class, 'dispatch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOnTime(): bool
    {
        return $this->dispatch_status === self::STATUS_ON_TIME;
    }

    public function isDelayed(): bool
    {
        return $this->dispatch_status === self::STATUS_DELAYED;
    }

    public function isPending(): bool
    {
        return $this->dispatch_status === self::STATUS_PENDING;
    }

    public function isCancelled(): bool
    {
        return $this->dispatch_status === self::STATUS_CANCELLED;
    }

    /**
     * Determine if trip is eligible for On-Time Dispatch Rate calculation.
     * Business rule: Exclude cancelled trips and pending trips whose scheduled departure is still in the future.
     */
    public function isEligibleForPunctuality(): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        if ($this->actual_departure_time !== null) {
            return true;
        }

        // If not departed yet, it is only eligible once its planned departure time has passed (overdue)
        return $this->planned_departure_time && Carbon::now()->greaterThanOrEqualTo($this->planned_departure_time);
    }

    /**
     * Compute and update dispatch status based on planned departure, actual departure, and grace period.
     */
    public function recalculateStatus(?Carbon $now = null): void
    {
        $now = $now ?? Carbon::now();

        if ($this->dispatch_status === self::STATUS_CANCELLED) {
            return;
        }

        if (! $this->actual_departure_time) {
            if ($this->planned_departure_time && $now->greaterThanOrEqualTo($this->planned_departure_time)) {
                $diffMinutes = (int) $this->planned_departure_time->diffInMinutes($now, false);
                $this->delay_minutes = max(0, $diffMinutes);
                $this->dispatch_status = self::STATUS_DELAYED;
            } else {
                $this->delay_minutes = 0;
                $this->dispatch_status = self::STATUS_PENDING;
            }

            return;
        }

        $grace = $this->grace_period_minutes ?? (int) config('fleet_reconciliation.dispatch_grace_period_minutes', 15);
        $diffMinutes = (int) $this->planned_departure_time->diffInMinutes($this->actual_departure_time, false);

        if ($diffMinutes <= $grace) {
            $this->delay_minutes = max(0, $diffMinutes);
            $this->dispatch_status = self::STATUS_ON_TIME;
        } else {
            $this->delay_minutes = max(0, $diffMinutes);
            $this->dispatch_status = self::STATUS_DELAYED;
        }
    }

    /**
     * Scope for eligible trips in punctuality calculation.
     *
     * @param  Builder<FleetDispatch>  $query
     */
    public function scopeEligibleForPunctuality(Builder $query): void
    {
        $query->where('dispatch_status', '!=', self::STATUS_CANCELLED)
            ->where(function (Builder $q) {
                $q->whereNotNull('actual_departure_time')
                    ->orWhere('planned_departure_time', '<=', now());
            });
    }

    /**
     * Scope for on-time departures.
     *
     * @param  Builder<FleetDispatch>  $query
     */
    public function scopeOnTime(Builder $query): void
    {
        $query->where('dispatch_status', self::STATUS_ON_TIME);
    }

    /**
     * Scope for delayed departures.
     *
     * @param  Builder<FleetDispatch>  $query
     */
    public function scopeDelayed(Builder $query): void
    {
        $query->where('dispatch_status', self::STATUS_DELAYED);
    }

    /**
     * Scope for pending departures.
     *
     * @param  Builder<FleetDispatch>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('dispatch_status', self::STATUS_PENDING);
    }
}
