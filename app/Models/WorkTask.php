<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\WorkTaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTask extends Model
{
    /** @use HasFactory<WorkTaskFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_ON_HOLD = 'on_hold';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_ON_HOLD,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const VALID_PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'resolution_notes',
        'next_action',
        'notes',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Status Helpers ─────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isOnHold(): bool
    {
        return $this->status === self::STATUS_ON_HOLD;
    }

    /**
     * A task is overdue when it has a past due_date and is not completed or cancelled.
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->isCompleted() || $this->isCancelled()) {
            return false;
        }

        if (! $this->due_date) {
            return false;
        }

        return $this->due_date->lt(Carbon::today());
    }

    /**
     * Returns true when the task is due today and is not completed/cancelled.
     */
    public function getIsDueTodayAttribute(): bool
    {
        if ($this->isCompleted() || $this->isCancelled()) {
            return false;
        }

        if (! $this->due_date) {
            return false;
        }

        return $this->due_date->isToday();
    }

    // ─── Priority Helpers ────────────────────────────────────────────────────

    /**
     * Tailwind badge token set for the current priority.
     *
     * @return array{bg: string, text: string, border: string}
     */
    public function getPriorityBadgeAttribute(): array
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'border' => 'border-red-200'],
            self::PRIORITY_HIGH => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200'],
            self::PRIORITY_LOW => ['bg' => 'bg-slate-50', 'text' => 'text-slate-500', 'border' => 'border-slate-200'],
            default => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-200'],
        };
    }

    /**
     * Human-readable priority label.
     */
    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'Urgent',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_LOW => 'Low',
            default => 'Normal',
        };
    }

    // ─── Status Badge ────────────────────────────────────────────────────────

    /**
     * Tailwind badge token set for the current status.
     *
     * @return array{bg: string, text: string, border: string, dot: string}
     */
    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500'],
            self::STATUS_IN_PROGRESS => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'dot' => 'bg-blue-500'],
            self::STATUS_ON_HOLD => ['bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'border' => 'border-slate-200', 'dot' => 'bg-slate-400'],
            self::STATUS_CANCELLED => ['bg' => 'bg-red-50', 'text' => 'text-red-600', 'border' => 'border-red-200', 'dot' => 'bg-red-400'],
            default => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'dot' => 'bg-amber-400'],
        };
    }

    /**
     * Human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_ON_HOLD => 'On Hold',
            default => ucfirst($this->status),
        };
    }

    // ─── Query Scopes ────────────────────────────────────────────────────────

    /**
     * @param  Builder<WorkTask>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<WorkTask>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * @param  Builder<WorkTask>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Tasks with a past due_date that are not completed or cancelled.
     *
     * @param  Builder<WorkTask>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today());
    }

    /**
     * Tasks due today that are not completed or cancelled.
     *
     * @param  Builder<WorkTask>  $query
     */
    public function scopeDueToday(Builder $query): void
    {
        $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED])
            ->whereNotNull('due_date')
            ->whereDate('due_date', Carbon::today());
    }

    /**
     * Tasks due in the future (after today) that are not completed or cancelled.
     *
     * @param  Builder<WorkTask>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED])
            ->where(function (Builder $q) {
                $q->whereNull('due_date')
                    ->orWhereDate('due_date', '>', Carbon::today());
            });
    }

    /**
     * Check if task was completed on time (before or on due date, or had no due date).
     */
    public function isCompletedOnTime(): bool
    {
        if (! $this->isCompleted() || ! $this->completed_at) {
            return false;
        }

        if (! $this->due_date) {
            return true;
        }

        return $this->completed_at->startOfDay()->lte($this->due_date->startOfDay());
    }

    /**
     * Check if task was completed after its due date.
     */
    public function isCompletedLate(): bool
    {
        if (! $this->isCompleted() || ! $this->completed_at || ! $this->due_date) {
            return false;
        }

        return $this->completed_at->startOfDay()->gt($this->due_date->startOfDay());
    }

    /**
     * @param  Builder<WorkTask>  $query
     */
    public function scopeCompletedToday(Builder $query): void
    {
        $query->where('status', self::STATUS_COMPLETED)
            ->whereDate('completed_at', Carbon::today());
    }

    /**
     * @param  Builder<WorkTask>  $query
     */
    public function scopeCompletedThisWeek(Builder $query): void
    {
        $query->where('status', self::STATUS_COMPLETED)
            ->whereBetween('completed_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ]);
    }

    /**
     * @param  Builder<WorkTask>  $query
     */
    public function scopeCompletedThisMonth(Builder $query): void
    {
        $query->where('status', self::STATUS_COMPLETED)
            ->whereBetween('completed_at', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ]);
    }
}
