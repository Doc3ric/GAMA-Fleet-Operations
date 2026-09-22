<?php

namespace App\Services;

use App\Models\DriverTrip;
use App\Models\Location;
use App\Models\LocationAlias;
use Illuminate\Database\Eloquent\Collection;

class LocationRecognitionService
{
    public const STATUS_RECOGNIZED = 'RECOGNIZED';

    public const STATUS_POSSIBLE_MATCH = 'POSSIBLE MATCH';

    public const STATUS_UNKNOWN = 'UNKNOWN';

    /**
     * Cache of active locations and aliases for batch processing.
     *
     * @var Collection<int, Location>|null
     */
    protected ?Collection $activeLocationsCache = null;

    /**
     * Normalize a location/destination string for comparison.
     */
    public function normalize(string $text): string
    {
        // Lowercase, replace hyphens and underscores with spaces, collapse multiple spaces, trim
        $clean = mb_strtolower(trim($text), 'UTF-8');
        $clean = preg_replace('/[_\-\/\\\]+/', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

        return trim($clean);
    }

    /**
     * Strip all non-alphanumeric characters for compact comparison (e.g. "G-2" -> "g2", "FARM 2" -> "farm2").
     */
    public function toAlphanumeric(string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($text, 'UTF-8')) ?? '';
    }

    /**
     * Recognize a destination string and return structured result.
     *
     * @return array{
     *     status: string,
     *     location: ?Location,
     *     confidence: int,
     *     match_type: string,
     *     raw_entry: ?string,
     *     suggested_location: ?Location
     * }
     */
    public function recognize(?string $destinationAddress, ?int $explicitLocationId = null): array
    {
        $raw = $destinationAddress !== null ? trim($destinationAddress) : '';

        // 1. Explicit assigned location takes absolute precedence if valid and active
        if ($explicitLocationId) {
            $explicitLoc = Location::with('aliases')->find($explicitLocationId);
            if ($explicitLoc && $explicitLoc->isActive()) {
                return [
                    'status' => self::STATUS_RECOGNIZED,
                    'location' => $explicitLoc,
                    'confidence' => 100,
                    'match_type' => 'explicit',
                    'raw_entry' => $destinationAddress,
                    'suggested_location' => null,
                ];
            }
        }

        if ($raw === '') {
            return [
                'status' => self::STATUS_UNKNOWN,
                'location' => null,
                'confidence' => 0,
                'match_type' => 'empty',
                'raw_entry' => $destinationAddress,
                'suggested_location' => null,
            ];
        }

        $normalizedInput = $this->normalize($raw);
        $alphanumericInput = $this->toAlphanumeric($raw);

        $locations = $this->getActiveLocations();

        // 2. Exact Match against Code (case-insensitive)
        foreach ($locations as $loc) {
            if ($this->normalize($loc->code) === $normalizedInput || ($alphanumericInput !== '' && $this->toAlphanumeric($loc->code) === $alphanumericInput)) {
                return [
                    'status' => self::STATUS_RECOGNIZED,
                    'location' => $loc,
                    'confidence' => 100,
                    'match_type' => 'exact_code',
                    'raw_entry' => $destinationAddress,
                    'suggested_location' => null,
                ];
            }
        }

        // 3. Exact Match against Official Name (case-insensitive)
        foreach ($locations as $loc) {
            if ($this->normalize($loc->official_name) === $normalizedInput) {
                return [
                    'status' => self::STATUS_RECOGNIZED,
                    'location' => $loc,
                    'confidence' => 100,
                    'match_type' => 'exact_name',
                    'raw_entry' => $destinationAddress,
                    'suggested_location' => null,
                ];
            }
        }

        // 4. Exact Match against Registered Aliases (case-insensitive)
        foreach ($locations as $loc) {
            foreach ($loc->aliases as $aliasObj) {
                if ($this->normalize($aliasObj->alias) === $normalizedInput) {
                    return [
                        'status' => self::STATUS_RECOGNIZED,
                        'location' => $loc,
                        'confidence' => 100,
                        'match_type' => 'exact_alias',
                        'raw_entry' => $destinationAddress,
                        'suggested_location' => null,
                    ];
                }
            }
        }

        // 5. Normalized alphanumeric compact match (e.g. "GAMA FARM2" matches "GAMA FARM 2")
        if (strlen($alphanumericInput) >= 2) {
            foreach ($locations as $loc) {
                if ($this->toAlphanumeric($loc->official_name) === $alphanumericInput) {
                    return [
                        'status' => self::STATUS_RECOGNIZED,
                        'location' => $loc,
                        'confidence' => 95,
                        'match_type' => 'normalized_name',
                        'raw_entry' => $destinationAddress,
                        'suggested_location' => null,
                    ];
                }
                foreach ($loc->aliases as $aliasObj) {
                    if ($this->toAlphanumeric($aliasObj->alias) === $alphanumericInput) {
                        return [
                            'status' => self::STATUS_RECOGNIZED,
                            'location' => $loc,
                            'confidence' => 95,
                            'match_type' => 'normalized_alias',
                            'raw_entry' => $destinationAddress,
                            'suggested_location' => null,
                        ];
                    }
                }
            }
        }

        // 6. Controlled Fuzzy Match (generates POSSIBLE MATCH suggestion only, NEVER auto-assigns location_id)
        // Minimum string length >= 3 to avoid false positives with short codes
        if (strlen($normalizedInput) >= 3) {
            $bestScore = 0.0;
            $bestCandidate = null;

            foreach ($locations as $loc) {
                // Check against official name
                $targetName = $this->normalize($loc->official_name);
                similar_text($normalizedInput, $targetName, $percent);
                if ($percent > $bestScore) {
                    $bestScore = $percent;
                    $bestCandidate = $loc;
                }

                // Check against aliases
                foreach ($loc->aliases as $aliasObj) {
                    $targetAlias = $this->normalize($aliasObj->alias);
                    similar_text($normalizedInput, $targetAlias, $aliasPercent);
                    if ($aliasPercent > $bestScore) {
                        $bestScore = $aliasPercent;
                        $bestCandidate = $loc;
                    }
                }
            }

            // Confidence threshold >= 75% for POSSIBLE MATCH
            if ($bestScore >= 75.0 && $bestCandidate !== null) {
                return [
                    'status' => self::STATUS_POSSIBLE_MATCH,
                    'location' => null, // NEVER auto-assigned!
                    'suggested_location' => $bestCandidate,
                    'confidence' => (int) round($bestScore),
                    'match_type' => 'fuzzy_suggestion',
                    'raw_entry' => $destinationAddress,
                ];
            }
        }

        // 7. Unknown Destination
        return [
            'status' => self::STATUS_UNKNOWN,
            'location' => null,
            'suggested_location' => null,
            'confidence' => 0,
            'match_type' => 'unknown',
            'raw_entry' => $destinationAddress,
        ];
    }

