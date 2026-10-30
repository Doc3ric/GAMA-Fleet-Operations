<?php

namespace App\Models;

use Database\Factories\MileageEvidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MileageEvidence extends Model
{
    /** @use HasFactory<MileageEvidenceFactory> */
    use HasFactory;

    protected $table = 'mileage_evidences';

    public const TYPE_ODOMETER_PHOTO = 'odometer_photo';

    public const TYPE_VERIFIED_READING_FORM = 'verified_reading_form';

    public const TYPE_GPS_REPORT = 'gps_report';

    public const TYPE_OTHER = 'other';

    public const VALID_TYPES = [
        self::TYPE_ODOMETER_PHOTO,
        self::TYPE_VERIFIED_READING_FORM,
        self::TYPE_GPS_REPORT,
        self::TYPE_OTHER,
    ];

    /** @var list<string> */
    protected $fillable = [
        'mileage_reconciliation_id',
        'evidence_type',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'verified_by',
        'verified_at',
        'notes',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'file_size' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(MileageReconciliation::class, 'mileage_reconciliation_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        $disk = config('fleet_reconciliation.evidence_storage_disk', 'public');

        return Storage::disk($disk)->url($this->file_path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
