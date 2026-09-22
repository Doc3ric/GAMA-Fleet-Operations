<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class VehicleImportService
{
    /**
     * Column key mappings to recognize header variations.
     *
     * @var array<string, list<string>>
     */
    protected array $headerAliases = [
        'equipment_code' => ['equipmentcode', 'equipment', 'code', 'eqcode', 'unitcode', 'vehiclecode', 'equipmentid'],
        'vehicle_type' => ['vehicletype', 'type', 'equipmenttype', 'category', 'kind'],
        'model' => ['model', 'devicemodel', 'brandmodel', 'makeandmodel', 'make', 'brand'],
        'plate_number' => ['platenumber', 'plate', 'plateno', 'platenum'],
        'date_acquired' => ['dateacquired', 'acquired', 'acquisitiondate', 'purchasedate', 'date'],
        'fuel' => ['fuel', 'fuelconsumption', 'fuelrate', 'fuelmin', 'fuelmax', 'fuelratehr'],
        'status' => ['status', 'vehiclestatus', 'state', 'condition'],
        'location' => ['location', 'site', 'assignedlocation', 'yard', 'place'],
        'project_code' => ['projectcode', 'project', 'projectno', 'prjcode'],
        'operator_driver' => ['operatordriver', 'operator', 'driver', 'driveroperator', 'assigneddriver'],
        'helper' => ['helper', 'assistant', 'crew'],
        'gps_status' => ['gpsstatus', 'gps', 'gpsequipped', 'trackerstatus'],
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
                'message' => 'Could not find valid column headers. Please ensure headers include Equipment Code, Vehicle Type, Model, etc.',
            ];
        }

        [$headerRowIndex, $columnMap] = $headerInfo;

        // 2. Handle replace mode
        if ($mode === 'replace') {
            Vehicle::all()->each(function ($v) {
                if ($v->image) {
                    Storage::disk('public')->delete($v->image);
                }
            });
            Vehicle::truncate();
        }

        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $pairedCount = 0;
        $userId = $userId ?: auth()->id();
        $linkService = new VehicleDeviceLinkService;

        // Cache vehicle types to minimize queries
        $vehicleTypes = VehicleType::all();

        // 3. Process data rows
        $totalRows = count($rows);
        for ($i = $headerRowIndex + 1; $i < $totalRows; $i++) {
            $row = $rows[$i];

            $equipmentCode = $this->getCellValue($row, $columnMap, 'equipment_code');

            // Skip empty equipment codes
            if (empty($equipmentCode)) {
                continue;
            }

            $rawVehicleType = $this->getCellValue($row, $columnMap, 'vehicle_type');
            $model = $this->getCellValue($row, $columnMap, 'model');
            $plateNumber = $this->getCellValue($row, $columnMap, 'plate_number');
            $rawDateAcquired = $this->getCellValue($row, $columnMap, 'date_acquired');
            $rawFuel = $this->getCellValue($row, $columnMap, 'fuel');
            $rawStatus = $this->getCellValue($row, $columnMap, 'status');
            $location = $this->getCellValue($row, $columnMap, 'location');
            $projectCode = $this->getCellValue($row, $columnMap, 'project_code');
            $operatorDriver = $this->getCellValue($row, $columnMap, 'operator_driver');
            $helper = $this->getCellValue($row, $columnMap, 'helper');
            $rawGpsStatus = $this->getCellValue($row, $columnMap, 'gps_status');

            // Parse fields
            $vehicleTypeId = $this->resolveVehicleType($rawVehicleType, $equipmentCode, $vehicleTypes);
            $dateAcquired = $this->parseDate($rawDateAcquired);
            [$fuelMin, $fuelMax, $fuelUnit] = $this->parseFuel($rawFuel);
            [$statusValue, $statusLabel] = $this->parseStatus($rawStatus);
            $gpsStatus = $this->parseGpsStatus($rawGpsStatus);

            $attributes = [
                'vehicle_type_id' => $vehicleTypeId,
                'model' => $model ?: null,
                'plate_number' => $plateNumber ?: null,
                'date_acquired' => $dateAcquired,
                'fuel_min' => $fuelMin,
                'fuel_max' => $fuelMax,
                'fuel_unit' => $fuelUnit,
                'status_value' => $statusValue,
                'status_label' => $statusLabel,
                'location' => $location ?: null,
                'project_code' => $projectCode ?: null,
                'operator_driver' => $operatorDriver ?: null,
                'helper' => $helper ?: null,
                'gps_status' => $gpsStatus,
            ];

            $existing = Vehicle::where('equipment_code', $equipmentCode)->first();

            if ($existing) {
                if ($mode === 'append') {
                    // Skip existing in append mode
                    $skippedCount++;

                    continue;
                }

                // Default update mode
                $existing->update($attributes);
                if ($linkService->linkVehicle($existing)) {
                    $pairedCount++;
                }
                $updatedCount++;
            } else {
                $newVehicle = Vehicle::create([
                    'equipment_code' => $equipmentCode,
                    'created_by' => $userId,
                    ...$attributes,
                ]);
                if ($linkService->linkVehicle($newVehicle)) {
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
            $msgParts[] = "{$pairedCount} auto-linked to GPS trackers";
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
                ? "Successfully processed vehicles: {$summary}."
                : ($skippedCount > 0 ? "No new vehicles added ({$skippedCount} skipped)." : 'No valid vehicle records found to import.'),
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

            // If equipment_code or at least 2 other columns matched
            if (isset($columnMap['equipment_code']) || $matchedKeys >= 2) {
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
     * Resolve or find VehicleType ID.
     *
     * @param  Collection<int, VehicleType>  $vehicleTypes
     */
    protected function resolveVehicleType(?string $rawType, string $equipmentCode, &$vehicleTypes): ?int
    {
        if ($rawType) {
            $cleanType = trim($rawType);
            $match = $vehicleTypes->first(function ($t) use ($cleanType) {
                return strcasecmp($t->name, $cleanType) === 0 || strcasecmp($t->code, $cleanType) === 0;
            });

            if ($match) {
                return $match->id;
            }

            // Create new type if it doesn't exist
            $codeCandidate = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $cleanType), 0, 4));
            if (empty($codeCandidate)) {
                $codeCandidate = 'VT';
            }

            $suffix = 1;
            $finalCode = $codeCandidate;
            while ($vehicleTypes->contains('code', $finalCode)) {
                $finalCode = substr($codeCandidate, 0, 2).$suffix;
                $suffix++;
            }

            $newType = VehicleType::create([
                'name' => strtoupper($cleanType),
                'code' => $finalCode,
            ]);

            $vehicleTypes->push($newType);

            return $newType->id;
        }

        // Try to infer from equipment code prefix (e.g. "BH 5" -> "BH")
        if (preg_match('/^([A-Za-z]+)/', trim($equipmentCode), $matches)) {
            $prefix = strtoupper($matches[1]);
            $match = $vehicleTypes->firstWhere('code', $prefix);
            if ($match) {
                return $match->id;
            }
        }

        return null;
    }

    /**
     * Parse Fuel string (e.g. "16-20 / LIT/HR", "16–20 LIT/HR", "20 LIT/HR", "18").
     *
     * @return array{0: ?float, 1: ?float, 2: string}
     */
    public function parseFuel(?string $val): array
    {
        if (! $val) {
            return [null, null, 'LIT/HR'];
        }

        $val = trim($val);

        // Normalize dashes
        $val = str_replace(['–', '—'], '-', $val);

        // Check range: "16-20 / LIT/HR" or "16-20 LIT/HR" or "16-20"
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*-\s*([0-9]+(?:\.[0-9]+)?)(?:\s*\/?\s*(.*))?$/i', $val, $matches)) {
            $min = (float) $matches[1];
            $max = (float) $matches[2];
            $unit = ! empty($matches[3]) ? trim($matches[3]) : 'LIT/HR';

            return [$min, $max, $unit];
        }

        // Single value: "20 / LIT/HR" or "20 LIT/HR" or "20"
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)(?:\s*\/?\s*(.*))?$/i', $val, $matches)) {
            $valNum = (float) $matches[1];
            $unit = ! empty($matches[2]) ? trim($matches[2]) : 'LIT/HR';

            return [$valNum, $valNum, $unit];
        }

        return [null, null, 'LIT/HR'];
    }

    /**
     * Parse Status string (e.g. "0.8 / RUNNING", "RUNNING", "0.8").
     *
     * @return array{0: ?float, 1: ?string}
     */
    public function parseStatus(?string $val): array
    {
        if (! $val) {
            return [null, null];
        }

        $val = trim($val);

        // Value / Label pattern
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*\/\s*(.*)$/i', $val, $matches)) {
            $num = (float) $matches[1];
            $label = trim($matches[2]);

            return [$num, strtoupper($label)];
        }

        // Pure numeric
        if (is_numeric($val)) {
            return [(float) $val, null];
        }

        // Pure text
        return [null, strtoupper($val)];
    }

    /**
     * Parse GPS status into allowed enum: YES, NO, EXPIRED, FOR_CHECKUP.
     */
    public function parseGpsStatus(?string $val): string
    {
        if (! $val) {
            return 'NO';
        }

        $normalized = strtoupper(trim($val));

        if (str_contains($normalized, 'CHECK')) {
            return 'FOR_CHECKUP';
        }

        if (in_array($normalized, ['YES', 'Y', '1', 'TRUE'], true)) {
            return 'YES';
        }

        if (str_contains($normalized, 'EXPIR')) {
            return 'EXPIRED';
        }

        return 'NO';
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

        // If numeric Excel timestamp (approx between years 1990 and 2050)
        if (is_numeric($val) && (float) $val > 30000 && (float) $val < 60000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $val)->format('Y-m-d');
            } catch (\Throwable $e) {
                // Fallback
            }
        }

        try {
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