    /**
     * Recognize destination for a specific DriverTrip model.
     *
     * @return array{
     *     status: string,
     *     location: ?Location,
     *     confidence: int,
     *     match_type: string,
     *     raw_entry: ?string,
     *     suggested_location: ?Location
     * }
     */
    public function recognizeForTrip(DriverTrip $trip): array
    {
        return $this->recognize($trip->destination_address, $trip->location_id);
    }

    /**
     * Batch recognize an iterable of trips with cached active locations (avoids N+1).
     *
     * @param  iterable<int, DriverTrip>  $trips
     * @return array<int, array{
     *     status: string,
     *     location: ?Location,
     *     confidence: int,
     *     match_type: string,
     *     raw_entry: ?string,
     *     suggested_location: ?Location
     * }>
     */
    public function recognizeMany(iterable $trips): array
    {
        $this->warmActiveLocations();
        $results = [];

        foreach ($trips as $trip) {
            $results[$trip->id] = $this->recognizeForTrip($trip);
        }

        return $results;
    }

    /**
     * Check if an alias already belongs to another active location (Adjustment 3).
     * Returns conflicting Location if found, or null if clean.
     */
    public function findConflictingLocationForAlias(string $alias, ?int $exceptLocationId = null): ?Location
    {
        $normalized = $this->normalize($alias);
        if ($normalized === '') {
            return null;
        }

        $query = LocationAlias::query()
            ->whereHas('location', function ($q) use ($exceptLocationId) {
                $q->where('status', Location::STATUS_ACTIVE);
                if ($exceptLocationId) {
                    $q->where('id', '!=', $exceptLocationId);
                }
            });

        $aliases = $query->with('location')->get();
        foreach ($aliases as $aliasRecord) {
            if ($this->normalize($aliasRecord->alias) === $normalized) {
                return $aliasRecord->location;
            }
        }

        // Also check if matches another active location's primary code
        $codeMatch = Location::query()
            ->active()
            ->when($exceptLocationId, fn ($q) => $q->where('id', '!=', $exceptLocationId))
            ->get();

        foreach ($codeMatch as $loc) {
            if ($this->normalize($loc->code) === $normalized) {
                return $loc;
            }
        }

        return null;
    }

    /**
     * Backfill location_id for past trips with matching destination_address without touching destination_address.
     */
    public function backfillHistoricalTrips(string $destinationText, Location $location): int
    {
        $clean = trim($destinationText);
        if ($clean === '') {
            return 0;
        }

        return DriverTrip::where('destination_address', $clean)
            ->whereNull('location_id')
            ->update(['location_id' => $location->id]);
    }

    /**
     * Fetch active locations with eager loaded aliases.
     *
     * @return Collection<int, Location>
     */
    public function getActiveLocations(): Collection
    {
        if ($this->activeLocationsCache === null) {
            $this->warmActiveLocations();
        }

        return $this->activeLocationsCache;
    }

    /**
     * Warm up active locations cache.
     */
    public function warmActiveLocations(): void
    {
        $this->activeLocationsCache = Location::query()
            ->active()
            ->with(['aliases'])
            ->get();
    }

    /**
     * Clear active locations cache.
     */
    public function clearCache(): void
    {
        $this->activeLocationsCache = null;
    }
}
