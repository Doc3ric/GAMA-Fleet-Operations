<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $fillable = [
        'report_type',
        'report_date',
        'status',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'report_date' => 'date',
    ];

    public function longIdlingRecords(): HasMany
    {
        return $this->hasMany(LongIdlingRecord::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'Completed',
            default => 'Draft',
        };
    }

    public function getReportTypeLabelAttribute(): string
    {
        return config('foms.report_types')[$this->report_type] ?? $this->report_type;
    }
}
