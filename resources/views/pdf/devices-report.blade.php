<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>GPS Devices Expiration & Telemetry Report</title>
    <style>
        * { box-sizing: border-box; }
        @page {
            margin: 8mm 8mm 10mm 8mm;
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
            bottom: -6mm;
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

        /* Header */
        .header {
            border-bottom: 2px solid #1e40af;
            padding-bottom: 5px;
            margin-bottom: 6px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        .report-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .report-meta {
            font-size: 7.5px;
            color: #475569;
            margin-top: 1px;
        }

        /* Summary Bar */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            margin-bottom: 6px;
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
            font-size: 6.5px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
        }
        .summary-value {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 1px;
        }
        .summary-value.expiring {
            color: #d97706;
        }
        .summary-value.expired {
            color: #dc2626;
        }
        .summary-value.active {
            color: #059669;
        }

        /* Budget Notice */
        .notice-box {
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
            border-left: 4px solid #dc2626;
            padding: 5px 8px;
            margin-bottom: 6px;
            font-size: 7.5px;
            color: #991b1b;
        }
        .notice-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5px;
            margin-bottom: 1px;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
        }
        .data-table thead tr {
            background-color: #0f172a;
            color: #ffffff;
        }
        .data-table th {
            padding: 4px 5px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid #334155;
            text-align: left;
        }
        .data-table th.center { text-align: center; }
        .data-table th.right { text-align: right; }

        .data-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .data-table tbody tr.row-expiring {
            background-color: #fffbeb;
        }
        .data-table tbody tr.row-expired {
            background-color: #fef2f2;
        }

        .data-table td {
            padding: 3.5px 5px;
            font-size: 7px;
            vertical-align: middle;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            word-wrap: break-word;
        }
        .data-table td.center { text-align: center; }
        .data-table td.right { text-align: right; }
        .data-table td.mono {
            font-family: Courier, monospace;
            font-size: 6.5px;
        }

        /* Status Pills */
        .badge {
            display: inline-block;
            padding: 1.5px 4px;
            border-radius: 3px;
            font-size: 6.5px;
            font-weight: bold;
            text-align: center;
        }
        .badge-expiring {
            background-color: #fef3c7;
            color: #92400e;
            border: 0.5px solid #fcd34d;
        }
        .badge-expired {
            background-color: #fee2e2;
            color: #991b1b;
            border: 0.5px solid #fca5a5;
        }
        .badge-active {
            background-color: #ecfdf5;
            color: #065f46;
            border: 0.5px solid #a7f3d0;
        }

        /* Signature block */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .signature-table td {
            width: 33.33%;
            vertical-align: top;
            padding: 0 15px;
        }
        .signature-line {
            border-bottom: 1px solid #64748b;
            height: 25px;
            margin-bottom: 3px;
        }
        .signature-name {
            font-size: 7.5px;
            font-weight: bold;
            color: #0f172a;
        }
        .signature-title {
            font-size: 6.5px;
            color: #64748b;
        }
    </style>
