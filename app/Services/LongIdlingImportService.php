<?php

namespace App\Services;

use App\Models\LongIdlingRecord;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LongIdlingImportService
{
    /**
     * Column key mappings to recognize diverse header variations.
     */
    protected array $headerAliases = [
        'device_name' => ['devicename', 'device', 'vehicle', 'vehiclename', 'unit', 'unitname'],
        'imei' => ['imei', 'imeino', 'imeinumber'],
        'model' => ['model', 'devicemodel', 'type'],
        'state' => ['state', 'status'],
        'start_time' => ['starttime', 'start', 'from', 'begintime'],
        'end_time' => ['endtime', 'end', 'to'],
        'stay_time' => ['staytime', 'stay', 'duration', 'idlingtime', 'idletime'],
        'coordinates' => ['coordinates', 'coordinate', 'coord', 'latlong', 'latlng', 'locationcoord', 'positioncoord'],
        'latitude' => ['latitude', 'lat'],
        'longitude' => ['longitude', 'long', 'lng', 'lon'],
        'address' => ['address', 'locationaddress', 'position', 'location', 'addr'],
        'remarks' => ['remarks', 'remark', 'notes', 'note'],
    ];

    /**
     * Import records from uploaded Excel or CSV file into a report.
     *
     * @return array{success: bool, count: int, mode: string, message: string}
     */
    public function import(Report $report, UploadedFile|string $file, string $mode = 'append'): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, false, false);

        if (empty($rows)) {
            return [
                'success' => false,
                'count' => 0,
                'mode' => $mode,
                'message' => 'The uploaded file contains no data.',
            ];
        }

        // 1. Locate header row
        $headerInfo = $this->findHeaderRow($rows);
        if (! $headerInfo) {
            return [
                'success' => false,
                'count' => 0,
                'mode' => $mode,
                'message' => 'Could not find valid column headers. Please ensure columns include Device Name, IMEI, Model, Start Time, End Time, Coordinates, Address.',
            ];
        }

        [$headerRowIndex, $columnMap] = $headerInfo;

        // 2. Handle replace mode
        if ($mode === 'replace') {
            $report->longIdlingRecords->each(function ($rec) {
                if ($rec->image) {
                    Storage::disk('public')->delete($rec->image);
                }
            });
            $report->longIdlingRecords()->delete();
        }

        $currentSortOrder = $report->longIdlingRecords()->max('sort_order') ?? 0;
        $importedCount = 0;

        // 3. Process data rows
        $totalRows = count($rows);
        for ($i = $headerRowIndex + 1; $i < $totalRows; $i++) {
            $row = $rows[$i];

            $deviceName = $this->getCellValue($row, $columnMap, 'device_name');
            $imei = $this->formatImei($this->getCellValue($row, $columnMap, 'imei'));
            $model = $this->getCellValue($row, $columnMap, 'model') ?: 'GPS';
            $state = $this->getCellValue($row, $columnMap, 'state');
            $rawStart = $this->getCellValue($row, $columnMap, 'start_time');
            $rawEnd = $this->getCellValue($row, $columnMap, 'end_time');
            $rawStay = $this->getCellValue($row, $columnMap, 'stay_time');
            $rawCoords = $this->getCellValue($row, $columnMap, 'coordinates');
            $rawLat = $this->getCellValue($row, $columnMap, 'latitude');
            $rawLng = $this->getCellValue($row, $columnMap, 'longitude');
            $address = $this->getCellValue($row, $columnMap, 'address');
            $remarks = $this->getCellValue($row, $columnMap, 'remarks');

            // Skip row if device name and IMEI and coordinates are completely blank
            if (empty($deviceName) && empty($imei) && empty($rawCoords)) {
                continue;
            }

            // Clean times
            $startTime = $this->parseTime($rawStart);
            $endTime = $this->parseTime($rawEnd);

            // Calculate stay time if missing or use provided
            $stayTime = $this->cleanStayTime($rawStay) ?: LongIdlingRecord::calculateStayTime($startTime, $endTime);

            // Parse Coordinates
            [$lat, $lng] = $this->parseCoordinates($rawCoords, $rawLat, $rawLng);

            $currentSortOrder++;

            LongIdlingRecord::create([
                'report_id' => $report->id,
                'device_name' => $deviceName ?: 'Unknown Device',
                'imei' => $imei ?: 'N/A',
                'model' => $model,
                'state' => $state ?: null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'stay_time' => $stayTime,
                'latitude' => $lat,
                'longitude' => $lng,
                'address' => $address ?: null,
                'remarks' => $remarks ?: null,
                'sort_order' => $currentSortOrder,
            ]);

            $importedCount++;
        }

        return [
            'success' => true,
            'count' => $importedCount,
            'mode' => $mode,
            'message' => $importedCount > 0
                ? "Successfully imported {$importedCount} record(s)."
                : 'No valid records found to import.',
        ];
    }

    /**
     * Find the header row by matching known column aliases.
     */
    protected function findHeaderRow(array $rows): ?array
    {
        $maxScan = min(15, count($rows));

        for ($r = 0; $r < $maxScan; $r++) {
            $row = $rows[$r];
            if (! is_array($row)) {
                continue;
            }

            $columnMap = [];
            $matchedKeys = 0;

            foreach ($row as $colIndex => $cellValue) {
                if ($cellValue === null || $cellValue === '') {
                    continue;
                }

                $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $cellValue));

                foreach ($this->headerAliases as $key => $aliases) {
                    if (in_array($normalized, $aliases, true) && ! isset($columnMap[$key])) {
                        $columnMap[$key] = $colIndex;
                        $matchedKeys++;
                        break;
                    }
                }
            }

            // If at least 2 critical columns matched, this is the header row
            if ($matchedKeys >= 2 && (isset($columnMap['device_name']) || isset($columnMap['imei']) || isset($columnMap['start_time']) || isset($columnMap['coordinates']))) {
                return [$r, $columnMap];
            }
        }

        return null;
    }

    protected function getCellValue(array $row, array $columnMap, string $key): ?string
    {
        if (! isset($columnMap[$key])) {
            return null;
        }

        $colIdx = $columnMap[$key];
        $val = $row[$colIdx] ?? null;

        return $val !== null ? trim((string) $val) : null;
    }

    /**
     * Parse and format IMEI, avoiding Excel scientific notation loss.
     */
    protected function formatImei(?string $val): ?string
    {
        if (! $val) {
            return null;
        }

        $val = trim($val);

        // If in scientific notation (e.g. 8.65968E+14)
        if (stripos($val, 'e+') !== false || stripos($val, 'e') !== false) {
            $floatVal = (float) $val;

            return number_format($floatVal, 0, '', '');
        }

        return $val;
    }

    /**
     * Parse date/time strings into standard HH:MM:SS format.
     */
    protected function parseTime(?string $val): ?string
    {
        if (! $val) {
            return null;
        }

        $val = trim($val);

        // Numeric Excel timestamp fraction (e.g. 0.7788)
        if (is_numeric($val) && (float) $val >= 0 && (float) $val < 1) {
            $totalSeconds = (int) round(((float) $val) * 86400);
            $hours = floor($totalSeconds / 3600);
            $mins = floor(($totalSeconds % 3600) / 60);
            $secs = $totalSeconds % 60;

            return sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
        }

        // Standard regex for HH:MM:SS or HH:MM
        if (preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?/', $val, $matches)) {
            $h = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $m = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $s = isset($matches[3]) ? str_pad($matches[3], 2, '0', STR_PAD_LEFT) : '00';

            // Check if 12-hour format with AM/PM
            if (stripos($val, 'pm') !== false && (int) $h < 12) {
                $h = str_pad((string) ((int) $h + 12), 2, '0', STR_PAD_LEFT);
            } elseif (stripos($val, 'am') !== false && (int) $h === 12) {
                $h = '00';
            }

            return "{$h}:{$m}:{$s}";
        }

        try {
            return Carbon::parse($val)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Clean and format stay time.
     */
    protected function cleanStayTime(?string $val): ?string
    {
        if (! $val) {
            return null;
        }

        $val = trim($val);

        if (preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $val)) {
            $parts = explode(':', $val);
            $h = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $m = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            $s = isset($parts[2]) ? str_pad($parts[2], 2, '0', STR_PAD_LEFT) : '00';

            return "{$h}:{$m}:{$s}";
        }

        return $val;
    }

    /**
     * Parse coordinates from single string or separate latitude & longitude values.
     *
     * @return array{0: ?float, 1: ?float}
     */
    protected function parseCoordinates(?string $coords, ?string $lat = null, ?string $lng = null): array
    {
        // 1. If explicit separate lat/lng given
        if ($lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng)) {
            return [(float) $lat, (float) $lng];
        }

        // 2. Parse from single coordinates string
        if ($coords) {
            // Split by comma, slash, semicolon, or space
            $parts = preg_split('/[\s,\/;]+/', trim($coords));
            if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                return [(float) $parts[0], (float) $parts[1]];
            }
        }

        return [
            $lat && is_numeric($lat) ? (float) $lat : null,
            $lng && is_numeric($lng) ? (float) $lng : null,
        ];
    }
}
