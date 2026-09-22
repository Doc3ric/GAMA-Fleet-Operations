<?php

namespace App\Services;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class DeviceImportService
{
    /**
     * Column key mappings to recognize Tracksolid & Excel header variations.
     *
     * @var array<string, list<string>>
     */
    protected array $headerAliases = [
        'device_name' => ['devicename', 'device', 'name', 'unit', 'vehiclename', 'equipmentcode', 'equipment'],
        'imei' => ['imei', 'imeino', 'imeinumber', 'deviceid'],
        'model' => ['model', 'devicemodel', 'devicetype', 'type'],
        'activated_date' => ['activateddate', 'activationdate', 'activated', 'activedate'],
        'sales_time' => ['salestime', 'salesdate', 'saletime', 'sale'],
        'sim' => ['sim', 'simno', 'simnumber', 'phoneno', 'phone', 'msisdn', 'mobilenumber'],
        'expiration_date' => ['userexpirationdate', 'expirationdate', 'expiration', 'expirydate', 'expiredate', 'expire', 'expiry', 'userdate'],
        'group' => ['group', 'defaultgroup', 'groupname', 'department', 'team', 'fleetgroup'],
        'iccid' => ['iccid', 'iccidno', 'icc'],
        'imsi' => ['imsi', 'imsino'],
        'mileage' => ['mileage', 'km', 'odometer', 'totalmileage', 'dist', 'distance'],
    ];

