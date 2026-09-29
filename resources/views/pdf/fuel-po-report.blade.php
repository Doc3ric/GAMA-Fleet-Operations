<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Fuel PO Checklist Report &bull; GAMA FOMS</title>
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 5px;
            text-align: left;
            border: 1px solid #0f172a;
        }
        .data-table td {
            font-size: 7.5px;
            padding: 5px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .font-mono { font-family: monospace; }

        .check-box {
            display: inline-block;
            font-size: 11px;
            font-weight: bold;
            line-height: 1;
        }
        .check-box-checked {
            color: #166534;
        }
        .check-box-unchecked {
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td style="width: 50%;">GAMA Fleet Operations Management System &bull; Purchasing Fuel PO Checklist</td>
                <td style="width: 50%; text-align: right;">Generated: {{ now()->format('M d, Y h:i A') }} &bull; Page <span class="pagenum"></span></td>
            </tr>
        </table>
    </div>

    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="company-name">GAMA</div>
                <div class="company-tagline">Fleet Operations Management System</div>
            </td>
            <td style="vertical-align: top;" class="text-right">
                <div class="report-title">FUEL PURCHASE ORDER (PO) CHECKLIST</div>
                <div class="report-subtitle">Itinerary Fuel Requirements &bull; {{ now()->format('F d, Y') }}</div>
            </td>
        </tr>
    </table>

    @php
        $list = $records ?? $itineraries ?? collect();
    @endphp

    <table class="summary-bar">
        <tr>
            <td>
                <span class="summary-label">Total Records:</span>
                <span class="summary-val">{{ $list->count() }}</span>
            </td>
            <td>
                <span class="summary-label">PO Checked:</span>
                <span class="summary-val" style="color: #166534;">{{ $list->where('po_checked', true)->count() }}</span>
            </td>
            <td>
                <span class="summary-label">PO Pending:</span>
                <span class="summary-val" style="color: #b45309;">{{ $list->where('po_checked', false)->count() }}</span>
            </td>
            <td>
                <span class="summary-label">Total Fuel Required:</span>
                @php
                    $totalL = (float) $list->sum('fuel_liters');
                @endphp
                <span class="summary-val" style="color: #1e3a8a;">{{ $totalL == round($totalL) ? number_format($totalL, 0) : number_format($totalL, 2) }} L</span>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;" class="text-center">#</th>
                <th style="width: 8%;">EQPT CODE</th>
                <th style="width: 9%;">MODEL</th>
                <th style="width: 12%;">DRIVER</th>
                <th style="width: 9%;">PLATE</th>
                <th style="width: 9%;">USER</th>
                <th style="width: 10%;">PROJECT</th>
                <th style="width: 15%;">DESTINATION</th>
                <th style="width: 8%;" class="text-right">DISTANCE</th>
                <th style="width: 8%;" class="text-right">AVG CONSUMP</th>
                <th style="width: 8%;" class="text-right">LITER FOR PO</th>
                <th style="width: 6%;" class="text-center">CHECKLIST</th>
            </tr>
        </thead>
        <tbody>
            @forelse($list as $index => $itinerary)
                @php
                    $vehicle = $itinerary->vehicle;
                    $avgConsumption = $vehicle?->average_consumption;
                    $fuelLiters = $itinerary->fuel_liters;
                @endphp
                <tr>
                    <td class="text-center font-bold">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $vehicle?->equipment_code ?? '—' }}</td>
                    <td>{{ $vehicle?->model ?? '—' }}</td>
                    <td>{{ $vehicle?->operator_driver ?? '—' }}</td>
                    <td class="font-mono">{{ $vehicle?->plate_number ?? '—' }}</td>
                    <td>{{ $vehicle?->user ?? '—' }}</td>
                    <td>{{ $vehicle?->project_code ?? '—' }}</td>
                    <td>{{ $itinerary->destination_name }}</td>
                    <td class="text-right font-mono">{{ number_format($itinerary->total_distance, 2) }} KM</td>
                    <td class="text-right font-mono">{{ $avgConsumption !== null ? number_format($avgConsumption, 2) . ' KM/L' : '—' }}</td>
                    <td class="text-right font-mono font-bold" style="color: #1e3a8a;">
                        {{ $fuelLiters !== null ? ((float) $fuelLiters == round($fuelLiters) ? number_format($fuelLiters, 0) : number_format($fuelLiters, 2)) . ' L' : '—' }}
                    </td>
                    <td class="text-center">
                        @if($itinerary->po_checked)
                            <span class="check-box check-box-checked">&#9745;</span>
                        @else
                            <span class="check-box check-box-unchecked">&#9744;</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center" style="padding: 15px; color: #64748b;">
                        No itinerary records found for fuel PO processing.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
