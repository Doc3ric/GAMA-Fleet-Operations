<?php

namespace App\Models;

use App\Services\RoutingService;
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

    /** @var list<string> */
    protected $fillable = [
        'vehicle_id',
        'itinerary_date',
        'title',
        'notes',
        'status',
        'total_duration_minutes',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'itinerary_date' => 'date',
        'total_duration_minutes' => 'integer',
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

    public function getFormattedTotalDurationAttribute(): string
    {
        return RoutingService::formatDuration($this->total_duration_minutes);
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
