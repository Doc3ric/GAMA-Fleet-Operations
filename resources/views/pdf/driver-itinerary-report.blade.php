<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Driver Itinerary Report - {{ $start_date->format('M j, Y') }} to {{ $end_date->format('M j, Y') }}</title>
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
            font-size: 15px;
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
            font-size: 12px;
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

        /* Summary Bar */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            margin-bottom: 8px;
        }
        .summary-table td {
            padding: 5px 8px;
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
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .summary-value {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 1px;
        }

        /* Itinerary Table */
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
        .data-table th.center {
            text-align: center;
        }
        .data-table td {
            padding: 4.5px 6px;
            font-size: 8px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        .data-table tr.even {
            background-color: #f8fafc;
        }
        .data-table tr:hover {
            background-color: #f1f5f9;
        }
        .center {
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-completed {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-inprogress {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-cancelled {
            background-color: #ffe4e6;
            color: #9f1239;
        }
    </style>
</head>
<body>

    {{-- Fixed Page Footer --}}
    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td style="width: 40%; text-align: left;">
                    {{ $company ?? 'GAMA' }} &mdash; Driver Itinerary Log
                </td>
                <td style="width: 30%; text-align: center;">
                    Period: {{ $start_date->format('M j, Y') }} &ndash; {{ $end_date->format('M j, Y') }}
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
                <div class="report-title">DRIVER ITINERARY REPORT</div>
                <div style="font-size: 8.5px; color: #475569; margin-top: 2px;">
                    Period: <b>{{ $start_date->format('F j, Y') }}</b> &ndash; <b>{{ $end_date->format('F j, Y') }}</b>
                    @if($driver)
                        &bull; Driver: <b>{{ $driver->name }}</b>
                    @endif
                    @if($vehicle)
                        &bull; Vehicle: <b>{{ $vehicle->equipment_code }} ({{ $vehicle->plate_number }})</b>
                    @endif
                </div>
            </td>
            <td class="report-meta">
                <div><b>Generated:</b> {{ now()->format('Y-m-d H:i') }}</div>
                <div><b>Prepared By:</b> {{ $preparedBy ?? 'GPS Monitoring Specialist' }}</div>
                <div><b>Status:</b> Consolidated Official Log</div>
            </td>
        </tr>
    </table>

    {{-- Summary KPI Bar --}}
    <table class="summary-table">
        <tr>
            <td style="width: 16%;">
                <div class="summary-label">Total Trips</div>
                <div class="summary-value">{{ number_format($statistics['total']) }}</div>
            </td>
            <td style="width: 16%;">
                <div class="summary-label" style="color: #166534;">Completed</div>
                <div class="summary-value" style="color: #166534;">{{ number_format($statistics['completed']) }}</div>
            </td>
            <td style="width: 16%;">
                <div class="summary-label" style="color: #92400e;">In Progress</div>
                <div class="summary-value" style="color: #92400e;">{{ number_format($statistics['in_progress']) }}</div>
            </td>
            <td style="width: 16%;">
                <div class="summary-label" style="color: #9f1239;">Cancelled</div>
                <div class="summary-value" style="color: #9f1239;">{{ number_format($statistics['cancelled']) }}</div>
            </td>
            <td style="width: 18%;">
                <div class="summary-label">Active Drivers</div>
                <div class="summary-value">{{ number_format($statistics['active_drivers']) }}</div>
            </td>
            <td style="width: 18%;">
                <div class="summary-label">Active Vehicles</div>
                <div class="summary-value">{{ number_format($statistics['active_vehicles']) }}</div>
            </td>
        </tr>
    </table>

    {{-- Itinerary Table --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;" class="center">#</th>
                <th style="width: 55px;">Date</th>
                <th style="width: 85px;">Driver</th>
                <th style="width: 75px;">Vehicle</th>
                <th style="width: 180px;">Origin</th>
                <th style="width: 180px;">Destination</th>
                <th style="width: 45px;" class="center">Time In</th>
                <th style="width: 45px;" class="center">Time Out</th>
                <th style="width: 55px;" class="center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($trips as $index => $trip)
                <tr class="{{ $index % 2 === 1 ? 'even' : '' }}">
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $trip->trip_date?->format('Y-m-d') ?? 'N/A' }}</td>
                    <td><b>{{ $trip->driver?->name ?? 'N/A' }}</b></td>
                    <td>
                        <b>{{ $trip->vehicle?->equipment_code ?? 'N/A' }}</b>
                        @if($trip->vehicle?->plate_number)
                            <br/><span style="font-size: 7px; color: #64748b;">{{ $trip->vehicle->plate_number }}</span>
                        @endif
                    </td>
                    <td>
                        @if($trip->origin_address)
                            {{ $trip->origin_address }}
                        @elseif($trip->origin_latitude && $trip->origin_longitude)
                            <span style="font-family: monospace; color: #475569;">{{ number_format($trip->origin_latitude, 5) }}, {{ number_format($trip->origin_longitude, 5) }}</span>
                        @else
                            <span style="color: #94a3b8; font-style: italic;">Coordinates available</span>
                        @endif
                    </td>
                    <td>
                        @if($trip->destination_address)
                            {{ $trip->destination_address }}
                        @elseif($trip->destination_latitude && $trip->destination_longitude)
                            <span style="font-family: monospace; color: #475569;">{{ number_format($trip->destination_latitude, 5) }}, {{ number_format($trip->destination_longitude, 5) }}</span>
                        @elseif($trip->isInProgress())
                            <span style="color: #b45309; font-style: italic;">In Progress</span>
                        @elseif($trip->isCancelled())
                            <span style="color: #be123c; font-style: italic;">Cancelled</span>
                        @else
                            <span style="color: #94a3b8; font-style: italic;">Coordinates available</span>
                        @endif
                    </td>
                    <td class="center" style="font-family: monospace;">{{ $trip->time_in ?? '—' }}</td>
                    <td class="center" style="font-family: monospace;">{{ $trip->time_out ?? ($trip->isInProgress() ? 'In Transit' : '—') }}</td>
                    <td class="center">
                        @if($trip->isCompleted())
                            <span class="badge badge-completed">COMPLETED</span>
                        @elseif($trip->isInProgress())
                            <span class="badge badge-inprogress">IN PROGRESS</span>
                        @else
                            <span class="badge badge-cancelled">CANCELLED</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center" style="padding: 20px; color: #94a3b8; font-style: italic;">
                        No trips recorded for the selected period.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
