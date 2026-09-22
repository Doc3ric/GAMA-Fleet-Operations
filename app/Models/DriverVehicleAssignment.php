<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\DriverVehicleAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class DriverVehicleAssignment extends Model
{
    /** @use HasFactory<DriverVehicleAssignmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'assigned_from',
        'assigned_until',
        'notes',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'assigned_from' => 'date',
        'assigned_until' => 'date',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function user(): BelongsTo
    {
        return $this->driver();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function driverTrips(): HasMany
    {
        return $this->hasMany(DriverTrip::class, 'driver_vehicle_assignment_id');
    }

    /**
     * Scope to active assignments on a specific date (defaults to today).
     *
     * @param  Builder<DriverVehicleAssignment>  $query
     */
    public function scopeActive(Builder $query, ?string $date = null): void
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();

        $query->whereDate('assigned_from', '<=', $targetDate)
            ->where(function (Builder $sub) use ($targetDate) {
                $sub->whereNull('assigned_until')
                    ->orWhereDate('assigned_until', '>=', $targetDate);
            });
    }

    /**
     * Scope for a specific driver.
     *
     * @param  Builder<DriverVehicleAssignment>  $query
     */
    public function scopeForDriver(Builder $query, int $driverId): void
    {
        $query->where('driver_id', $driverId);
    }

    /**
     * Scope for a specific vehicle.
     *
     * @param  Builder<DriverVehicleAssignment>  $query
     */
    public function scopeForVehicle(Builder $query, int $vehicleId): void
    {
        $query->where('vehicle_id', $vehicleId);
    }

    /**
     * Check if this assignment is active on a given date.
     */
    public function isActive(?string $date = null): bool
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();
        $from = $this->assigned_from?->toDateString();
        $until = $this->assigned_until?->toDateString();

        if (! $from || $from > $targetDate) {
            return false;
        }

        return $until === null || $until >= $targetDate;
    }

    /**
     * Check if creating or updating an assignment conflicts with existing assignments.
     */
    public static function hasConflict(
        int $driverId,
        int $vehicleId,
        string $assignedFrom,
        ?string $assignedUntil = null,
        ?int $ignoreId = null
    ): bool {
        $from = Carbon::parse($assignedFrom)->toDateString();
        $until = $assignedUntil ? Carbon::parse($assignedUntil)->toDateString() : null;

        if ($until !== null && $until < $from) {
            throw new InvalidArgumentException('assigned_until cannot be earlier than assigned_from.');
        }

        $query = static::where(function (Builder $q) use ($driverId, $vehicleId) {
            $q->where('driver_id', $driverId)
                ->orWhere('vehicle_id', $vehicleId);
        });

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $query->where(function (Builder $q) use ($from, $until) {
            if ($until !== null) {
                $q->whereDate('assigned_from', '<=', $until);
            }
            $q->where(function (Builder $inner) use ($from) {
                $inner->whereNull('assigned_until')
                    ->orWhereDate('assigned_until', '>=', $from);
            });
        });

        return $query->exists();
    }
}