    /**
     * Import records from uploaded Excel or CSV file.
     *
     * @return array{success: bool, count: int, mode: string, message: string}
     */
    public function import(UploadedFile|string $file, string $mode = 'update', ?int $userId = null): array
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
                'message' => 'Could not find valid column headers. Please ensure headers include Device Name, IMEI, Model, Expiration Date, etc.',
            ];
        }

        [$headerRowIndex, $columnMap] = $headerInfo;

        $userId = $userId ?: auth()->id();

        // 2. Handle replace mode
        if ($mode === 'replace') {
            Device::where('created_by', $userId)->delete();
        }

        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $pairedCount = 0;
        $linkService = app(VehicleDeviceLinkService::class);

        // 3. Process data rows
        $totalRows = count($rows);
        for ($i = $headerRowIndex + 1; $i < $totalRows; $i++) {
            $row = $rows[$i];

            $deviceName = $this->getCellValue($row, $columnMap, 'device_name');
            $imei = $this->getCellValue($row, $columnMap, 'imei');

            // Skip completely blank rows or missing device identifiers
            if (empty($deviceName) && empty($imei)) {
                continue;
            }

            $model = $this->getCellValue($row, $columnMap, 'model');
            $rawActivated = $this->getCellValue($row, $columnMap, 'activated_date');
            $rawSalesTime = $this->getCellValue($row, $columnMap, 'sales_time');
            $sim = $this->getCellValue($row, $columnMap, 'sim');
            $rawExpiration = $this->getCellValue($row, $columnMap, 'expiration_date');
            $group = $this->getCellValue($row, $columnMap, 'group');
            $iccid = $this->getCellValue($row, $columnMap, 'iccid');
            $imsi = $this->getCellValue($row, $columnMap, 'imsi');
            $rawMileage = $this->getCellValue($row, $columnMap, 'mileage');

            // Parse values
            $activatedDate = $this->parseDate($rawActivated);
            $salesTime = $this->parseDate($rawSalesTime);
            [$expirationDate, $cleanRawExpiration] = $this->parseExpiration($rawExpiration);
            $mileage = $this->parseMileage($rawMileage);

            $attributes = [
                'device_name' => $deviceName ?: ($imei ?: 'Unknown Device'),
                'imei' => $imei,
                'model' => $model,
                'activated_date' => $activatedDate,
                'sales_time' => $salesTime,
                'sim' => $sim,
                'expiration_date' => $expirationDate,
                'raw_expiration' => $cleanRawExpiration,
                'group_name' => $group,
                'iccid' => $iccid,
                'imsi' => $imsi,
                'mileage' => $mileage,
            ];

            // Match existing record
            $existing = null;
            if (! empty($imei)) {
                $existing = Device::where('imei', $imei)->first();
            }
            if (! $existing && ! empty($deviceName)) {
                $existing = Device::where('device_name', $deviceName)
                    ->where('created_by', $userId)
                    ->first();
            }

            if ($existing) {
                if ($mode === 'append') {
                    $skippedCount++;

                    continue;
                }

                $existing->update($attributes);
                if ($linkService->linkDevice($existing)) {
                    $pairedCount++;
                }
                $updatedCount++;
            } else {
                $newDevice = Device::create([
                    'created_by' => $userId,
                    ...$attributes,
                ]);
                if ($linkService->linkDevice($newDevice)) {
                    $pairedCount++;
                }
                $importedCount++;
            }
        }

        $totalProcessed = $importedCount + $updatedCount;

        $msgParts = [];
        if ($importedCount > 0) {
            $msgParts[] = "{$importedCount} added";
        }
        if ($updatedCount > 0) {
            $msgParts[] = "{$updatedCount} updated";
        }
        if ($pairedCount > 0) {
            $msgParts[] = "{$pairedCount} auto-linked to vehicles";
        }
        if ($skippedCount > 0) {
            $msgParts[] = "{$skippedCount} skipped";
        }

        $summary = ! empty($msgParts) ? implode(', ', $msgParts) : '0 records processed';

        return [
            'success' => $totalProcessed > 0 || $skippedCount > 0,
            'count' => $totalProcessed,
            'mode' => $mode,
            'message' => $totalProcessed > 0
                ? "Successfully processed devices: {$summary}."
                : ($skippedCount > 0 ? "No new devices added ({$skippedCount} existing skipped)." : 'No valid device records found to import.'),
        ];
    }

    /**
     * Find the header row by matching known column aliases.
     *
     * @param  array<int, mixed>  $rows
     * @return array{0: int, 1: array<string, int>}|null
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

            // If device_name or imei matched, plus at least 1 other column
            if ((isset($columnMap['device_name']) || isset($columnMap['imei'])) && $matchedKeys >= 2) {
                return [$r, $columnMap];
            }
        }

        return null;
    }

    /**
     * Get cell value from mapped column.
     *
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columnMap
     */
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
     * Parse date string or Excel date serial.
     */
    public function parseDate(?string $val): ?string
    {
        if (! $val) {
            return null;
        }

        $val = trim($val);

        // Numeric Excel timestamp
        if (is_numeric($val) && (float) $val > 30000 && (float) $val < 60000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $val)->format('Y-m-d');
            } catch (\Throwable) {
                // Fallback
            }
        }

        // Standard date regex YYYY-MM-DD
        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $val, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        try {
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse expiration string, e.g. "2026-10-04(Expires in 20 days)", "Expired", "2027-05-02".
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function parseExpiration(?string $val): array
    {
        if (! $val) {
            return [null, null];
        }

        $clean = trim($val);

        // If it starts with "Expired"
        if (str_starts_with(strtolower($clean), 'expired')) {
            return [null, $clean];
        }

        // Numeric Excel timestamp
        if (is_numeric($clean) && (float) $clean > 30000 && (float) $clean < 60000) {
            try {
                $dateStr = ExcelDate::excelToDateTimeObject((float) $clean)->format('Y-m-d');

                return [$dateStr, $clean];
            } catch (\Throwable) {
                // Fallback
            }
        }

        // Check if date appears in string: "2026-10-04(Expires in 20 days)" or "2026-10-04"
        if (preg_match('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $clean, $m)) {
            $dateStr = sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);

            return [$dateStr, $clean];
        }

        try {
            $parsed = Carbon::parse($clean)->format('Y-m-d');

            return [$parsed, $clean];
        } catch (\Throwable) {
            return [null, $clean];
        }
    }

    /**
     * Parse mileage into float.
     */
    public function parseMileage(?string $val): ?float
    {
        if ($val === null || $val === '') {
            return null;
        }

        $clean = str_replace([',', 'km', 'KM', ' '], '', trim($val));

        return is_numeric($clean) ? (float) $clean : null;
    }
}
