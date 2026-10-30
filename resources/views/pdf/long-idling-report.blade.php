<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Long Idling Report - {{ $dateRangeLabel ?? $report->report_date->format('F j, Y') }}</title>
    <style>
        * { box-sizing: border-box; }
        @page {
            margin: 10mm 10mm 12mm 10mm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }

        /* Fixed Page Footer */
        .page-footer {
            position: fixed;
            bottom: -8mm;
            left: 0;
            right: 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 3px;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-table td {
            font-size: 7.5px;
            color: #94a3b8;
            padding: 0;
        }
        .pagenum:before {
            content: counter(page);
        }

        /* Header */
        .header {
            border-bottom: 2.5px solid #1e40af;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .report-title {
            font-size: 15px;
            font-weight: bold;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-date {
            font-size: 9px;
            color: #475569;
            margin-top: 2px;
        }

        /* Summary Bar */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            margin-bottom: 10px;
        }
        .summary-table td {
            padding: 6px 10px;
            text-align: center;
            vertical-align: middle;
            border-right: 1px solid #e2e8f0;
        }
        .summary-table td:last-child {
            border-right: none;
        }
        .summary-label {
            font-size: 7px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
        }
        .summary-value {
            font-size: 11px;
            font-weight: bold;
            color: #1e40af;
            margin-top: 1px;
        }

        /* Tabular Data View */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 12px;
        }
        .data-table thead {
            display: table-header-group;
        }
        .data-table tr {
            page-break-inside: avoid;
        }
        .data-table th {
            background: #1e40af;
            color: #ffffff;
            padding: 5px 4px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid #1e3a8a;
            text-align: left;
        }
        .data-table td {
            padding: 4px;
            border: 1px solid #cbd5e1;
            font-size: 7.5px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .data-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .text-center { text-align: center; }
        .stay-time-cell {
            font-weight: bold;
            color: #1e40af;
        }
        .mono {
            font-family: Courier, monospace;
            font-size: 7px;
        }
        .row-remarks {
            font-size: 6.5px;
            color: #64748b;
            margin-top: 1px;
            font-style: italic;
        }

        /* Section Divider */
        .section-break {
            page-break-before: always;
        }
        .section-header {
            background: #1e40af;
            color: #ffffff;
            padding: 6px 10px;
            margin-bottom: 10px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-subtitle {
            font-size: 7.5px;
            color: #bfdbfe;
            margin-top: 1px;
        }

        /* Record Block (Cards) */
        .record-block {
            margin-bottom: 14px;
            border: 1px solid #cbd5e1;
            overflow: hidden;
            page-break-inside: avoid;
            background: #ffffff;
        }
        .record-header {
            background: #1e40af;
            color: #ffffff;
            padding: 5px 10px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .record-table {
            width: 100%;
            border-collapse: collapse;
        }
        .record-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 8.5px;
        }
        .record-table td:first-child {
            width: 25%;
            font-weight: bold;
            color: #475569;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
        }
        .record-table td:last-child {
            width: 75%;
            color: #1e293b;
        }
        .record-table tr:last-child td {
            border-bottom: none;
        }

        /* Full-Width Screenshot Container & Image */
        .screenshot-container {
            width: 100%;
            background: #ffffff;
            border-top: 1px solid #cbd5e1;
            padding: 0;
            margin: 0;
            line-height: 0;
            text-align: center;
        }
        .screenshot-img {
            width: 100%;
            max-width: 100%;
            max-height: 400pt;
            height: auto;
            display: block;
            margin: 0 auto;
            border: none;
        }

        /* Multiple Screenshots Grid Table */
        .screenshots-multi-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #cbd5e1;
            background: #ffffff;
            margin: 0;
            table-layout: fixed;
        }
        .screenshots-multi-table td {
            padding: 4px;
            vertical-align: top;
            text-align: center;
            border: none;
            background: #ffffff;
        }
        .screenshot-card-cell {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 3px;
        }
        .screenshot-caption {
            font-size: 7.5px;
            font-weight: bold;
            color: #475569;
            background: #f1f5f9;
            padding: 2px 4px;
            margin-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .screenshot-multi-img {
            max-width: 100%;
            max-height: 250pt;
            height: auto;
            display: block;
            margin: 0 auto;
            border: 1px solid #cbd5e1;
        }
    </style>
</head>
<body>

    @php
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        /**
         * Resize and compress an image using GD, returning a base64 data URI.
         * Downscales to max 1200px wide/tall and compresses as JPEG at 70% quality.
         * Falls back to raw base64 if GD is not available or the image type is unsupported.
         *
         * @param string $diskPath Absolute path to the source image file
         * @return array{mime: string, data: string} mime type and base64-encoded data
         */
        $compressImageForPdf = function(string $diskPath): array {
            $maxDimension = 1200;
            $jpegQuality  = 70;

            $mime = mime_content_type($diskPath) ?: 'image/jpeg';

            if (!extension_loaded('gd')) {
                return ['mime' => $mime, 'data' => base64_encode(file_get_contents($diskPath))];
            }

            try {
                [$srcW, $srcH, $type] = getimagesize($diskPath);

                // Only resize if larger than max dimension
                if ($srcW <= $maxDimension && $srcH <= $maxDimension) {
                    // Still re-encode as JPEG to shrink file size
                    $src = match ($type) {
                        IMAGETYPE_JPEG => imagecreatefromjpeg($diskPath),
                        IMAGETYPE_PNG  => imagecreatefrompng($diskPath),
                        IMAGETYPE_GIF  => imagecreatefromgif($diskPath),
                        IMAGETYPE_WEBP => imagecreatefromwebp($diskPath),
                        default        => null,
                    };

                    if (!$src) {
                        return ['mime' => $mime, 'data' => base64_encode(file_get_contents($diskPath))];
                    }

                    ob_start();
                    imagejpeg($src, null, $jpegQuality);
                    $data = ob_get_clean();
                    imagedestroy($src);

                    return ['mime' => 'image/jpeg', 'data' => base64_encode($data)];
                }

                // Scale down proportionally
                $ratio  = min($maxDimension / $srcW, $maxDimension / $srcH);
                $dstW   = (int) round($srcW * $ratio);
                $dstH   = (int) round($srcH * $ratio);

                $src = match ($type) {
                    IMAGETYPE_JPEG => imagecreatefromjpeg($diskPath),
                    IMAGETYPE_PNG  => imagecreatefrompng($diskPath),
                    IMAGETYPE_GIF  => imagecreatefromgif($diskPath),
                    IMAGETYPE_WEBP => imagecreatefromwebp($diskPath),
                    default        => null,
                };

                if (!$src) {
                    return ['mime' => $mime, 'data' => base64_encode(file_get_contents($diskPath))];
                }

                $dst = imagecreatetruecolor($dstW, $dstH);

                // Preserve transparency for PNG/GIF source images
                if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF])) {
                    imagefilledrectangle($dst, 0, 0, $dstW, $dstH, imagecolorallocate($dst, 255, 255, 255));
                }

                imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
                imagedestroy($src);

                ob_start();
                imagejpeg($dst, null, $jpegQuality);
                $data = ob_get_clean();
                imagedestroy($dst);

                return ['mime' => 'image/jpeg', 'data' => base64_encode($data)];

            } catch (\Throwable $e) {
                // Graceful fallback: embed the raw file
                return ['mime' => $mime, 'data' => base64_encode(file_get_contents($diskPath))];
            }
        };

        $getDiskImages = function($rec) {
            $imgs = method_exists($rec, 'getAllImages') ? $rec->getAllImages() : (!empty($rec->images) ? (is_array($rec->images) ? $rec->images : json_decode($rec->images, true)) : (!empty($rec->image) ? [$rec->image] : []));
            $valid = [];
            foreach ((array)$imgs as $img) {
                if (!empty($img)) {
                    $diskPath = \Illuminate\Support\Facades\Storage::disk('public')->path($img);
                    if (file_exists($diskPath)) {
                        $valid[] = [
                            'path' => $img,
                            'diskPath' => $diskPath,
                        ];
                    }
                }
            }
            return $valid;
        };

        $recordsWithImages = $report->longIdlingRecords->filter(fn($r) => !empty($getDiskImages($r)));
        $hasAnyImages = $recordsWithImages->isNotEmpty();
        $allHaveImages = $recordsWithImages->count() === $report->longIdlingRecords->count() && $report->longIdlingRecords->count() > 0;
        $useCardLayout = $allHaveImages && $report->longIdlingRecords->count() <= 5;
    @endphp

    {{-- Fixed Page Footer --}}
    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td style="text-align: left; width: 40%;">Prepared by: <strong style="color: #475569;">{{ $preparedBy }}</strong></td>
                <td style="text-align: center; width: 35%;">Generated: {{ now()->format('F j, Y g:i A') }}</td>
                <td style="text-align: right; width: 25%;">Page <span class="pagenum"></span></td>
            </tr>
        </table>
    </div>

    {{-- Header --}}
    <div class="header">
        <div class="report-title">Long Idling Report</div>
        <div class="report-date">{{ $dateRangeSubtitle ?? $report->report_date->format('l, F j, Y') }}</div>
        @if(!empty($filterLabel))
        <div style="font-size: 8px; color: #dc2626; margin-top: 2px; font-weight: bold;">
            Filtered by: &ldquo;{{ $filterLabel }}&rdquo; &mdash; {{ $report->longIdlingRecords->count() }} {{ \Illuminate\Support\Str::plural('record', $report->longIdlingRecords->count()) }} shown
        </div>
        @elseif(!empty($searchFilter))
        <div style="font-size: 8px; color: #dc2626; margin-top: 2px; font-weight: bold;">
            Filtered by: &ldquo;{{ $searchFilter }}&rdquo; &mdash; {{ $report->longIdlingRecords->count() }} {{ \Illuminate\Support\Str::plural('record', $report->longIdlingRecords->count()) }} shown
        </div>
        @endif
    </div>

    {{-- Summary Bar --}}
    <table class="summary-table">
        <tr>
            <td style="width: {{ $report->remarks ? '25%' : '33%' }};">
                <div class="summary-label">{{ !empty($isMultiDate) ? 'Date Range' : 'Report Date' }}</div>
                <div class="summary-value">{{ $dateRangeLabel ?? $report->report_date->format('M j, Y') }}</div>
            </td>
            <td style="width: {{ $report->remarks ? '25%' : '33%' }};">
                <div class="summary-label">Total Vehicles</div>
                <div class="summary-value">{{ $report->longIdlingRecords->count() }}</div>
            </td>
            <td style="width: {{ $report->remarks ? '25%' : '34%' }};">
                <div class="summary-label">Status</div>
                <div class="summary-value" style="color: {{ $report->isCompleted() ? '#059669' : '#d97706' }};">
                    {{ strtoupper($report->status) }}
                </div>
            </td>
            @if($report->remarks)
            <td style="width: 25%; text-align: left; padding-left: 10px;">
                <div class="summary-label">Remarks</div>
                <div style="font-size: 8px; color: #475569; margin-top: 1px;">{{ $report->remarks }}</div>
            </td>
            @endif
        </tr>
    </table>

    @if($report->longIdlingRecords->isEmpty())
        <div style="padding: 20px; text-align: center; color: #64748b; font-size: 10px; border: 1px dashed #cbd5e1;">
            No idling records found for this report.
        </div>
    @elseif($useCardLayout)
        {{-- All records have screenshots & count <= 5: Direct Visual Card Layout --}}
        @foreach($report->longIdlingRecords as $index => $record)
            <div class="record-block">
                <div class="record-header">
                    Record #{{ $index + 1 }} &mdash; {{ $record->device_name }}
                </div>
                <table class="record-table">
                    @if(!empty($isMultiDate) && $record->report?->report_date)
                    <tr><td>Date</td><td><strong>{{ $record->report->report_date->format('M j, Y') }}</strong></td></tr>
                    @endif
                    <tr><td>Device Name</td><td>{{ $record->device_name ?: '-' }}</td></tr>
                    @if($record->driver_name)
                    <tr><td>Driver Name</td><td>{{ $record->driver_name }}</td></tr>
                    @endif
                    <tr><td>IMEI</td><td>{{ $record->imei ?: '-' }}</td></tr>
                    <tr><td>Model</td><td>{{ $record->model ?: '-' }}</td></tr>
                    @if($record->state)
                    <tr><td>State</td><td>{{ $record->state }}</td></tr>
                    @endif
                    <tr><td>Start Time</td><td>{{ $record->start_time ?: '-' }}</td></tr>
                    <tr><td>End Time</td><td>{{ $record->end_time ?: '-' }}</td></tr>
                    <tr><td>Stay Time</td><td style="font-weight: bold; color: #1e40af;">{{ $record->stay_time ?: '-' }}</td></tr>
                    <tr><td>Coordinates</td><td>{{ $record->coordinates ?: '-' }}</td></tr>
                    <tr><td>Address</td><td>{{ $record->address ?: '-' }}</td></tr>
                    @if($record->remarks)
                    <tr><td>Remarks</td><td>{{ $record->remarks }}</td></tr>
                    @endif
                </table>

                @php
                    $diskImages = $getDiskImages($record);
                @endphp
                @if(!empty($diskImages))
                    @if(count($diskImages) === 1)
                        @php
                            $imgItem = $diskImages[0];
                            $compressed = $compressImageForPdf($imgItem['diskPath']);
                            $imgData = $compressed['data'];
                            $imgMime = $compressed['mime'];
                        @endphp
                        <div class="screenshot-container">
                            <img src="data:{{ $imgMime }};base64,{{ $imgData }}" class="screenshot-img" alt="Captured image">
                        </div>
                    @else
                        @php
                            $chunks = array_chunk($diskImages, 2);
                        @endphp
                        <table class="screenshots-multi-table">
                            @foreach($chunks as $chunk)
                                <tr>
                                    @foreach($chunk as $cIndex => $imgItem)
                                        @php
                                            $compressed = $compressImageForPdf($imgItem['diskPath']);
                                            $imgData = $compressed['data'];
                                            $imgMime = $compressed['mime'];
                                            $currentNum = ($loop->parent->index * 2) + $cIndex + 1;
                                        @endphp
                                        <td style="width: 50%;">
                                            <div class="screenshot-card-cell">
                                                <div class="screenshot-caption">Screenshot {{ $currentNum }} of {{ count($diskImages) }}</div>
                                                <img src="data:{{ $imgMime }};base64,{{ $imgData }}" class="screenshot-multi-img" alt="Captured image {{ $currentNum }}">
                                            </div>
                                        </td>
                                    @endforeach
                                    @if(count($chunk) === 1)
                                        <td style="width: 50%;"></td>
                                    @endif
                                </tr>
                            @endforeach
                        </table>
                    @endif
                @endif
            </div>
        @endforeach

    @else
        {{-- High-Density Tabular Layout for large dataset or records without screenshots --}}
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 20px;" class="text-center">#</th>
                    @if(!empty($isMultiDate))
                    <th style="width: 50px;">Date</th>
                    @endif
                    <th style="width: {{ !empty($isMultiDate) ? '55px' : '60px' }};">Device Name</th>
                    <th style="width: {{ !empty($isMultiDate) ? '60px' : '65px' }};">Driver Name</th>
                    <th style="width: {{ !empty($isMultiDate) ? '65px' : '70px' }};">IMEI</th>
                    <th style="width: 35px;">Model</th>
                    <th style="width: 35px;">State</th>
                    <th style="width: 42px;">Start</th>
                    <th style="width: 42px;">End</th>
                    <th style="width: 42px;">Stay Time</th>
                    <th style="width: 68px;">Coordinates</th>
                    <th>Address</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report->longIdlingRecords as $index => $record)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        @if(!empty($isMultiDate))
                        <td style="font-weight: bold; color: #475569;">{{ $record->report?->report_date?->format('M j, Y') ?? '-' }}</td>
                        @endif
                        <td>
                            <strong>{{ $record->device_name ?: '-' }}</strong>
                            @if($record->remarks)
                                <div class="row-remarks">{{ $record->remarks }}</div>
                            @endif
                        </td>
                        <td>{{ $record->driver_name ?: '-' }}</td>
                        <td>{{ $record->imei ?: '-' }}</td>
                        <td>{{ $record->model ?: '-' }}</td>
                        <td>{{ $record->state ?: '-' }}</td>
                        <td>{{ $record->start_time ?: '-' }}</td>
                        <td>{{ $record->end_time ?: '-' }}</td>
                        <td class="stay-time-cell">{{ $record->stay_time ?: '-' }}</td>
                        <td class="mono">{{ $record->coordinates ?: '-' }}</td>
                        <td>{{ $record->address ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($hasAnyImages)
            {{-- Visual Evidence Section for records with screenshots attached --}}
            <div class="section-break"></div>
            <div class="section-header">
                <div class="section-title">Visual Evidence & Captured Screenshots</div>
                <div class="section-subtitle">Photographic and GPS verification for flagged long idling incidents ({{ $recordsWithImages->count() }} {{ \Illuminate\Support\Str::plural('record', $recordsWithImages->count()) }} with screenshots)</div>
            </div>

            @foreach($recordsWithImages as $index => $record)
                @php
                    $diskImages = $getDiskImages($record);
                @endphp
                <div class="record-block">
                    <div class="record-header">
                        Record #{{ $index + 1 }} &mdash; {{ $record->device_name }}
                        @if(count($diskImages) > 1)
                            <span style="float: right; font-size: 7.5px; opacity: 0.9; text-transform: none; font-weight: normal;">
                                ({{ count($diskImages) }} Screenshots attached)
                            </span>
                        @endif
                    </div>
                    <table class="record-table">
                        @if(!empty($isMultiDate) && $record->report?->report_date)
                        <tr><td>Date</td><td><strong>{{ $record->report->report_date->format('M j, Y') }}</strong></td></tr>
                        @endif
                        <tr><td>Device Name</td><td>{{ $record->device_name ?: '-' }}</td></tr>
                        @if($record->driver_name)
                        <tr><td>Driver Name</td><td>{{ $record->driver_name }}</td></tr>
                        @endif
                        <tr><td>IMEI</td><td>{{ $record->imei ?: '-' }}</td></tr>
                        <tr><td>Model</td><td>{{ $record->model ?: '-' }}</td></tr>
                        @if($record->state)
                        <tr><td>State</td><td>{{ $record->state }}</td></tr>
                        @endif
                        <tr><td>Start Time</td><td>{{ $record->start_time ?: '-' }}</td></tr>
                        <tr><td>End Time</td><td>{{ $record->end_time ?: '-' }}</td></tr>
                        <tr><td>Stay Time</td><td style="font-weight: bold; color: #1e40af;">{{ $record->stay_time ?: '-' }}</td></tr>
                        <tr><td>Coordinates</td><td>{{ $record->coordinates ?: '-' }}</td></tr>
                        <tr><td>Address</td><td>{{ $record->address ?: '-' }}</td></tr>
                        @if($record->remarks)
                        <tr><td>Remarks</td><td>{{ $record->remarks }}</td></tr>
                        @endif
                    </table>

                    @if(!empty($diskImages))
                        @if(count($diskImages) === 1)
                            @php
                                $imgItem = $diskImages[0];
                                $compressed = $compressImageForPdf($imgItem['diskPath']);
                                $imgData = $compressed['data'];
                                $imgMime = $compressed['mime'];
                            @endphp
                            <div class="screenshot-container">
                                <img src="data:{{ $imgMime }};base64,{{ $imgData }}" class="screenshot-img" alt="Captured image">
                            </div>
                        @else
                            @php
                                $chunks = array_chunk($diskImages, 2);
                            @endphp
                            <table class="screenshots-multi-table">
                                @foreach($chunks as $chunk)
                                    <tr>
                                        @foreach($chunk as $cIndex => $imgItem)
                                            @php
                                                $compressed = $compressImageForPdf($imgItem['diskPath']);
                                                $imgData = $compressed['data'];
                                                $imgMime = $compressed['mime'];
                                                $currentNum = ($loop->parent->index * 2) + $cIndex + 1;
                                            @endphp
                                            <td style="width: 50%;">
                                                <div class="screenshot-card-cell">
                                                    <div class="screenshot-caption">Screenshot {{ $currentNum }} of {{ count($diskImages) }}</div>
                                                    <img src="data:{{ $imgMime }};base64,{{ $imgData }}" class="screenshot-multi-img" alt="Captured image {{ $currentNum }}">
                                                </div>
                                            </td>
                                        @endforeach
                                        @if(count($chunk) === 1)
                                            <td style="width: 50%;"></td>
                                        @endif
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    @endif
                </div>
            @endforeach
        @endif

    @endif

</body>
</html>