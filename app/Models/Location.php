<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_INACTIVE = 'Inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    public const TYPE_FARM = 'Farm';

    public const TYPE_WAREHOUSE = 'Warehouse';

    public const TYPE_OFFICE = 'Office';

    public const TYPE_PROJECT_SITE = 'Project Site';

    public const TYPE_CONSTRUCTION_SITE = 'Construction Site';

    public const TYPE_SUPPLIER = 'Supplier';

    public const TYPE_CUSTOMER = 'Customer';

    public const TYPE_FUEL_STATION = 'Fuel Station';

    public const TYPE_OTHER = 'Other';

    public const TYPES = [
        self::TYPE_FARM,
        self::TYPE_WAREHOUSE,
        self::TYPE_OFFICE,
        self::TYPE_PROJECT_SITE,
        self::TYPE_CONSTRUCTION_SITE,
        self::TYPE_SUPPLIER,
        self::TYPE_CUSTOMER,
        self::TYPE_FUEL_STATION,
        self::TYPE_OTHER,
    ];

    /** @var list<string> */
    protected $fillable = [
        'code',
        'official_name',
        'type',
        'latitude',
        'longitude',
        'address',
        'barangay',
        'municipality',
        'province',
        'status',
        'image_path',
        'notes',
        'area_consultant',
        'contact_number',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function aliases(): HasMany
    {
        return $this->hasMany(LocationAlias::class, 'location_id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(DriverTrip::class, 'location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    public function getCoordinatesAttribute(): string
    {
        return "{$this->latitude}, {$this->longitude}";
    }

    /**
     * Scope for active locations.
     *
     * @param  Builder<Location>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for inactive locations.
     *
     * @param  Builder<Location>  $query
     */
    public function scopeInactive(Builder $query): void
    {
        $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope for filtering by type.
     *
     * @param  Builder<Location>  $query
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Scope for searching across code, official name, aliases, address, barangay, municipality, province.
     *
     * @param  Builder<Location>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $clean = trim($term);
        if ($clean === '') {
            return;
        }

        $query->where(function (Builder $q) use ($clean) {
            $q->where('code', 'like', "%{$clean}%")
                ->orWhere('official_name', 'like', "%{$clean}%")
                ->orWhere('address', 'like', "%{$clean}%")
                ->orWhere('barangay', 'like', "%{$clean}%")
                ->orWhere('municipality', 'like', "%{$clean}%")
                ->orWhere('province', 'like', "%{$clean}%")
                ->orWhereHas('aliases', function (Builder $aq) use ($clean) {
                    $aq->where('alias', 'like', "%{$clean}%");
                });
        });
    }

    /**
     * Get usage statistics in Driver Trips.
     */
    public function getUsageStats(): array
    {
        // Aliases list for this location
        $knownAliases = $this->aliases->pluck('alias')->toArray();
        $allSearchTerms = array_unique(array_filter(array_merge(
            [$this->code, $this->official_name],
            $knownAliases
        )));

        // Trips referencing by location_id or matching destination text
        $tripQuery = DriverTrip::query()
            ->where(function ($q) use ($allSearchTerms) {
                $q->where('location_id', $this->id);
                if (! empty($allSearchTerms)) {
                    $q->orWhereIn('destination_address', $allSearchTerms);
                }
            });

        $totalCount = (clone $tripQuery)->count();
        $recentTrips = (clone $tripQuery)
            ->with(['driver', 'vehicle'])
            ->orderByDesc('trip_date')
            ->orderByDesc('time_in')
            ->limit(5)
            ->get();

        // Frequently used driver-entered strings
        $commonEntries = (clone $tripQuery)
            ->whereNotNull('destination_address')
            ->selectRaw('destination_address, COUNT(*) as count')
            ->groupBy('destination_address')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'destination_address')
            ->toArray();

        return [
            'total_trips' => $totalCount,
            'recent_trips' => $recentTrips,
            'common_entries' => $commonEntries,
        ];
    }
}
