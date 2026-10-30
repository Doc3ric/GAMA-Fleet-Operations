<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryEvent extends Model
{
    use HasFactory;

    public const TYPE_IDLING = 'idling';

    public const TYPE_PARKING = 'parking';

    public const TYPE_MOVING = 'moving';

    public const TYPE_OFFLINE = 'offline';

    public const TYPE_UNKNOWN = 'unknown';

    public const QUALITY_GOOD = 'good';

    public const QUALITY_STALE = 'stale';

    public const QUALITY_INTERMITTENT = 'intermittent';

    public const QUALITY_SUSPECT = 'suspect';

    public const SOURCE_LIVE_SYNC = 'live_sync';

    public const SOURCE_TRACK_RECONSTRUCTION = 'track_reconstruction';

    /** @var list<string> */
    protected $fillable = [
        'vehicle_id',
        'device_id',
        'imei',
        'event_type',
        'start_time',
        'end_time',
        'duration_seconds',
        'latitude',
        'longitude',
        'address',
        'is_ongoing',
        'data_quality',
        'source',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_seconds' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_ongoing' => 'boolean',
        'metadata' => 'array',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * Scope for ongoing active events.
     *
     * @param  Builder<TelemetryEvent>  $query
     */
    public function scopeOngoing(Builder $query): void
    {
        $query->where('is_ongoing', true);
    }

    /**
     * Scope for completed historical events.
     *
     * @param  Builder<TelemetryEvent>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('is_ongoing', false);
    }

    /**
     * Scope for events of a specific type.
     *
     * @param  Builder<TelemetryEvent>  $query
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('event_type', $type);
    }

    /**
     * Scope for events of a specific vehicle.
     *
     * @param  Builder<TelemetryEvent>  $query
     */
    public function scopeForVehicle(Builder $query, int $vehicleId): void
    {
        $query->where('vehicle_id', $vehicleId);
    }

    /**
     * Scope for events within a date-time range.
     *
     * @param  Builder<TelemetryEvent>  $query
     */
    public function scopeInDateRange(Builder $query, ?string $start, ?string $end): void
    {
        if ($start) {
            $query->where('start_time', '>=', $start);
        }
        if ($end) {
            $query->where('start_time', '<=', $end);
        }
    }

    /**
     * Human-readable duration string, e.g. "2h 15m", "45m", "10s".
     */
    public function getFormattedDurationAttribute(): string
    {
        $seconds = $this->duration_seconds;

        // If ongoing, dynamically compute duration from start_time until now
        if ($this->is_ongoing && $this->start_time) {
            $seconds = max(0, (int) now()->diffInSeconds($this->start_time));
        }

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
        }

        return "{$minutes}m";
    }

    /**
     * Human-readable label for event type.
     */
    public function getEventTypeLabelAttribute(): string
    {
        return match ($this->event_type) {
            self::TYPE_IDLING => 'Idling (Engine ON)',
            self::TYPE_PARKING => 'Parked (Engine OFF)',
            self::TYPE_MOVING => 'Moving',
            self::TYPE_OFFLINE => 'Offline',
            default => ucfirst($this->event_type),
        };
    }

    /**
     * Tailwind styling tokens for event badges.
     *
     * @return array{bg: string, text: string, border: string, dot: string}
     */
    public function getBadgeColorAttribute(): array
    {
        return match ($this->event_type) {
            self::TYPE_IDLING => [
                'bg' => 'bg-amber-50 dark:bg-amber-950/50',
                'text' => 'text-amber-800 dark:text-amber-300',
                'border' => 'border-amber-200 dark:border-amber-800',
                'dot' => 'bg-amber-500',
            ],
            self::TYPE_PARKING => [
                'bg' => 'bg-blue-50 dark:bg-blue-950/50',
                'text' => 'text-blue-800 dark:text-blue-300',
                'border' => 'border-blue-200 dark:border-blue-800',
                'dot' => 'bg-blue-500',
            ],
            self::TYPE_MOVING => [
                'bg' => 'bg-emerald-50 dark:bg-emerald-950/50',
                'text' => 'text-emerald-800 dark:text-emerald-300',
                'border' => 'border-emerald-200 dark:border-emerald-800',
                'dot' => 'bg-emerald-500',
            ],
            self::TYPE_OFFLINE => [
                'bg' => 'bg-slate-100 dark:bg-slate-800',
                'text' => 'text-slate-700 dark:text-slate-300',
                'border' => 'border-slate-300 dark:border-slate-700',
                'dot' => 'bg-slate-400',
            ],
            default => [
                'bg' => 'bg-purple-50 dark:bg-purple-950/50',
                'text' => 'text-purple-800 dark:text-purple-300',
                'border' => 'border-purple-200 dark:border-purple-800',
                'dot' => 'bg-purple-500',
            ],
        };
    }
}
