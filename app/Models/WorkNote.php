<?php

namespace App\Models;

use Database\Factories\WorkNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkNote extends Model
{
    /** @use HasFactory<WorkNoteFactory> */
    use HasFactory;

    public const COLOR_SLATE = 'slate';

    public const COLOR_BLUE = 'blue';

    public const COLOR_AMBER = 'amber';

    public const COLOR_EMERALD = 'emerald';

    public const COLOR_ROSE = 'rose';

    public const VALID_COLORS = [
        self::COLOR_SLATE,
        self::COLOR_BLUE,
        self::COLOR_AMBER,
        self::COLOR_EMERALD,
        self::COLOR_ROSE,
    ];

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'is_pinned',
        'color',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Query Scopes ───────────────────────────────────────────────────────

    /**
     * @param  Builder<WorkNote>  $query
     */
    public function scopePinned(Builder $query): void
    {
        $query->where('is_pinned', true);
    }

    /**
     * @param  Builder<WorkNote>  $query
     */
    public function scopeUnpinned(Builder $query): void
    {
        $query->where('is_pinned', false);
    }

    /**
     * @param  Builder<WorkNote>  $query
     */
    public function scopeSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('content', 'like', "%{$search}%");
        });
    }

    // ─── Color Attribute Styling ────────────────────────────────────────────

    /**
     * Get Tailwind card styling based on selected color.
     *
     * @return array{card: string, header: string, badge: string, border: string}
     */
    public function getColorStyleAttribute(): array
    {
        return match ($this->color) {
            self::COLOR_BLUE => [
                'card' => 'bg-blue-50/40 hover:bg-blue-50/70 border-blue-200',
                'header' => 'text-blue-900',
                'badge' => 'bg-blue-100 text-blue-800 border-blue-200',
                'border' => 'border-blue-200',
            ],
            self::COLOR_AMBER => [
                'card' => 'bg-amber-50/40 hover:bg-amber-50/70 border-amber-200',
                'header' => 'text-amber-900',
                'badge' => 'bg-amber-100 text-amber-800 border-amber-200',
                'border' => 'border-amber-200',
            ],
            self::COLOR_EMERALD => [
                'card' => 'bg-emerald-50/40 hover:bg-emerald-50/70 border-emerald-200',
                'header' => 'text-emerald-900',
                'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                'border' => 'border-emerald-200',
            ],
            self::COLOR_ROSE => [
                'card' => 'bg-rose-50/40 hover:bg-rose-50/70 border-rose-200',
                'header' => 'text-rose-900',
                'badge' => 'bg-rose-100 text-rose-800 border-rose-200',
                'border' => 'border-rose-200',
            ],
            default => [
                'card' => 'bg-white hover:bg-slate-50/70 border-slate-200',
                'header' => 'text-slate-900',
                'badge' => 'bg-slate-100 text-slate-700 border-slate-200',
                'border' => 'border-slate-200',
            ],
        };
    }
}
