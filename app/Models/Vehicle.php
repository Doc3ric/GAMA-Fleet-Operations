<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'equipment_code',
        'vehicle_type_id',
        'model',
        'plate_number',
        'date_acquired',
        'fuel_min',
        'fuel_max',
        'fuel_unit',
        'average_fuel_consumption',
        'status_value',
        'status_label',
        'location',
        'project_code',
        'operator_driver',
        'user',
        'helper',
        'gps_status',
        'image',
        'notes',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date_acquired' => 'date',
        'fuel_min' => 'float',
        'fuel_max' => 'float',
        'average_fuel_consumption' => 'float',
        'status_value' => 'float',
    ];

    /** Valid GPS status values. */
    public const GPS_STATUSES = ['YES', 'NO', 'EXPIRED', 'FOR_CHECKUP'];

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function device(): HasOne
    {
        return $this->hasOne(Device::class, 'vehicle_id');
    }

    public function driverVehicleAssignments(): HasMany
    {
        return $this->hasMany(DriverVehicleAssignment::class, 'vehicle_id');
    }

    public function driverTrips(): HasMany
    {
        return $this->hasMany(DriverTrip::class, 'vehicle_id');
    }

    public function fuelConsumptionTests(): HasMany
    {
        return $this->hasMany(FuelConsumptionTest::class, 'vehicle_id');
    }

    /**
     * Returns average fuel consumption in KM/L, checking direct attribute first, then latest test.
     */
    public function getAverageConsumptionAttribute(): ?float
    {
        if ($this->average_fuel_consumption !== null && (float) $this->average_fuel_consumption > 0) {
            return (float) $this->average_fuel_consumption;
        }

        $latestTest = $this->fuelConsumptionTests()->latest('test_date')->latest('id')->first();
        if ($latestTest && (float) $latestTest->average_fuel_consumption > 0) {
            return (float) $latestTest->average_fuel_consumption;
        }

        return null;
    }

    /**
     * Returns fuel display string, e.g. "16–20 LIT/HR" or "20 LIT/HR".
     */
    public function getFuelDisplayAttribute(): ?string
    {
        if ($this->fuel_min === null && $this->fuel_max === null) {
            return null;
        }

        $unit = $this->fuel_unit ?: 'LIT/HR';

        if ($this->fuel_min !== null && $this->fuel_max !== null && $this->fuel_min !== $this->fuel_max) {
            return "{$this->fuel_min}–{$this->fuel_max} {$unit}";
        }

        $value = $this->fuel_max ?? $this->fuel_min;

        return "{$value} {$unit}";
    }

    /**
     * Returns status display string, e.g. "0.8 / RUNNING".
     */
    public function getStatusDisplayAttribute(): ?string
    {
        if ($this->status_value === null && ! $this->status_label) {
            return null;
        }

        if ($this->status_value !== null && $this->status_label) {
            return "{$this->status_value} / {$this->status_label}";
        }

        return $this->status_label ?? (string) $this->status_value;
    }

    /**
     * Returns the public URL for the vehicle image.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return Storage::disk('public')->url($this->image);
    }

    /**
     * Returns Tailwind color classes for GPS status badge.
     *
     * @return array{bg: string, text: string}
     */
    public function getGpsStatusColorAttribute(): array
    {
        return match ($this->gps_status) {
            'YES' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800'],
            'NO' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600'],
            'EXPIRED' => ['bg' => 'bg-red-100', 'text' => 'text-red-800'],
            'FOR_CHECKUP' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800'],
            default => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600'],
        };
    }

    /**
     * Returns human-readable GPS status label.
     */
    public function getGpsStatusLabelAttribute(): string
    {
        return match ($this->gps_status) {
            'FOR_CHECKUP' => 'FOR CHECKUP',
            default => $this->gps_status,
        };
    }
}
