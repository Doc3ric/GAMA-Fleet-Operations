<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Advanced Itinerary #{{ $advancedItinerary->id }} - {{ $advancedItinerary->itinerary_date->format('Y-m-d') }}</title>
    <style>
        * { box-sizing: border-box; }
        @page {
            margin: 12mm 12mm 15mm 12mm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
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
            letter-spacing: 0.5px;
        }
        .company-tagline {
            font-size: 8.5px;
            color: #64748b;
        }
        .report-title {
            font-size: 13px;
            font-weight: bold;
            color: #1e3a8a;
            text-align: right;
        }
        .report-subtitle {
            font-size: 8.5px;
            color: #64748b;
            text-align: right;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .meta-table td {
            padding: 6px 10px;
            font-size: 8.5px;
        }
        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 18%;
        }
        .meta-value {
            color: #0f172a;
            width: 32%;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th {
            background: #1e293b;
            color: #ffffff;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #1e293b;
        }
        .data-table td {
            font-size: 8.5px;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-draft {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-finalized {
            background: #dcfce7;
            color: #166534;
        }
        .summary-box {
            margin-top: 10px;
            padding: 10px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            text-align: right;
        }
        .summary-total {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="company-name">GAMA</div>
                <div class="company-tagline">Fleet Operations Management System</div>
            </td>
            <td style="vertical-align: top;" class="text-right">
                <div class="report-title">ADVANCED ITINERARY REPORT</div>
                <div class="report-subtitle">Itinerary #{{ $advancedItinerary->id }} &bull; Generated {{ now()->format('M d, Y h:i A') }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Itinerary Date:</td>
            <td class="meta-value">{{ $advancedItinerary->itinerary_date->format('F d, Y') }}</td>
            <td class="meta-label">Status:</td>
            <td class="meta-value">
                <span class="badge {{ $advancedItinerary->isFinalized() ? 'badge-finalized' : 'badge-draft' }}">
                    {{ $advancedItinerary->status }}
                </span>
            </td>
        </tr>
        <tr>
            <td class="meta-label">Vehicle:</td>
            <td class="meta-value">
                @if($advancedItinerary->vehicle)
                    {{ $advancedItinerary->vehicle->equipment_code }} ({{ $advancedItinerary->vehicle->plate_number }})
                @else
                    <span style="color: #94a3b8;">Unassigned</span>
                @endif
            </td>
            <td class="meta-label">Total Est. Driving Time:</td>
            <td class="meta-value font-bold" style="color: #1e3a8a;">{{ $advancedItinerary->formatted_total_duration }}</td>
        </tr>
        <tr>
            <td class="meta-label">Created By:</td>
            <td class="meta-value">{{ $advancedItinerary->creator?->name ?? 'System' }}</td>
            <td class="meta-label">Total Road Distance:</td>
            <td class="meta-value font-bold" style="color: #1e3a8a;">{{ number_format($advancedItinerary->total_distance, 2) }} km</td>
        </tr>
        @if($advancedItinerary->title)
        <tr>
            <td class="meta-label">Title / Subject:</td>
            <td class="meta-value" colspan="3">{{ $advancedItinerary->title }}</td>
        </tr>
        @endif
        @if($advancedItinerary->notes)
        <tr>
            <td class="meta-label">Notes / Instructions:</td>
            <td class="meta-value" colspan="3">{{ $advancedItinerary->notes }}</td>
        </tr>
        @endif
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">#</th>
                <th style="width: 23%;">Origin Location</th>
                <th style="width: 23%;">Starting Point</th>
                <th style="width: 23%;">Destination</th>
                <th style="width: 8%;" class="text-right">Origin &rarr; Start</th>
                <th style="width: 8%;" class="text-right">Start &rarr; Dest</th>
                <th style="width: 10%;" class="text-right">Total (km)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($advancedItinerary->legs as $index => $leg)
                <tr>
                    <td class="text-center font-bold">{{ $index + 1 }}</td>
                    <td>
                        <div class="font-bold">{{ $leg->origin?->official_name ?? 'N/A' }}</div>
                        <div style="color: #64748b; font-size: 7.5px;">{{ $leg->origin?->code }}</div>
                    </td>
                    <td>
                        <div class="font-bold">{{ $leg->startingPoint?->official_name ?? 'N/A' }}</div>
                        <div style="color: #64748b; font-size: 7.5px;">{{ $leg->startingPoint?->code }}</div>
                    </td>
                    <td>
                        <div class="font-bold">{{ $leg->destination?->official_name ?? 'N/A' }}</div>
                        <div style="color: #64748b; font-size: 7.5px;">{{ $leg->destination?->code }}</div>
                        @if($leg->purpose)
                            <div style="color: #2563eb; font-size: 7.5px;">Purpose: {{ $leg->purpose }}</div>
                        @endif
                    </td>
                    <td class="text-right">
                        <div>{{ $leg->distance_origin_to_start !== null ? number_format($leg->distance_origin_to_start, 2).' km' : '-' }}</div>
                        @if($leg->duration_origin_to_start_minutes)
                            <div style="color: #64748b; font-size: 7.5px;">{{ $leg->formatted_origin_to_start_duration }}</div>
                        @endif
                    </td>
                    <td class="text-right">
                        <div>{{ $leg->distance_start_to_dest !== null ? number_format($leg->distance_start_to_dest, 2).' km' : '-' }}</div>
                        @if($leg->duration_start_to_dest_minutes)
                            <div style="color: #64748b; font-size: 7.5px;">{{ $leg->formatted_start_to_dest_duration }}</div>
                        @endif
                    </td>
                    <td class="text-right font-bold">
                        <div>{{ $leg->total_distance !== null ? number_format($leg->total_distance, 2).' km' : '-' }}</div>
                        @if($leg->total_duration_minutes)
                            <div style="color: #1e3a8a; font-size: 7.5px;">{{ $leg->formatted_total_duration }}</div>
                        @endif
                        <div style="font-size: 7px; color: #64748b; font-weight: normal;">({{ strtoupper($leg->routing_source ?? 'N/A') }})</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 15px; color: #64748b;">
                        No legs recorded for this itinerary.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary-box">
        <table style="width: 100%;">
            <tr>
                <td style="text-align: left; font-size: 8.5px; color: #475569;">
                    Total Legs: <strong>{{ $advancedItinerary->legs->count() }}</strong>
                </td>
                <td style="text-align: right;">
                    <span class="summary-total">Total Road Distance: {{ number_format($advancedItinerary->total_distance, 2) }} km</span>
                    <span class="summary-total" style="display: inline-block; margin-left: 20px;">Total Est. Driving Time: {{ $advancedItinerary->formatted_total_duration }}</span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