</head>
<body>

    {{-- Fixed Page Footer --}}
    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td style="width: 40%;">GAMA FOMS &bull; Confidential Fleet Operations Report</td>
                <td style="width: 20%; text-align: center;">Page <span class="pagenum"></span></td>
                <td style="width: 40%; text-align: right;">Generated: {{ $generatedAt }}</td>
            </tr>
        </table>
    </div>

    {{-- Header --}}
    <div class="header">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 70%;">
                    <div class="company-name">{{ $company }}</div>
                    <div class="report-title">GPS Devices Expiration & Telemetry Report</div>
                    <div class="report-meta">
                        <strong>Filter:</strong> {{ $statusFilter }} &nbsp;&bull;&nbsp;
                        <strong>Scope:</strong> Total {{ $devices->count() }} records listed
                    </div>
                </td>
                <td style="width: 30%; text-align: right; vertical-align: top;">
                    <div style="font-size: 7.5px; color: #64748b;">System Prepared By:</div>
                    <div style="font-size: 9px; font-weight: bold; color: #1e293b;">{{ $preparedBy }}</div>
                    <div style="font-size: 7px; color: #94a3b8;">{{ $tagline }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Summary Cards --}}
    <table class="summary-table">
        <tr>
            <td style="width: 25%;">
                <div class="summary-label">Total GPS Devices</div>
                <div class="summary-value">{{ number_format($totalCount) }}</div>
            </td>
            <td style="width: 25%;">
                <div class="summary-label">Expires in &le; 30 Days</div>
                <div class="summary-value expiring">{{ number_format($expiringSoonCount) }}</div>
            </td>
            <td style="width: 25%;">
                <div class="summary-label">Already Expired</div>
                <div class="summary-value expired">{{ number_format($expiredCount) }}</div>
            </td>
            <td style="width: 25%;">
                <div class="summary-label">Active Units (&gt; 30 Days)</div>
                <div class="summary-value active">{{ number_format($activeCount) }}</div>
            </td>
        </tr>
    </table>

    {{-- Budget Notice (If applicable) --}}
    @if($expiringSoonCount > 0 || $expiredCount > 0)
        <div class="notice-box">
            <div class="notice-title">Action Required: GPS Subscription Budgeting & Renewal Notice</div>
            There are <strong>{{ $expiringSoonCount }} device(s) expiring within the next 30 days</strong> and <strong>{{ $expiredCount }} device(s) already expired</strong>.
            Please submit this report to company procurement/finance for subscription renewal budgeting.
        </div>
    @endif

    {{-- Main Telemetry & Expiration Data Table --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;" class="center">#</th>
                <th style="width: 14%;">Device Name</th>
                <th style="width: 14%;">IMEI</th>
                <th style="width: 7%;" class="center">Model</th>
                <th style="width: 10%;">SIM</th>
                <th style="width: 17%;">User Expiration Date</th>
                <th style="width: 11%;">Group</th>
                <th style="width: 13%;">ICCID</th>
                <th style="width: 10%;" class="right">Mileage</th>
            </tr>
        </thead>
        <tbody>
            @forelse($devices as $index => $device)
                @php
                    $badge = $device->expiration_badge;
                    $rowClass = '';
                    $badgeClass = 'badge-active';

                    if ($badge['status'] === 'expiring_soon') {
                        $rowClass = 'row-expiring';
                        $badgeClass = 'badge-expiring';
                    } elseif ($badge['status'] === 'expired') {
                        $rowClass = 'row-expired';
                        $badgeClass = 'badge-expired';
                    }
                @endphp
                <tr class="{{ $rowClass }}">
                    <td class="center">{{ $index + 1 }}</td>
                    <td style="font-weight: bold; color: #0f172a;">{{ $device->device_name }}</td>
                    <td class="mono">{{ $device->imei ?: '-' }}</td>
                    <td class="center"><strong>{{ $device->model ?: '-' }}</strong></td>
                    <td class="mono">{{ $device->sim ?: '-' }}</td>
                    <td>
                        <strong>{{ $device->expiration_date ? $device->expiration_date->format('Y-m-d') : ($device->raw_expiration ?: '-') }}</strong>
                        <span class="badge {{ $badgeClass }}">{{ $badge['label'] }}</span>
                    </td>
                    <td>{{ $device->group_name ?: '-' }}</td>
                    <td class="mono">{{ $device->iccid ? Str::limit($device->iccid, 18) : '-' }}</td>
                    <td class="right mono">{{ $device->mileage !== null ? number_format($device->mileage, 1).' km' : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center" style="padding: 12px; color: #94a3b8;">
                        No GPS devices found matching the specified criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Signatures --}}
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-title">Prepared By:</div>
                <div class="signature-line"></div>
                <div class="signature-name">{{ $preparedBy }}</div>
                <div class="signature-title">Fleet Monitoring Operator</div>
            </td>
            <td>
                <div class="signature-title">Reviewed By:</div>
                <div class="signature-line"></div>
                <div class="signature-name">&nbsp;</div>
                <div class="signature-title">Fleet Supervisor / Operations</div>
            </td>
            <td>
                <div class="signature-title">Approved For Budgeting By:</div>
                <div class="signature-line"></div>
                <div class="signature-name">&nbsp;</div>
                <div class="signature-title">Finance / General Management</div>
            </td>
        </tr>
    </table>

</body>
</html>
