<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Mileage Reconciliation Report &bull; GAMA FOMS</title>
    <style>
        * { box-sizing: border-box; }
        @page {
            margin: 10mm 10mm 12mm 10mm;
            size: landscape;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 8px;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }

        .page-footer {
            position: fixed;
            bottom: -8mm;
            left: 0;
            right: 0;
            border-top: 1px solid #cbd5e1;
            padding-top: 3px;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-table td {
            font-size: 7px;
            color: #64748b;
            padding: 0;
        }
        .pagenum:before {
            content: counter(page);
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        .company-tagline {
            font-size: 8px;
            color: #64748b;
        }
        .report-title {
            font-size: 13px;
            font-weight: bold;
            color: #1e3a8a;
            text-align: right;
        }
        .report-subtitle {
            font-size: 8px;
            color: #64748b;
            text-align: right;
        }

        .summary-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .summary-bar td {
            padding: 5px 8px;
            font-size: 8px;
        }
        .summary-label {
            color: #64748b;
            font-size: 7.5px;
            text-transform: uppercase;
        }
        .summary-val {
            font-weight: bold;
            color: #0f172a;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .data-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            text-align: left;
            padding: 4px 5px;
            border: 1px solid #334155;
            white-space: nowrap;
        }
        .data-table td {
            font-size: 7.5px;
            padding: 3.5px 5px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 6.5px;
            font-weight: bold;
        }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-draft { background: #f1f5f9; color: #475569; }
        .badge-submitted { background: #e0f2fe; color: #0369a1; }
        .badge-review { background: #fef3c7; color: #92400e; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .badge-exception { background: #fee2e2; color: #b91c1c; font-weight: bold; }
        .badge-normal { background: #f0fdf4; color: #15803d; }
    </style>
</head>
<body>
    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td style="width: 40%;">GAMA FOMS &bull; Mileage Reconciliation & Punctuality System</td>
                <td style="width: 30%; text-align: center;">Generated on {{ now()->format('F d, Y h:i A') }}</td>
                <td style="width: 30%; text-align: right;">Page <span class="pagenum"></span></td>
            </tr>
        </table>
    </div>

    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="company-name">{{ config('app.company_name', 'GAMA') }} FLEET OPERATIONS</div>
                <div class="company-tagline">Mileage Reconciliation & Variance Monitoring Report</div>
            </td>
            <td style="vertical-align: top;">
                <div class="report-title">MILEAGE RECONCILIATION SUMMARY</div>
                <div class="report-subtitle">
                    Period: {{ $dateRangeText ?? 'All Records' }}
                    @if(!empty($vehicleFilter)) &bull; Vehicle: {{ $vehicleFilter }} @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="summary-bar">
        <tr>
            <td>
                <span class="summary-label">Total Reconciliations:</span><br/>
                <span class="summary-val">{{ $records->count() }}</span>
            </td>
            <td>
                <span class="summary-label">Approved:</span><br/>
                <span class="summary-val" style="color: #166534;">{{ $records->where('status', 'APPROVED')->count() }}</span>
            </td>
            <td>
                <span class="summary-label">Pending Review:</span><br/>
                <span class="summary-val" style="color: #b45309;">{{ $records->whereIn('status', ['SUBMITTED', 'FOR_REVIEW'])->count() }}</span>
            </td>
            <td>
                <span class="summary-label">With Exceptions:</span><br/>
                <span class="summary-val" style="color: #dc2626;">{{ $records->where('has_exceptions', true)->count() }}</span>
            </td>
            <td class="text-right">
                <span class="summary-label">Total Planned KM:</span><br/>
                <span class="summary-val">{{ number_format($records->sum('planned_distance_km'), 2) }} KM</span>
            </td>
            <td class="text-right">
                <span class="summary-label">Total Actual KM:</span><br/>
                <span class="summary-val">{{ number_format($records->sum('actual_odometer_km'), 2) }} KM</span>
            </td>
            <td class="text-right">
                <span class="summary-label">Total Variance:</span><br/>
                <span class="summary-val" style="{{ $records->sum('variance_km') > 0 ? 'color:#dc2626;' : 'color:#166534;' }}">
                    {{ ($records->sum('variance_km') > 0 ? '+' : '') . number_format($records->sum('variance_km'), 2) }} KM
                </span>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 7%;">Rec #</th>
                <th style="width: 7%;">Trip #</th>
                <th style="width: 7%;">Date</th>
                <th style="width: 12%;">Vehicle / Plate</th>
                <th style="width: 5%;" class="text-center">GPS</th>
                <th style="width: 13%;">Destination</th>
                <th style="width: 7%;" class="text-right">Planned KM</th>
                <th style="width: 7%;" class="text-right">Start Odo</th>
                <th style="width: 7%;" class="text-right">End Odo</th>
                <th style="width: 7%;" class="text-right">Actual KM</th>
                <th style="width: 7%;" class="text-right">GPS KM</th>
                <th style="width: 7%;" class="text-right">Variance</th>
                <th style="width: 7%;" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $rec)
                @php
                    $vehicle = $rec->vehicle;
                    $eqpt = trim((string) ($vehicle?->equipment_code ?? ''));
                    $plate = trim((string) ($vehicle?->plate_number ?? ''));
                    $vehicleLabel = ($eqpt && $plate) ? "{$eqpt} - {$plate}" : ($eqpt ?: ($plate ?: '—'));
                    $statusClass = match($rec->status) {
                        'APPROVED' => 'badge-approved',
                        'SUBMITTED' => 'badge-submitted',
                        'FOR_REVIEW' => 'badge-review',
                        'REJECTED' => 'badge-rejected',
                        default => 'badge-draft',
                    };
                @endphp
                <tr>
                    <td style="font-weight: bold; font-family: monospace;">{{ $rec->reconciliation_number }}</td>
                    <td style="font-family: monospace;">{{ $rec->dispatch?->trip_number ?? '—' }}</td>
                    <td>{{ $rec->trip_date?->format('Y-m-d') }}</td>
                    <td><strong>{{ $vehicleLabel }}</strong></td>
                    <td class="text-center">
                        <span class="badge {{ $rec->is_gps_vehicle ? 'badge-approved' : 'badge-draft' }}">
                            {{ $rec->is_gps_vehicle ? 'GPS' : 'NON-GPS' }}
                        </span>
                    </td>
                    <td>{{ $rec->dispatch?->destination ?? '—' }}</td>
                    <td class="text-right">{{ number_format($rec->planned_distance_km, 2) }}</td>
                    <td class="text-right">{{ $rec->starting_odometer !== null ? number_format($rec->starting_odometer, 1) : '—' }}</td>
                    <td class="text-right">{{ $rec->ending_odometer !== null ? number_format($rec->ending_odometer, 1) : '—' }}</td>
                    <td class="text-right"><strong>{{ number_format($rec->actual_odometer_km, 2) }}</strong></td>
                    <td class="text-right">{{ $rec->gps_reported_km !== null ? number_format($rec->gps_reported_km, 2) : '—' }}</td>
                    <td class="text-right" style="{{ $rec->variance_km > 0 ? 'color: #dc2626; font-weight: bold;' : '' }}">
                        {{ ($rec->variance_km > 0 ? '+' : '') . number_format($rec->variance_km, 2) }}
                        @if($rec->variance_percentage !== null)
                            <small>({{ ($rec->variance_percentage > 0 ? '+' : '') . number_format($rec->variance_percentage, 1) }}%)</small>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $statusClass }}">{{ $rec->status }}</span>
                        @if($rec->has_exceptions)
                            <br/><span class="badge badge-exception" style="margin-top: 2px;">EXCEPTION</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center" style="padding: 20px; color: #64748b;">
                        No reconciliation records found matching the specified criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
