<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceMonitoringAction extends Model
{
    public const ACTION_COMPLETED = 'completed';

    public const ACTION_REOPENED = 'reopened';

    /** @var list<string> */
    protected $fillable = [
        'device_monitoring_check_id',
        'performed_by',
        'action',
        'performed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'performed_at' => 'datetime',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(DeviceMonitoringCheck::class, 'device_monitoring_check_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
