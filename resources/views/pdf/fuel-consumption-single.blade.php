<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Average Fuel Consumption - {{ $test->vehicle?->equipment_code ?? 'Test' }} - {{ $test->test_date?->format('Y-m-d') }}</title>
    <style>
        * { box-sizing: border-box; }
        @page {
            margin: 10mm 12mm 12mm 12mm;
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
            margin-bottom: 10px;
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
            font-size: 13px;
            font-weight: bold;
            color: #1e40af;
            text-transform: uppercase;
            margin-top: 5px;
            letter-spacing: 0.5px;
        }
        .report-meta {
            font-size: 8px;
            color: #475569;
            text-align: right;
            vertical-align: top;
        }

        /* Benchmark Banner */
        .benchmark-box {
            background-color: #ecfdf5;
            border: 2px solid #10b981;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 12px;
        }
        .benchmark-label {
            font-size: 9px;
            font-weight: bold;
            color: #065f46;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .benchmark-value {
            font-size: 24px;
            font-weight: bold;
            color: #047857;
            font-family: Courier, monospace;
            float: right;
            margin-top: -5px;
        }
        .benchmark-desc {
            font-size: 8px;
            color: #047857;
            margin-top: 3px;
        }

        /* Section Tables */
        .section-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .section-title {
            font-size: 9px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 3px;
            margin-bottom: 6px;
        }

        .data-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .data-grid td {
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            font-size: 8px;
            vertical-align: top;
        }
        .data-grid td.label-cell {
            background-color: #f8fafc;
            color: #475569;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5px;
            width: 32%;
        }
        .data-grid td.val-cell {
            color: #0f172a;
            font-weight: normal;
        }
        .data-grid td.val-bold {
            font-weight: bold;
        }
        .data-grid td.val-highlight {
            background-color: #eff6ff;
            color: #1e3a8a;
            font-weight: bold;
            font-size: 9px;
        }

        /* Image Gallery */
        .evidence-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 15px;
        }
        .evidence-table td {
            width: 33.33%;
            vertical-align: top;
            padding: 0 4px;
        }
        .evidence-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 5px;
            background-color: #f8fafc;
            text-align: center;
        }
        .evidence-title {
            font-size: 7.5px;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .evidence-img {
            max-width: 100%;
            height: 120px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
        }
        .no-img-placeholder {
            height: 120px;
            line-height: 120px;
            background-color: #ffffff;
            border: 1px dashed #cbd5e1;
            color: #94a3b8;
            font-size: 7.5px;
            font-style: italic;
        }

        /* Signature block */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .signature-table td {
            width: 33.33%;
            vertical-align: top;
            padding: 0 10px;
            font-size: 8px;
        }
        .sig-line {
            border-bottom: 1px solid #64748b;
            margin-top: 28px;
            margin-bottom: 4px;
        }
        .sig-label {
            font-size: 7.5px;
            color: #64748b;
            text-transform: uppercase;
        }
        .sig-name {
            font-size: 8.5px;
            font-weight: bold;
            color: #0f172a;
        }
    </style>
</head>
<body>

    {{-- Fixed Page Footer --}}
    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td style="width: 40%; text-align: left;">
                    {{ $company ?? 'GAMA' }} &mdash; Average Fuel Consumption Test Log
                </td>
                <td style="width: 30%; text-align: center;">
                    Document ID: AFC-{{ str_pad($test->id, 5, '0', STR_PAD_LEFT) }}
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
                <div class="report-title">AVERAGE FUEL CONSUMPTION TEST REPORT</div>
                <div style="font-size: 8px; color: #475569; margin-top: 2px;">
                    Test Date: <b>{{ $test->test_date?->format('F d, Y') }}</b> &bull;
                    Vehicle: <b>{{ $test->equipment_code_display }}</b>
                    ({{ $test->plate_number_display ?: 'Unassigned Plate' }})
                </div>
            </td>
            <td class="report-meta">
                <div><b>Generated:</b> {{ now()->format('Y-m-d H:i') }}</div>
                <div><b>Prepared By:</b> {{ $preparedBy ?? 'GPS Monitoring Specialist' }}</div>
                <div><b>Testing Protocol:</b> Full-Tank Method</div>
            </td>
        </tr>
    </table>

    {{-- Prominent Benchmark Result Banner --}}
    <div class="benchmark-box">
        <div class="benchmark-value">
            {{ number_format($test->average_fuel_consumption, 2) }} <span style="font-size: 14px;">KM/L</span>
        </div>
        <div class="benchmark-label">Official Calculated Benchmark</div>
        <div style="font-size: 11px; font-weight: bold; color: #064e3b; margin-top: 2px;">
            AVERAGE FUEL CONSUMPTION: {{ number_format($test->average_fuel_consumption, 2) }} KM/L
        </div>
        <div class="benchmark-desc">
            Calculation: Distance Travelled ({{ number_format($test->distance_travelled, 2) }} km) &divide; Fuel Consumed ({{ number_format($test->fuel_consumed_liters, 3) }} L)
            @if($test->is_short_distance)
                <br><span style="color: #b45309; font-weight: bold;">Notice: Short test distance. Calculated consumption may not represent typical vehicle economy.</span>
            @endif
        </div>
    </div>

    {{-- Vehicle & Driver Information --}}
    <div class="section-title">1. Vehicle & Driver Specifications</div>
    <table class="data-grid" style="margin-bottom: 12px;">
        <tr>
            <td class="label-cell">Vehicle / Equipment</td>
            <td class="val-cell val-bold">{{ $test->equipment_code_display }}</td>
            <td class="label-cell">Plate Number</td>
            <td class="val-cell val-bold" style="font-family: Courier, monospace;">{{ $test->plate_number_display ?: 'Not Assigned' }}</td>
        </tr>
        <tr>
            <td class="label-cell">Make / Model</td>
            <td class="val-cell">{{ $test->model_display ?: 'Unspecified' }}</td>
            <td class="label-cell">Vehicle Type</td>
            <td class="val-cell">{{ $test->vehicle?->vehicleType?->name ?: 'Standard' }}</td>
        </tr>
        <tr>
            <td class="label-cell">Driver / Operator</td>
            <td class="val-cell">{{ $test->driver_display_name }}</td>
            <td class="label-cell">Test Route / Location</td>
            <td class="val-cell">{{ $test->test_route ?: 'Designated Test Circuit' }}</td>
        </tr>
    </table>

    {{-- Readings & Fuel Calculations --}}
    <div class="section-title">2. Full-Tank Odometer & Refuel Readings</div>
    <table class="data-grid" style="margin-bottom: 12px;">
        <tr>
            <td class="label-cell">Start Odometer (1st Full Tank)</td>
            <td class="val-cell" style="font-family: Courier, monospace;">{{ number_format($test->start_odometer, 2) }} km</td>
            <td class="label-cell">End Odometer</td>
            <td class="val-cell" style="font-family: Courier, monospace;">{{ number_format($test->end_odometer, 2) }} km</td>
        </tr>
        <tr>
            <td class="label-cell">Distance Travelled</td>
            <td class="val-cell val-highlight" style="font-family: Courier, monospace;">
                {{ number_format($test->distance_travelled, 2) }} km
            </td>
            <td class="label-cell">Fuel Consumed (2nd Full Tank)</td>
            <td class="val-cell val-highlight" style="font-family: Courier, monospace;">
                {{ number_format($test->fuel_consumed_liters, 3) }} Liters
            </td>
        </tr>
        <tr>
            <td class="label-cell">Average Fuel Consumption</td>
            <td class="val-cell val-highlight" colspan="3" style="font-size: 11px; color: #047857;">
                {{ number_format($test->average_fuel_consumption, 2) }} KM/L
            </td>
        </tr>
        @if($test->remarks)
        <tr>
            <td class="label-cell">Remarks & Observations</td>
            <td class="val-cell" colspan="3">{{ $test->remarks }}</td>
        </tr>
        @endif
    </table>

    {{-- Supporting Evidence / Photos --}}
    <div class="section-title">3. Supporting Test Evidence</div>
    <table class="evidence-table">
        <tr>
            {{-- 1. Start Odometer --}}
            <td>
                <div class="evidence-card">
                    <div class="evidence-title">1. Start Odometer Photo</div>
                    @php
                        $startPath = $test->start_odometer_image ? \Illuminate\Support\Facades\Storage::disk('public')->path($test->start_odometer_image) : null;
                    @endphp
                    @if($startPath && file_exists($startPath))
                        @php
                            $imgData = base64_encode(file_get_contents($startPath));
                            $imgMime = mime_content_type($startPath);
                        @endphp
                        <img src="data:{{ $imgMime }};base64,{{ $imgData }}" class="evidence-img" alt="Start Odometer">
                    @else
                        <div class="no-img-placeholder">No photo attached</div>
                    @endif
                </div>
            </td>

            {{-- 2. End Odometer --}}
            <td>
                <div class="evidence-card">
                    <div class="evidence-title">2. End Odometer Photo</div>
                    @php
                        $endPath = $test->end_odometer_image ? \Illuminate\Support\Facades\Storage::disk('public')->path($test->end_odometer_image) : null;
                    @endphp
                    @if($endPath && file_exists($endPath))
                        @php
                            $imgData = base64_encode(file_get_contents($endPath));
                            $imgMime = mime_content_type($endPath);
                        @endphp
                        <img src="data:{{ $imgMime }};base64,{{ $imgData }}" class="evidence-img" alt="End Odometer">
                    @else
                        <div class="no-img-placeholder">No photo attached</div>
                    @endif
                </div>
            </td>

            {{-- 3. Fuel Receipt --}}
            <td>
                <div class="evidence-card">
                    <div class="evidence-title">3. Fuel Receipt (2nd Tank)</div>
                    @php
                        $receiptPath = $test->fuel_receipt_image ? \Illuminate\Support\Facades\Storage::disk('public')->path($test->fuel_receipt_image) : null;
                    @endphp
                    @if($receiptPath && file_exists($receiptPath))
                        @php
                            $imgData = base64_encode(file_get_contents($receiptPath));
                            $imgMime = mime_content_type($receiptPath);
                        @endphp
                        <img src="data:{{ $imgMime }};base64,{{ $imgData }}" class="evidence-img" alt="Fuel Receipt">
                    @else
                        <div class="no-img-placeholder">No receipt attached</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Signatures / Attestation --}}
    <div class="section-title">4. Verification & Attestation</div>
    <table class="signature-table">
        <tr>
            <td>
                <div class="sig-name">{{ $test->creator?->name ?? 'Specialist' }}</div>
                <div class="sig-label">Recorded By (Monitoring Center)</div>
                <div class="sig-line"></div>
                <div class="sig-label">Date: {{ $test->created_at->format('Y-m-d') }}</div>
            </td>
            <td>
                <div class="sig-name">{{ $test->attested_by ?: '—' }}</div>
                <div class="sig-label">Attested By (Witness / Specialist)</div>
                <div class="sig-line"></div>
                <div class="sig-label">Signature / Verification</div>
            </td>
            <td>
                <div class="sig-name">{{ $test->requested_by ?: '—' }}</div>
                <div class="sig-label">Requested By (Management / Unit)</div>
                <div class="sig-line"></div>
                <div class="sig-label">Signature / Approval</div>
            </td>
        </tr>
    </table>

</body>
</html>
