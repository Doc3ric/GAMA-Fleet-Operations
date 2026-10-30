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
        'driver_name',
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
        'images',
        'remarks',
        'sort_order',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'sort_order' => 'integer',
        'images' => 'array',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Get all image paths as an array.
     *
     * @return array<int, string>
     */
    public function getAllImages(): array
    {
        $list = [];
        if (is_array($this->images)) {
            $list = $this->images;
        } elseif (is_string($this->images) && ! empty($this->images)) {
            $decoded = json_decode($this->images, true);
            if (is_array($decoded)) {
                $list = $decoded;
            }
        }

        if (empty($list) && ! empty($this->image)) {
            $list = [$this->image];
        }

        return array_values(array_filter($list));
    }

    /**
     * Get all full URLs for images.
     *
     * @return array<int, string>
     */
    public function getAllImageUrls(): array
    {
        return array_values(array_map(function ($img) {
            return Storage::disk('public')->url($img);
        }, $this->getAllImages()));
    }

    /**
     * Accessor for all_images attribute
     *
     * @return array<int, string>
     */
    public function getAllImagesAttribute(): array
    {
        return $this->getAllImages();
    }

    /**
     * Accessor for all_image_urls attribute
     *
     * @return array<int, string>
     */
    public function getAllImageUrlsAttribute(): array
    {
        return $this->getAllImageUrls();
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image) {
            return Storage::disk('public')->url($this->image);
        }

        $all = $this->getAllImages();
        if (! empty($all)) {
            return Storage::disk('public')->url($all[0]);
        }

        return null;
    }

    /**
     * Delete all images belonging to this record from disk.
     */
    public function deleteAllImages(): void
    {
        foreach ($this->getAllImages() as $img) {
            Storage::disk('public')->delete($img);
        }

        if (! empty($this->image)) {
            Storage::disk('public')->delete($this->image);
        }
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
