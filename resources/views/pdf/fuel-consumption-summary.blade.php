<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Average Fuel Consumption Summary Report</title>
    <style>
        * { box-sizing: border-box; }
        @page {
            margin: 10mm 10mm 12mm 10mm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8px;
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
            border-top: 1px solid #cbd5e1;
            padding-top: 3px;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-table td {
            font-size: 7.5px;
            color: #64748b;
            padding: 0;
        }
        .pagenum:before {
            content: counter(page);
        }

        /* Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2.5px solid #0f172a;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .company-tagline {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 1px;
        }
        .report-title {
            font-size: 11px;
            font-weight: bold;
            color: #1e40af;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .report-meta {
            font-size: 8px;
            color: #475569;
            text-align: right;
            vertical-align: top;
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #0f172a;
            text-align: left;
        }
        .data-table th.center, .data-table td.center {
            text-align: center;
        }
        .data-table th.right, .data-table td.right {
            text-align: right;
        }
        .data-table td {
            padding: 4px 5px;
            font-size: 8px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        .data-table tr.even {
            background-color: #f8fafc;
        }
        .mono {
            font-family: Courier, monospace;
        }
        .badge-km-l {
            background-color: #ecfdf5;
            color: #047857;
            font-weight: bold;
            padding: 1px 4px;
            border-radius: 3px;
            border: 1px solid #a7f3d0;
        }
    </style>
</head>
<body>

    {{-- Fixed Page Footer --}}
    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td style="width: 40%; text-align: left;">
                    {{ $company ?? 'GAMA' }} &mdash; Fleet Fuel Economy Benchmark
                </td>
                <td style="width: 30%; text-align: center;">
                    Generated: {{ now()->format('Y-m-d H:i') }}
                </td>
                <td style="width: 30%; text-align: right;">
                    Page <span class="pagenum"></span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td style="width: 65%; vertical-align: top;">
                <div class="company-name">{{ $company ?? 'GAMA FOODS CORPORATION' }}</div>
                <div class="company-tagline">{{ $tagline ?? 'Fleet Operations Management System' }}</div>
                <div class="report-title">AVERAGE FUEL CONSUMPTION LOG & BENCHMARK SUMMARY</div>
            </td>
            <td class="report-meta">
                <div><b>Generated:</b> {{ now()->format('Y-m-d H:i') }}</div>
                <div><b>Prepared By:</b> {{ $preparedBy ?? 'GPS Monitoring Specialist' }}</div>
                <div><b>Total Records:</b> {{ count($tests) }}</div>
            </td>
        </tr>
    </table>

    {{-- Data Table --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;" class="center">#</th>
                <th style="width: 55px;">Date</th>
                <th style="width: 90px;">Vehicle</th>
                <th style="width: 90px;">Driver / Operator</th>
                <th style="width: 65px;" class="right">Start Odo</th>
                <th style="width: 65px;" class="right">End Odo</th>
                <th style="width: 65px;" class="right">Distance</th>
                <th style="width: 65px;" class="right">Fuel (2nd Tank)</th>
                <th style="width: 75px;" class="center">Avg Consumption</th>
                <th>Route / Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tests as $index => $test)
                <tr class="{{ $index % 2 === 1 ? 'even' : '' }}">
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $test->test_date?->format('Y-m-d') ?? 'N/A' }}</td>
                    <td>
                        <b>{{ $test->vehicle?->equipment_code ?? 'N/A' }}</b>
                        @if($test->vehicle?->plate_number)
                            <br><span style="font-size: 7px; color: #64748b;">{{ $test->vehicle->plate_number }}</span>
                        @endif
                    </td>
                    <td>{{ $test->driver_display_name }}</td>
                    <td class="right mono">{{ number_format($test->start_odometer, 2) }}</td>
                    <td class="right mono">{{ number_format($test->end_odometer, 2) }}</td>
                    <td class="right mono"><b>{{ number_format($test->distance_travelled, 2) }} km</b></td>
                    <td class="right mono"><b>{{ number_format($test->fuel_consumed_liters, 3) }} L</b></td>
                    <td class="center mono">
                        <span class="badge-km-l">{{ number_format($test->average_fuel_consumption, 2) }} km/L</span>
                    </td>
                    <td>
                        {{ $test->test_route ?: '—' }}
                        @if($test->remarks)
                            <br><span style="font-size: 7px; color: #64748b;">{{ $test->remarks }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center" style="padding: 20px; color: #94a3b8; font-style: italic;">
                        No fuel consumption tests recorded.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
