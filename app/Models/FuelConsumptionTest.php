<?php

namespace App\Models;

use App\Services\FuelConsumptionCalculationService;
use Database\Factories\FuelConsumptionTestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FuelConsumptionTest extends Model
{
    /** @use HasFactory<FuelConsumptionTestFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'test_date',
        'vehicle_id',
        'custom_equipment_code',
        'custom_plate_number',
        'custom_model',
        'driver_id',
        'driver_name',
        'start_odometer',
        'end_odometer',
        'distance_travelled',
        'fuel_consumed_liters',
        'average_fuel_consumption',
        'test_route',
        'remarks',
        'attested_by',
        'requested_by',
        'start_odometer_image',
        'end_odometer_image',
        'fuel_receipt_image',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'test_date' => 'date',
        'start_odometer' => 'float',
        'end_odometer' => 'float',
        'distance_travelled' => 'float',
        'fuel_consumed_liters' => 'float',
        'average_fuel_consumption' => 'float',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStartOdometerImageUrlAttribute(): ?string
    {
        return $this->start_odometer_image ? Storage::disk('public')->url($this->start_odometer_image) : null;
    }

    public function getEndOdometerImageUrlAttribute(): ?string
    {
        return $this->end_odometer_image ? Storage::disk('public')->url($this->end_odometer_image) : null;
    }

    public function getFuelReceiptImageUrlAttribute(): ?string
    {
        return $this->fuel_receipt_image ? Storage::disk('public')->url($this->fuel_receipt_image) : null;
    }

    public function getIsShortDistanceAttribute(): bool
    {
        return app(FuelConsumptionCalculationService::class)->isShortDistance((float) $this->distance_travelled);
    }

    public function getShortDistanceWarningAttribute(): ?string
    {
        return $this->is_short_distance
            ? app(FuelConsumptionCalculationService::class)->getShortDistanceWarningMessage()
            : null;
    }

    public function getDriverDisplayNameAttribute(): string
    {
        return $this->driver?->name ?? $this->driver_name ?? 'Not Assigned';
    }

    public function getEquipmentCodeDisplayAttribute(): string
    {
        return $this->vehicle?->equipment_code ?? $this->custom_equipment_code ?? 'N/A';
    }

    public function getPlateNumberDisplayAttribute(): ?string
    {
        return $this->vehicle?->plate_number ?? $this->custom_plate_number;
    }

    public function getModelDisplayAttribute(): ?string
    {
        return $this->vehicle?->model ?? $this->custom_model;
    }

    /**
     * Scope for a specific vehicle.
     *
     * @param  Builder<FuelConsumptionTest>  $query
     */
    public function scopeForVehicle(Builder $query, int $vehicleId): void
    {
        $query->where('vehicle_id', $vehicleId);
    }

    /**
     * Scope for date range filter.
     *
     * @param  Builder<FuelConsumptionTest>  $query
     */
    public function scopeDateRange(Builder $query, ?string $start, ?string $end): void
    {
        if ($start) {
            $query->whereDate('test_date', '>=', $start);
        }
        if ($end) {
            $query->whereDate('test_date', '<=', $end);
        }
    }

    /**
     * Scope for general search.
     *
     * @param  Builder<FuelConsumptionTest>  $query
     */
    public function scopeSearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $query->where(function (Builder $q) use ($search) {
            $q->where('test_route', 'like', "%{$search}%")
                ->orWhere('remarks', 'like', "%{$search}%")
                ->orWhere('driver_name', 'like', "%{$search}%")
                ->orWhere('attested_by', 'like', "%{$search}%")
                ->orWhere('requested_by', 'like', "%{$search}%")
                ->orWhereHas('vehicle', function (Builder $vq) use ($search) {
                    $vq->where('equipment_code', 'like', "%{$search}%")
                        ->orWhere('plate_number', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                })
                ->orWhereHas('driver', function (Builder $dq) use ($search) {
                    $dq->where('name', 'like', "%{$search}%");
                });
        });
    }
}
