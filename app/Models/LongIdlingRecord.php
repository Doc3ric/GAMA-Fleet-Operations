<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LongIdlingRecord extends Model
{
    protected $fillable = [
        'report_id',
        'device_name',
        'imei',
        'model',
        'state',
        'start_time',
        'end_time',
        'stay_time',
        'latitude',
        'longitude',
        'address',
        'image',
        'remarks',
        'sort_order',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'sort_order' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return Storage::disk('public')->url($this->image);
    }

    public function getCoordinatesAttribute(): ?string
    {
        if (! $this->latitude || ! $this->longitude) {
            return null;
        }

        return "{$this->latitude}, {$this->longitude}";
    }

    public static function calculateStayTime(?string $start, ?string $end): ?string
    {
        if (! $start || ! $end) {
            return null;
        }

        try {
            $start = trim($start);
            $end = trim($end);

            $cleanStart = preg_match('/^(\d{1,2}:\d{2}:\d{2})/', $start, $m1) ? $m1[1] : (preg_match('/^(\d{1,2}:\d{2})/', $start, $m1) ? $m1[1] : $start);
            $cleanEnd = preg_match('/^(\d{1,2}:\d{2}:\d{2})/', $end, $m2) ? $m2[1] : (preg_match('/^(\d{1,2}:\d{2})/', $end, $m2) ? $m2[1] : $end);

            $startFormat = substr_count($cleanStart, ':') === 2 ? 'H:i:s' : 'H:i';
            $endFormat = substr_count($cleanEnd, ':') === 2 ? 'H:i:s' : 'H:i';

            $startCarbon = Carbon::createFromFormat($startFormat, $cleanStart);
            $endCarbon = Carbon::createFromFormat($endFormat, $cleanEnd);

            if ($endCarbon->lessThan($startCarbon)) {
                $endCarbon->addDay();
            }

            $diff = $startCarbon->diff($endCarbon);
            $totalHours = ($diff->days * 24) + $diff->h;

            return sprintf('%02d:%02d:%02d', $totalHours, $diff->i, $diff->s);
        } catch (\Exception $e) {
            return null;
        }
    }
}
