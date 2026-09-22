<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'device_name',
        'vehicle_id',
        'imei',
        'model',
        'activated_date',
        'sales_time',
        'sim',
        'expiration_date',
        'raw_expiration',
        'group_name',
        'iccid',
        'imsi',
        'mileage',
        'notes',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'activated_date' => 'date',
        'sales_time' => 'date',
        'expiration_date' => 'date',
        'mileage' => 'float',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Number of days until device expiration.
     * Returns positive integer for future, 0 for today, negative for past.
     */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (! $this->expiration_date) {
            if ($this->raw_expiration && str_contains(strtolower($this->raw_expiration), 'expired')) {
                return -1;
            }

            return null;
        }

        $today = Carbon::today();
        $expiration = $this->expiration_date->copy()->startOfDay();

        return (int) $today->diffInDays($expiration, false);
    }

    /**
     * Get the expiration category: 'expired', 'expiring_soon' (<=30 days), 'active' (>30 days), or 'unknown'.
     */
    public function getExpirationStatusAttribute(): string
    {
        $days = $this->days_until_expiration;

        if ($days === null) {
            return 'unknown';
        }

        if ($days < 0) {
            return 'expired';
        }

        if ($days <= 30) {
            return 'expiring_soon';
        }

        return 'active';
    }

    /**
     * Badge UI details including labels and Tailwind color tokens.
     *
     * @return array{status: string, label: string, bg: string, text: string, border: string, dot: string, pulse: bool}
     */
    public function getExpirationBadgeAttribute(): array
    {
        $days = $this->days_until_expiration;

        if ($days === null) {
            return [
                'status' => 'unknown',
                'label' => $this->raw_expiration ?: 'No Expiration',
                'bg' => 'bg-slate-50',
                'text' => 'text-slate-600',
                'border' => 'border-slate-200',
                'dot' => 'bg-slate-400',
                'pulse' => false,
            ];
        }

        if ($days < 0) {
            $absDays = abs($days);
            $label = $days === -1 && ! $this->expiration_date
                ? 'Expired'
                : ($absDays === 1 ? 'Expired 1 day ago' : "Expired {$absDays} days ago");

            return [
                'status' => 'expired',
                'label' => $label,
                'bg' => 'bg-red-50',
                'text' => 'text-red-700 font-bold',
                'border' => 'border-red-200',
                'dot' => 'bg-red-600',
                'pulse' => false,
            ];
        }

        if ($days === 0) {
            return [
                'status' => 'expiring_soon',
                'label' => 'Expires Today',
                'bg' => 'bg-red-100',
                'text' => 'text-red-800 font-bold',
                'border' => 'border-red-300',
                'dot' => 'bg-red-600',
                'pulse' => true,
            ];
        }

        if ($days <= 30) {
            return [
                'status' => 'expiring_soon',
                'label' => "Expires in {$days} days",
                'bg' => 'bg-amber-50',
                'text' => 'text-amber-800 font-bold',
                'border' => 'border-amber-300',
                'dot' => 'bg-amber-500',
                'pulse' => true,
            ];
        }

        return [
            'status' => 'active',
            'label' => "Active ({$days} days left)",
            'bg' => 'bg-emerald-50',
            'text' => 'text-emerald-700 font-medium',
            'border' => 'border-emerald-200',
            'dot' => 'bg-emerald-500',
            'pulse' => false,
        ];
    }

    /**
     * Scope for devices expiring within given days (default 30).
     *
     * @param  Builder<Device>  $query
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): void
    {
        $today = Carbon::today()->toDateString();
        $futureDate = Carbon::today()->addDays($days)->toDateString();

        $query->whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [$today, $futureDate]);
    }

    /**
     * Scope for devices that have already expired.
     *
     * @param  Builder<Device>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $today = Carbon::today()->toDateString();

        $query->where(function (Builder $q) use ($today) {
            $q->where('expiration_date', '<', $today)
                ->orWhere(function (Builder $sub) {
                    $sub->whereNull('expiration_date')
                        ->where('raw_expiration', 'like', '%Expired%');
                });
        });
    }

    /**
     * Scope for active devices that expire beyond given days (default 30).
     *
     * @param  Builder<Device>  $query
     */
    public function scopeActive(Builder $query, int $days = 30): void
    {
        $threshold = Carbon::today()->addDays($days)->toDateString();

        $query->whereNotNull('expiration_date')
            ->where('expiration_date', '>', $threshold);
    }
}
