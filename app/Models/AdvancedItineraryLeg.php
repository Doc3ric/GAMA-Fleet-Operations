<?php

namespace App\Models;

use Database\Factories\AdvancedItineraryLegFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvancedItineraryLeg extends Model
{
    /** @use HasFactory<AdvancedItineraryLegFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'advanced_itinerary_id',
        'sort_order',
        'origin_location_id',
        'starting_point_location_id',
        'destination_location_id',
        'distance_origin_to_start',
        'distance_start_to_dest',
        'total_distance',
        'routing_source',
        'purpose',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'distance_origin_to_start' => 'float',
        'distance_start_to_dest' => 'float',
        'total_distance' => 'float',
        'sort_order' => 'integer',
    ];

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(AdvancedItinerary::class, 'advanced_itinerary_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function startingPoint(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'starting_point_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function isAutomatic(): bool
    {
        return $this->routing_source === 'osrm';
    }

    public function isManual(): bool
    {
        return $this->routing_source === 'manual';
    }
}
