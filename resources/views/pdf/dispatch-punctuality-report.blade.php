<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Dispatch Punctuality & On-Time Rate Report &bull; GAMA FOMS</title>
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
            padding: 6px 10px;
            font-size: 8.5px;
        }
        .summary-label {
            color: #64748b;
            font-size: 7.5px;
            text-transform: uppercase;
        }
        .summary-val {
            font-weight: bold;
            font-size: 12px;
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
        .badge-ontime { background: #dcfce7; color: #166534; }
        .badge-delayed { background: #fee2e2; color: #991b1b; }
        .badge-pending { background: #e0f2fe; color: #0369a1; }
        .badge-cancelled { background: #f1f5f9; color: #64748b; }
    </style>
</head>
<body>
    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td style="width: 40%;">GAMA FOMS &bull; Fleet Dispatch Monitoring Module</td>
                <td style="width: 30%; text-align: center;">Generated on {{ now()->format('F d, Y h:i A') }}</td>
                <td style="width: 30%; text-align: right;">Page <span class="pagenum"></span></td>
            </tr>
        </table>
    </div>

    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="company-name">{{ config('app.company_name', 'GAMA') }} FLEET OPERATIONS</div>
                <div class="company-tagline">On-Time Dispatch Rate & Departure Punctuality Report</div>
            </td>
            <td style="vertical-align: top;">
                <div class="report-title">ON-TIME DISPATCH RATE REPORT</div>
                <div class="report-subtitle">
                    Period: {{ $dateRangeText ?? 'All Records' }}
                    @if(!empty($vehicleFilter)) &bull; Vehicle: {{ $vehicleFilter }} @endif
                </div>
            </td>
        </tr>
    </table>

    @php
        $stats = $statistics ?? [
            'total_trips' => $dispatches->count(),
            'eligible_count' => $dispatches->where('dispatch_status', '!=', 'CANCELLED')->count(),
            'on_time_count' => $dispatches->where('dispatch_status', 'ON_TIME')->count(),
            'delayed_count' => $dispatches->where('dispatch_status', 'DELAYED')->count(),
            'pending_count' => $dispatches->where('dispatch_status', 'PENDING')->count(),
            'cancelled_count' => $dispatches->where('dispatch_status', 'CANCELLED')->count(),
            'on_time_rate' => 0.0,
        ];
    @endphp

    <table class="summary-bar">
        <tr>
            <td style="border-right: 2px solid #e2e8f0; background: #eff6ff;">
                <span class="summary-label">On-Time Dispatch Rate:</span><br/>
                <span class="summary-val" style="color: #1d4ed8; font-size: 14px;">{{ number_format($stats['on_time_rate'], 1) }}%</span>
            </td>
            <td>
                <span class="summary-label">On-Time Departures:</span><br/>
                <span class="summary-val" style="color: #166534;">{{ $stats['on_time_count'] }}</span>
            </td>
            <td>
                <span class="summary-label">Delayed Departures:</span><br/>
                <span class="summary-val" style="color: #dc2626;">{{ $stats['delayed_count'] }}</span>
            </td>
            <td>
                <span class="summary-label">Pending Departures:</span><br/>
                <span class="summary-val" style="color: #0284c7;">{{ $stats['pending_count'] }}</span>
            </td>
            <td>
                <span class="summary-label">Cancelled:</span><br/>
                <span class="summary-val" style="color: #64748b;">{{ $stats['cancelled_count'] }}</span>
            </td>
            <td class="text-right">
                <span class="summary-label">Total Eligible Trips:</span><br/>
                <span class="summary-val">{{ $stats['eligible_count'] }}</span>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 9%;">Trip #</th>
                <th style="width: 8%;">Date</th>
                <th style="width: 14%;">Vehicle / Plate</th>
                <th style="width: 12%;">Driver</th>
                <th style="width: 12%;">Starting Point</th>
                <th style="width: 14%;">Destination</th>
                <th style="width: 10%;">Planned Dep.</th>
                <th style="width: 10%;">Actual Dep.</th>
                <th style="width: 6%;" class="text-center">Delay</th>
                <th style="width: 7%;" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dispatches as $dispatch)
                @php
                    $vehicle = $dispatch->vehicle;
                    $eqpt = trim((string) ($vehicle?->equipment_code ?? ''));
                    $plate = trim((string) ($vehicle?->plate_number ?? ''));
                    $vehicleLabel = ($eqpt && $plate) ? "{$eqpt} - {$plate}" : ($eqpt ?: ($plate ?: '—'));
                    $statusClass = match($dispatch->dispatch_status) {
                        'ON_TIME' => 'badge-ontime',
                        'DELAYED' => 'badge-delayed',
                        'PENDING' => 'badge-pending',
                        default => 'badge-cancelled',
                    };
                @endphp
                <tr>
                    <td style="font-weight: bold; font-family: monospace;">{{ $dispatch->trip_number }}</td>
                    <td>{{ $dispatch->trip_date?->format('Y-m-d') }}</td>
                    <td><strong>{{ $vehicleLabel }}</strong></td>
                    <td>{{ $dispatch->driver_name ?: '—' }}</td>
                    <td>{{ $dispatch->starting_point }}</td>
                    <td>{{ $dispatch->destination }}</td>
                    <td>{{ $dispatch->planned_departure_time?->format('H:i') }}</td>
                    <td>{{ $dispatch->actual_departure_time ? $dispatch->actual_departure_time->format('H:i') : '—' }}</td>
                    <td class="text-center" style="{{ $dispatch->delay_minutes > 0 ? 'color: #dc2626; font-weight: bold;' : '' }}">
                        {{ $dispatch->delay_minutes > 0 ? $dispatch->delay_minutes . 'm' : '—' }}
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $statusClass }}">{{ $dispatch->dispatch_status }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 20px; color: #64748b;">
                        No dispatch records found matching the specified criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
