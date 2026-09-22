<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Long Idling Report - {{ $report->report_date->format('F j, Y') }}</title>
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
    </style>
</head>
<body>

    @php
        $recordsWithImages = $report->longIdlingRecords->filter(fn($r) => !empty($r->image) && (\Illuminate\Support\Facades\Storage::disk('public')->exists($r->image) || file_exists(\Illuminate\Support\Facades\Storage::disk('public')->path($r->image))));
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
        <div class="report-date">{{ $report->report_date->format('l, F j, Y') }}</div>
    </div>

    {{-- Summary Bar --}}
    <table class="summary-table">
        <tr>
            <td style="width: {{ $report->remarks ? '25%' : '33%' }};">
                <div class="summary-label">Report Date</div>
                <div class="summary-value">{{ $report->report_date->format('M j, Y') }}</div>
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
                    <tr><td>Device Name</td><td>{{ $record->device_name ?: '-' }}</td></tr>
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
                    $diskPath = \Illuminate\Support\Facades\Storage::disk('public')->path($record->image ?? '');
                @endphp
                @if($record->image && file_exists($diskPath))
                    <div class="screenshot-container">
                        @php
                            $imgData = base64_encode(file_get_contents($diskPath));
                            $imgMime = mime_content_type($diskPath);
                        @endphp
                        <img src="data:{{ $imgMime }};base64,{{ $imgData }}" class="screenshot-img" alt="Captured image">
                    </div>
                @endif
            </div>
        @endforeach

    @else
        {{-- High-Density Tabular Layout for large dataset or records without screenshots --}}
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 22px;" class="text-center">#</th>
                    <th style="width: 65px;">Device Name</th>
                    <th style="width: 75px;">IMEI</th>
                    <th style="width: 42px;">Model</th>
                    <th style="width: 42px;">State</th>
                    <th style="width: 48px;">Start</th>
                    <th style="width: 48px;">End</th>
                    <th style="width: 48px;">Stay Time</th>
                    <th style="width: 78px;">Coordinates</th>
                    <th>Address</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report->longIdlingRecords as $index => $record)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $record->device_name ?: '-' }}</strong>
                            @if($record->remarks)
                                <div class="row-remarks">{{ $record->remarks }}</div>
                            @endif
                        </td>
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
                <div class="record-block">
                    <div class="record-header">
                        Record #{{ $index + 1 }} &mdash; {{ $record->device_name }}
                    </div>
                    <table class="record-table">
                        <tr><td>Device Name</td><td>{{ $record->device_name ?: '-' }}</td></tr>
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
                        $diskPath = \Illuminate\Support\Facades\Storage::disk('public')->path($record->image ?? '');
                    @endphp
                    @if(file_exists($diskPath))
                        <div class="screenshot-container">
                            @php
                                $imgData = base64_encode(file_get_contents($diskPath));
                                $imgMime = mime_content_type($diskPath);
                            @endphp
                            <img src="data:{{ $imgMime }};base64,{{ $imgData }}" class="screenshot-img" alt="Captured image">
                        </div>
                    @endif
                </div>
            @endforeach
        @endif

    @endif

</body>
</html>