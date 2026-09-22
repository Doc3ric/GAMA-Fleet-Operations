<?php

namespace App\Models;

use Database\Factories\LocationAliasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationAlias extends Model
{
    /** @use HasFactory<LocationAliasFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'location_id',
        'alias',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Scope matching alias case-insensitively.
     *
     * @param  Builder<LocationAlias>  $query
     */
    public function scopeForAlias(Builder $query, string $alias): void
    {
        $query->whereRaw('LOWER(alias) = ?', [strtolower(trim($alias))]);
    }
}
