<?php

namespace App\Models;

use App\Exceptions\DriverTripException;
use Carbon\Carbon;
use Database\Factories\DriverTripFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverTrip extends Model
{
    /** @use HasFactory<DriverTripFactory> */
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const VALID_STATUSES = [
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'driver_id',
        'vehicle_id',
        'driver_vehicle_assignment_id',
        'trip_date',
        'time_in',
        'time_out',
        'origin_latitude',
        'origin_longitude',
        'origin_accuracy',
        'origin_address',
        'destination_latitude',
        'destination_longitude',
        'destination_accuracy',
        'destination_address',
        'location_id',
        'status',
        'remarks',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'trip_date' => 'date',
        'origin_latitude' => 'float',
        'origin_longitude' => 'float',
        'origin_accuracy' => 'float',
        'destination_latitude' => 'float',
        'destination_longitude' => 'float',
        'destination_accuracy' => 'float',
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

    public function driverVehicleAssignment(): BelongsTo
    {
        return $this->belongsTo(DriverVehicleAssignment::class, 'driver_vehicle_assignment_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Scope for a specific driver.
     *
     * @param  Builder<DriverTrip>  $query
     */
    public function scopeForDriver(Builder $query, int $driverId): void
    {
        $query->where('driver_id', $driverId);
    }

    /**
     * Scope to currently in progress trips.
     *
     * @param  Builder<DriverTrip>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope to completed trips.
     *
     * @param  Builder<DriverTrip>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Domain method to start a trip with full business rule validation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function startTrip(array $attributes): self
    {
        $driverId = (int) ($attributes['driver_id'] ?? 0);
        $vehicleId = (int) ($attributes['vehicle_id'] ?? 0);
        $clientId = $attributes['client_id'] ?? null;

        if (! $clientId) {
            throw new DriverTripException('Trip requires a valid client_id UUID.');
        }

        // Idempotency: if trip already exists with this client_id
        $existing = static::where('client_id', $clientId)->first();
        if ($existing) {
            if ($existing->driver_id !== $driverId) {
                throw new DriverTripException("client_id '{$clientId}' has already been used by another driver.");
            }

            return $existing;
        }

        // Rule 1: Driver must have role = driver
        $driver = User::find($driverId);
        if (! $driver || ! $driver->isDriver()) {
            throw DriverTripException::invalidDriverRole($driverId);
        }

        // Rule 2: Vehicle must exist
        $vehicle = Vehicle::find($vehicleId);
        if (! $vehicle) {
            throw new DriverTripException("Vehicle #{$vehicleId} does not exist.");
        }

        // Rule 4: One active IN_PROGRESS trip per driver
        $hasActiveTrip = static::where('driver_id', $driverId)
            ->where('status', self::STATUS_IN_PROGRESS)
            ->exists();
        if ($hasActiveTrip) {
            throw DriverTripException::activeTripAlreadyExists($driverId);
        }

        $tripDate = isset($attributes['trip_date'])
            ? Carbon::parse($attributes['trip_date'])->toDateString()
            : Carbon::today()->toDateString();

        // Rule 3: Driver must be authorized to use the vehicle (active assignment)
        $assignment = DriverVehicleAssignment::forDriver($driverId)
            ->forVehicle($vehicleId)
            ->active($tripDate)
            ->first();

        if (! $assignment) {
            throw DriverTripException::unauthorizedVehicle($driverId, $vehicleId);
        }

        // Rule 9: origin coordinates and time_in required
        if (! isset($attributes['origin_latitude'])) {
            throw DriverTripException::missingOriginData('origin_latitude');
        }
        if (! isset($attributes['origin_longitude'])) {
            throw DriverTripException::missingOriginData('origin_longitude');
        }
        if (empty($attributes['time_in'])) {
            throw DriverTripException::missingOriginData('time_in');
        }

        return static::create([
            'client_id' => $clientId,
            'driver_id' => $driverId,
            'vehicle_id' => $vehicleId,
            'driver_vehicle_assignment_id' => $assignment->id,
            'trip_date' => $tripDate,
            'time_in' => $attributes['time_in'],
            'origin_latitude' => $attributes['origin_latitude'],
            'origin_longitude' => $attributes['origin_longitude'],
            'origin_accuracy' => $attributes['origin_accuracy'] ?? null,
            'origin_address' => $attributes['origin_address'] ?? null,
            'status' => self::STATUS_IN_PROGRESS,
            'remarks' => $attributes['remarks'] ?? null,
        ]);
    }

    /**
     * Domain method to end/complete a trip with full business rule validation.
     *
     * @param  array<string, mixed>  $destinationData
     */
    public function endTrip(array $destinationData, ?User $byUser = null): self
    {
        // Rule 5: Cannot END an already completed/cancelled trip
        if (! $this->isInProgress()) {
            throw DriverTripException::cannotEndTrip($this->status);
        }

        // Rule 6: Cannot END another driver's trip (unless admin)
        if ($byUser && ! $byUser->isAdmin() && $byUser->id !== $this->driver_id) {
            throw DriverTripException::unauthorizedDriver();
        }

        // Rule 8: destination data required when completing a trip
        if (! isset($destinationData['destination_latitude'])) {
            throw DriverTripException::missingDestinationData('destination_latitude');
        }
        if (! isset($destinationData['destination_longitude'])) {
            throw DriverTripException::missingDestinationData('destination_longitude');
        }
        if (empty($destinationData['time_out'])) {
            throw DriverTripException::missingDestinationData('time_out');
        }

        $this->update([
            'destination_latitude' => $destinationData['destination_latitude'],
            'destination_longitude' => $destinationData['destination_longitude'],
            'destination_accuracy' => $destinationData['destination_accuracy'] ?? null,
            'destination_address' => $destinationData['destination_address'] ?? null,
            'time_out' => $destinationData['time_out'],
            'status' => self::STATUS_COMPLETED,
            'remarks' => $destinationData['remarks'] ?? $this->remarks,
        ]);

        return $this;
    }

    /**
     * Domain method to cancel an in-progress trip.
     */
    public function cancelTrip(?string $remarks = null, ?User $byUser = null): self
    {
        if (! $this->isInProgress()) {
            throw DriverTripException::cannotEndTrip($this->status);
        }

        if ($byUser && ! $byUser->isAdmin() && $byUser->id !== $this->driver_id) {
            throw DriverTripException::unauthorizedDriver();
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'remarks' => $remarks ?? $this->remarks,
        ]);

        return $this;
    }
}
