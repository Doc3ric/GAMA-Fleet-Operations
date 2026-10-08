<?php

namespace App\Models;

use Database\Factories\AdvancedItineraryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvancedItinerary extends Model
{
    /** @use HasFactory<AdvancedItineraryFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_FINALIZED = 'FINALIZED';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_FINALIZED,
    ];

    public const CALCULATION_METHOD_DIVIDE = 'divide';

    public const CALCULATION_METHOD_MULTIPLY = 'multiply';

    public const CALCULATION_METHODS = [
        self::CALCULATION_METHOD_DIVIDE,
        self::CALCULATION_METHOD_MULTIPLY,
    ];

    /** @var list<string> */
    protected $fillable = [
        'vehicle_id',
        'driver_name',
        'itinerary_date',
        'title',
        'destination',
        'start_odo',
        'end_odo',
        'total_distance',
        'notes',
        'status',
        'total_duration_minutes',
        'fuel_liters_required',
        'calculation_method',
        'po_checked',
        'po_checked_at',
        'po_checked_by',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'itinerary_date' => 'date',
        'start_odo' => 'float',
        'end_odo' => 'float',
        'total_distance' => 'float',
        'total_duration_minutes' => 'integer',
        'fuel_liters_required' => 'float',
        'calculation_method' => 'string',
        'po_checked' => 'boolean',
        'po_checked_at' => 'datetime',
        'po_checked_by' => 'integer',
    ];

    public function legs(): HasMany
    {
        return $this->hasMany(AdvancedItineraryLeg::class, 'advanced_itinerary_id')->orderBy('sort_order');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function getTotalDistanceAttribute(): float
    {
        if (isset($this->attributes['total_distance']) && $this->attributes['total_distance'] !== null && (float) $this->attributes['total_distance'] > 0) {
            return (float) $this->attributes['total_distance'];
        }

        if ($this->relationLoaded('legs')) {
            return (float) ($this->legs->sum('total_distance') ?? 0.0);
        }

        return (float) ($this->legs()->sum('total_distance') ?? 0.0);
    }

    public function getTotalDurationMinutesAttribute(): ?int
    {
        if (isset($this->attributes['total_duration_minutes']) && $this->attributes['total_duration_minutes'] !== null) {
            return (int) $this->attributes['total_duration_minutes'];
        }

        if ($this->relationLoaded('legs')) {
            $sum = $this->legs->sum('total_duration_minutes');

            return $sum > 0 ? (int) $sum : null;
        }

        $sum = $this->legs()->sum('total_duration_minutes');

        return $sum > 0 ? (int) $sum : null;
    }

    public function getDestinationNameAttribute(): string
    {
        if (! empty($this->destination)) {
            return $this->destination;
        }

        $legs = $this->relationLoaded('legs') ? $this->legs : $this->legs()->with('destination')->get();
        $lastLeg = $legs->sortBy('sort_order')->last();

        if (! $lastLeg) {
            return '—';
        }

        return $lastLeg->destination?->official_name ?? $lastLeg->purpose ?? '—';
    }

    public function getPurposeCargoAttribute(): string
    {
        $legs = $this->relationLoaded('legs') ? $this->legs : $this->legs()->with('destination')->get();

        if ($legs->isEmpty()) {
            return '—';
        }

        $destParts = $this->destination ? array_map('trim', explode(' → ', $this->destination)) : [];

        $purposes = [];
        foreach ($legs as $i => $leg) {
            $rawPurpose = trim((string) ($leg->purpose ?? ''));
            if ($rawPurpose === '') {
                continue;
            }

            $destName = trim((string) ($leg->destination?->official_name ?? ($destParts[$i] ?? '')));

            if ($destName !== '' && strcasecmp($rawPurpose, $destName) === 0) {
                continue;
            }

            if ($this->destination && strcasecmp($rawPurpose, trim((string) $this->destination)) === 0) {
                continue;
            }

            $purposes[] = $rawPurpose;
        }

        $unique = array_values(array_unique($purposes));

        if (empty($unique)) {
            return '—';
        }

        return implode('; ', $unique);
    }

    public function getDriverNameAttribute(): string
    {
        if (! empty($this->attributes['driver_name'])) {
            return $this->attributes['driver_name'];
        }

        return $this->vehicle?->operator_driver ?? '—';
    }

    public function getFuelLitersAttribute(): ?float
    {
        if ($this->fuel_liters_required !== null) {
            return (float) $this->fuel_liters_required;
        }

        $vehicle = $this->vehicle;
        $avgConsumption = $vehicle?->average_fuel_consumption ?? $vehicle?->average_consumption;
        $method = $this->calculation_method ?: self::detectCalculationMethod($vehicle?->equipment_code);

        return self::calculateFuelLiters(
            $this->total_distance,
            $avgConsumption !== null ? (float) $avgConsumption : null,
            $method
        );
    }

    public function poChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'po_checked_by');
    }

    /**
     * Auto-detect whether to use 'multiply' (FL / Forklift) or 'divide' (standard vehicles).
     */
    public static function detectCalculationMethod(?string $equipmentCode): string
    {
        if ($equipmentCode === null || trim($equipmentCode) === '') {
            return self::CALCULATION_METHOD_DIVIDE;
        }

        // Forklift vehicles generally start with "FL" (e.g. FL5, FL 8/FL6, FL 9, FL10)
        if (preg_match('/^FL(\s*[-_\/0-9]|$)/i', trim($equipmentCode))) {
            return self::CALCULATION_METHOD_MULTIPLY;
        }

        return self::CALCULATION_METHOD_DIVIDE;
    }

    /**
     * Calculate fuel liters required based on Total Distance and Average Consumption.
     * Method 'divide': Total Distance ÷ Average Consumption (standard vehicles).
     * Method 'multiply': Total Distance × Average Consumption (FL / Forklift vehicles).
     *
     * Uses custom threshold rounding (0.10 threshold):
     * - Decimal .00 to .09: keep integer part (floor)
     * - Decimal .10 and higher: round up to next whole integer (ceiling)
     *
     * Returns null safely if average consumption is missing, zero, or negative.
     */
    public static function calculateFuelLiters(
        ?float $distance,
        ?float $averageConsumption,
        string $method = self::CALCULATION_METHOD_DIVIDE
    ): ?float {
        if ($distance === null || $distance <= 0) {
            return 0.0;
        }

        if ($averageConsumption === null || $averageConsumption <= 0) {
            return null;
        }

        if ($method === self::CALCULATION_METHOD_MULTIPLY) {
            $raw = $distance * $averageConsumption;
        } else {
            $raw = $distance / $averageConsumption;
        }

        $rounded = round($raw, 2);
        $intPart = floor($rounded);
        $decPart = round($rounded - $intPart, 2);

        return (float) ($decPart >= 0.10 ? $intPart + 1 : $intPart);
    }

    /**
     * Automatically compute and update the fuel liters required for this itinerary
     * using its total distance and the vehicle's average fuel consumption.
     */
    public function recalculateFuelLiters(): ?float
    {
        $vehicle = $this->vehicle;
        $avgConsumption = $vehicle?->average_fuel_consumption ?? $vehicle?->average_consumption;
        $method = $this->calculation_method ?: self::detectCalculationMethod($vehicle?->equipment_code);
        $liters = self::calculateFuelLiters(
            $this->total_distance,
            $avgConsumption !== null ? (float) $avgConsumption : null,
            $method
        );

        $this->fuel_liters_required = $liters;

        return $liters;
    }

    /**
     * Explicitly toggle the PO checklist state and update audit information.
     */
    public function togglePoCheck(int $userId): bool
    {
        $newChecked = ! $this->po_checked;

        $this->update([
            'po_checked' => $newChecked,
            'po_checked_at' => $newChecked ? now() : null,
            'po_checked_by' => $newChecked ? $userId : null,
        ]);

        return $newChecked;
    }

    /**
     * @param  Builder<AdvancedItinerary>  $query
     * @return Builder<AdvancedItinerary>
     */
    public function scopePoChecked(Builder $query): Builder
    {
        return $query->where('po_checked', true);
    }

    /**
     * @param  Builder<AdvancedItinerary>  $query
     * @return Builder<AdvancedItinerary>
     */
    public function scopePoUnchecked(Builder $query): Builder
    {
        return $query->where('po_checked', false);
    }

    /**
     * @param  Builder<AdvancedItinerary>  $query
     * @return Builder<AdvancedItinerary>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * @param  Builder<AdvancedItinerary>  $query
     * @return Builder<AdvancedItinerary>
     */
    public function scopeFinalized(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FINALIZED);
    }
}
