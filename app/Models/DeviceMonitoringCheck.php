<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceMonitoringCheck extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'device_id',
        'owner_id',
        'checked_by',
        'monitored_on',
        'checked_at',
        'device_name',
        'imei',
        'vehicle_label',
        'is_completed',
        'reopened_at',
        'reopened_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'checked_at' => 'datetime',
        'is_completed' => 'boolean',
        'reopened_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withTrashed();
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(DeviceMonitoringAction::class);
    }
}
