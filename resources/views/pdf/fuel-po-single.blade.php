<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Fuel PO Record #{{ $fuelPo->id }} &bull; GAMA FOMS</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 12mm; size: portrait; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
        }
        .company-tagline { font-size: 9px; color: #64748b; }
        .report-title { font-size: 14px; font-weight: bold; color: #0f172a; }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 12px;
            margin-bottom: 6px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .info-table td {
            padding: 4px 6px;
            font-size: 9px;
            border: 1px solid #e2e8f0;
        }
        .info-label {
            background: #f8fafc;
            font-weight: bold;
            color: #475569;
            width: 25%;
        }
        .info-value { width: 25%; color: #0f172a; }
        .legs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .legs-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            padding: 5px 6px;
            text-align: left;
        }
        .legs-table td {
            padding: 4px 6px;
            font-size: 8px;
            border: 1px solid #e2e8f0;
        }
        .checklist-box {
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 10px;
            border: 1px solid #cbd5e1;
        }
        .checklist-checked { background: #f0fdf4; border-color: #86efac; color: #166534; }
        .checklist-unchecked { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="company-name">GAMA</div>
                <div class="company-tagline">Fleet Operations Management System</div>
            </td>
            <td style="text-align: right;">
                <div class="report-title">FUEL PURCHASE ORDER RECORD</div>
                <div style="font-size: 9px; color: #64748b;">Record ID: #{{ $fuelPo->id }} &bull; Date: {{ $fuelPo->itinerary_date->format('F d, Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Vehicle Master & Trip Profile</div>
    @php
        $v = $fuelPo->vehicle;
        $avgConsumption = $v?->average_consumption;
        $fuelLiters = $fuelPo->fuel_liters;
    @endphp
    <table class="info-table">
        <tr>
            <td class="info-label">Equipment Code:</td>
            <td class="info-value" style="font-weight: bold;">{{ $v?->equipment_code ?? '—' }}</td>
            <td class="info-label">Plate Number:</td>
            <td class="info-value">{{ $v?->plate_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">Model:</td>
            <td class="info-value">{{ $v?->model ?? '—' }}</td>
            <td class="info-label">Driver / Operator:</td>
            <td class="info-value">{{ $fuelPo->driver_name }}</td>
        </tr>
        <tr>
            <td class="info-label">User / Department:</td>
            <td class="info-value">{{ $v?->user ?? '—' }}</td>
            <td class="info-label">Project Code:</td>
            <td class="info-value">{{ $v?->project_code ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">Average Consumption:</td>
            <td class="info-value">{{ $avgConsumption !== null ? number_format($avgConsumption, 2) . ' KM/L' : 'Not set' }}</td>
            <td class="info-label">Status:</td>
            <td class="info-value" style="font-weight: bold;">{{ $fuelPo->status }}</td>
        </tr>
    </table>

    <div class="section-title">Fuel Requirements & Checklist State</div>
    <table class="info-table">
        <tr>
            <td class="info-label">Total Road Distance:</td>
            <td class="info-value" style="font-weight: bold;">{{ number_format($fuelPo->total_distance, 2) }} KM</td>
            <td class="info-label">Liter for PO:</td>
            <td class="info-value" style="font-weight: bold; color: #1e3a8a; font-size: 10px;">
                {{ $fuelLiters !== null ? ((float) $fuelLiters == round($fuelLiters) ? number_format($fuelLiters, 0) : number_format($fuelLiters, 2)) . ' Liters' : '—' }}
            </td>
        </tr>
        <tr>
            <td class="info-label">PO Checklist State:</td>
            <td class="info-value" colspan="3">
                @if($fuelPo->po_checked)
                    <span style="font-weight: bold; color: #166534;">&#9745; CHECKED / COMPLETED</span>
                    <span style="font-size: 8px; color: #64748b;">(Checked by {{ $fuelPo->poChecker?->name ?? 'User' }} on {{ $fuelPo->po_checked_at?->format('M d, Y h:i A') }})</span>
                @else
                    <span style="font-weight: bold; color: #b45309;">&#9744; UNCHECKED (Pending PO Verification)</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="section-title">Itinerary Legs & Destinations ({{ $fuelPo->legs->count() }})</div>
    <table class="legs-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">#</th>
                <th style="width: 20%;">Origin Location</th>
                <th style="width: 20%;">Starting Point</th>
                <th style="width: 20%;">Destination</th>
                <th style="width: 15%;">Purpose / Cargo</th>
                <th style="width: 10%; text-align: right;">Distance</th>
                <th style="width: 10%; text-align: center;">Source</th>
            </tr>
        </thead>
        <tbody>
            @forelse($fuelPo->legs as $index => $leg)
                <tr>
                    <td style="text-align: center; font-weight: bold;">{{ $index + 1 }}</td>
                    <td>{{ $leg->origin?->official_name ?? '—' }}</td>
                    <td>{{ $leg->startingPoint?->official_name ?? '—' }}</td>
                    <td style="font-weight: bold;">{{ $leg->destination?->official_name ?? '—' }}</td>
                    <td>{{ $leg->purpose ?? '—' }}</td>
                    <td style="text-align: right; font-family: monospace;">{{ $leg->total_distance !== null ? number_format($leg->total_distance, 2) . ' km' : '—' }}</td>
                    <td style="text-align: center; text-transform: uppercase;">{{ $leg->routing_source ?? 'manual' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 10px; color: #64748b;">No legs recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
