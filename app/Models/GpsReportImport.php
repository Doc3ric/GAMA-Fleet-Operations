<?php

namespace App\Models;

use Database\Factories\GpsReportImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GpsReportImport extends Model
{
    /** @use HasFactory<GpsReportImportFactory> */
    use HasFactory;

    public const STATUS_PREVIEW = 'PREVIEW';

    public const STATUS_COMMITTED = 'COMMITTED';

    public const STATUS_FAILED = 'FAILED';

    /** @var list<string> */
    protected $fillable = [
        'filename',
        'file_path',
        'file_size',
        'mime_type',
        'total_rows',
        'matched_rows',
        'unmatched_rows',
        'duplicate_rows',
        'status',
        'import_summary',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'matched_rows' => 'integer',
        'unmatched_rows' => 'integer',
        'duplicate_rows' => 'integer',
        'import_summary' => 'array',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(GpsReportRecord::class, 'gps_report_import_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
