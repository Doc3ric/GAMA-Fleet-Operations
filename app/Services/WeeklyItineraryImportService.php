<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdvancedItinerary;
use App\Models\Location;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

class WeeklyItineraryImportService
{
    public function __construct(
        protected SpreadsheetViewerService $viewerService,
        protected XlsbReaderService $xlsbReader
    ) {}

    /**
     * Parse an uploaded Weekly Itinerary Report (.xlsx, .xlsb, or .xls) without modifying the database.
     *
     * @return array<string, mixed>
     */
    public function parse(UploadedFile|string $file, ?string $originalName = null): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $fileName = $originalName ?? ($file instanceof UploadedFile ? $file->getClientOriginalName() : basename($filePath));
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (! in_array($extension, ['xlsx', 'xlsb', 'xls'], true)) {
            throw new RuntimeException("Unsupported file type: .{$extension}. Allowed formats are .xlsx, .xlsb, and .xls.");
        }

        // 1. Locate the correct worksheet
        $sheets = $this->viewerService->getWorksheets($filePath, $extension);
        if (empty($sheets)) {
            throw new RuntimeException('No worksheets found in the uploaded workbook.');
        }

        $targetSheet = $this->identifyItinerarySheet($sheets, $filePath, $extension);
        $rows = $this->viewerService->readWorksheet($filePath, $targetSheet['id'], $extension);

        if (empty($rows)) {
            throw new RuntimeException("The worksheet '{$targetSheet['name']}' contains no rows.");
        }

        // 2. Extract header metadata
        $headerData = $this->extractHeaderMetadata($rows);

        // 3. Resolve vehicle
        $vehicleMatch = $this->resolveVehicle($headerData['plate_number_raw'], $headerData['driver_name_raw']);

        // 4. Find table header and extract data rows
        $tableInfo = $this->extractTableRows($rows);
        $rawRows = $tableInfo['rows'];
        $columnMap = $tableInfo['column_map'];

        if (empty($rawRows)) {
            throw new RuntimeException("No valid trip/leg rows found under the table headers in sheet '{$targetSheet['name']}'.");
        }

        // 5. Group rows by date
        $avgConsumption = $vehicleMatch['vehicle']?->average_fuel_consumption
            ?? $vehicleMatch['vehicle']?->average_consumption
            ?? $headerData['avg_consumption_in_sheet'];

        if ($avgConsumption !== null) {
            $avgConsumption = (float) $avgConsumption;
        }

        $dateGroups = $this->groupRowsByDate($rawRows, $avgConsumption, $headerData['week_date_raw']);

        // 6. Compute overall metrics
        $totalDistance = 0.0;
        $totalLegs = 0;
        $overallFuelLiters = 0.0;

        foreach ($dateGroups as $group) {
            $totalDistance += $group['total_distance'];
            $totalLegs += count($group['legs']);
            if ($group['fuel_liters_required'] !== null) {
                $overallFuelLiters += $group['fuel_liters_required'];
            }
        }

        return [
            'file_name' => $fileName,
            'sheet_name' => $targetSheet['name'],
            'header' => [
                'driver_name' => $headerData['driver_name_raw'],
                'week_label' => $headerData['week_date_raw'],
                'plate_number_raw' => $headerData['plate_number_raw'],
                'extracted_eqtp_code' => $vehicleMatch['detected_eqtp_code'],
                'extracted_plate' => $vehicleMatch['detected_plate'],
            ],
            'vehicle_match' => $vehicleMatch,
            'avg_consumption' => $avgConsumption,
            'summary' => [
                'total_distance' => round($totalDistance, 2),
                'total_legs' => $totalLegs,
                'total_dates' => count($dateGroups),
                'overall_fuel_liters' => $overallFuelLiters > 0 ? (float) $overallFuelLiters : null,
            ],
            'date_groups' => $dateGroups,
            'column_map' => $columnMap,
        ];
    }

    /**
     * Import parsed Weekly Itinerary Report into the database creating AdvancedItinerary records per date.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function import(UploadedFile|string $file, int $userId, array $options = []): array
    {
        $parsed = $this->parse($file, $options['original_name'] ?? null);

        // Allow user overrides from preview modal
        $targetVehicleId = $options['vehicle_id'] ?? $parsed['vehicle_match']['vehicle']?->id;
        $driverName = $options['driver_name'] ?? $parsed['header']['driver_name'] ?? '—';
        $status = $options['status'] ?? AdvancedItinerary::STATUS_FINALIZED;

        // If vehicle is not matched in database, check if manual vehicle creation is requested
        if (! $targetVehicleId) {
            $eqtpCode = $options['custom_equipment_code'] ?? $parsed['vehicle_match']['detected_eqtp_code'];
            $plate = $options['custom_plate_number'] ?? $parsed['vehicle_match']['detected_plate'];
            $avgCons = $options['custom_average_consumption'] ?? $parsed['avg_consumption'];

            if (! empty($eqtpCode)) {
                $vehicle = Vehicle::firstOrCreate(
                    ['equipment_code' => $eqtpCode],
                    [
                        'plate_number' => $plate ?: null,
                        'operator_driver' => $driverName ?: null,
                        'average_fuel_consumption' => $avgCons !== null ? (float) $avgCons : null,
                        'created_by' => $userId,
                    ]
                );
                $targetVehicleId = $vehicle->id;
            } else {
                throw new RuntimeException('Cannot import itinerary without a recognized vehicle or manual equipment code.');
            }
        }

        $vehicle = Vehicle::find($targetVehicleId);
        $avgConsumption = $vehicle?->average_fuel_consumption ?? $vehicle?->average_consumption ?? $parsed['avg_consumption'];
        if ($avgConsumption !== null) {
            $avgConsumption = (float) $avgConsumption;
        }

        $calculationMethod = AdvancedItinerary::detectCalculationMethod($vehicle?->equipment_code ?? $parsed['header']['plate_number_raw'] ?? null);

        $createdItineraries = [];

        DB::transaction(function () use ($parsed, $targetVehicleId, $driverName, $status, $userId, $avgConsumption, $calculationMethod, &$createdItineraries) {
            foreach ($parsed['date_groups'] as $group) {
                $dailyDist = (float) $group['total_distance'];
                $fuelLiters = AdvancedItinerary::calculateFuelLiters($dailyDist, $avgConsumption, $calculationMethod);

                $destinationsList = [];
                foreach ($group['legs'] as $leg) {
                    if (! empty($leg['destination'])) {
                        $destinationsList[] = $leg['destination'];
                    } elseif (! empty($leg['purpose'])) {
                        $destinationsList[] = $leg['purpose'];
                    }
                }
                $combinedDest = implode(' → ', array_unique($destinationsList));
                if (mb_strlen($combinedDest) > 255) {
                    $combinedDest = mb_substr($combinedDest, 0, 252).'...';
                }

                $weekLabel = $parsed['header']['week_label'] ?: $group['formatted_date'];
                $itinerary = AdvancedItinerary::create([
                    'vehicle_id' => $targetVehicleId,
                    'driver_name' => $driverName,
                    'itinerary_date' => $group['date'],
                    'title' => "Weekly Itinerary: {$weekLabel} ({$group['date']})",
                    'destination' => $combinedDest ?: 'Weekly Itinerary Route',
                    'start_odo' => $group['start_odo'],
                    'end_odo' => $group['end_odo'],
                    'total_distance' => $dailyDist > 0 ? $dailyDist : null,
                    'fuel_liters_required' => $fuelLiters,
                    'calculation_method' => $calculationMethod,
                    'notes' => 'Imported from Weekly Itinerary Report: '.$parsed['file_name'],
                    'status' => $status,
                    'po_checked' => false,
                    'created_by' => $userId,
                ]);

                // Create legs for this date
                foreach ($group['legs'] as $idx => $legData) {
                    $originLocId = null;
                    if (! empty($legData['origin'])) {
                        $originLocId = Location::where('official_name', $legData['origin'])
                            ->orWhere('code', $legData['origin'])
                            ->value('id');
                    }

                    $destLocId = null;
                    if (! empty($legData['destination'])) {
                        $destLocId = Location::where('official_name', $legData['destination'])
                            ->orWhere('code', $legData['destination'])
                            ->value('id');
                    }

                    // Format purpose with time if available
                    $purpose = $legData['purpose'] ?? $legData['destination'] ?? 'Trip';
                    $timeParts = [];
                    if (! empty($legData['time_in'])) {
                        $timeParts[] = 'In: '.$legData['time_in'];
                    }
                    if (! empty($legData['time_out'])) {
                        $timeParts[] = 'Out: '.$legData['time_out'];
                    }
                    if (! empty($timeParts)) {
                        $purpose .= ' ('.implode(', ', $timeParts).')';
                    }

                    $itinerary->legs()->create([
                        'sort_order' => $idx,
                        'origin_location_id' => $originLocId,
                        'destination_location_id' => $destLocId,
                        'start_odo' => $legData['start_odo'],
                        'end_odo' => $legData['end_odo'],
                        'total_distance' => $legData['distance'],
                        'routing_source' => 'manual',
                        'purpose' => $purpose,
                    ]);
                }

                $createdItineraries[] = $itinerary;
            }
        });

        $count = count($createdItineraries);
        $totalLegs = $parsed['summary']['total_legs'];

        return [
            'success' => true,
            'count' => $count,
            'total_legs' => $totalLegs,
            'itineraries' => $createdItineraries,
            'message' => "Successfully imported {$count} Fuel PO record(s) across {$totalLegs} trip leg(s).",
        ];
    }

    /**
     * Identify the worksheet most likely containing the Weekly Itinerary Report.
     *
     * @param  array<int, array{id: string, name: string, target: string}>  $sheets
     * @return array{id: string, name: string, target: string}
     */
    protected function identifyItinerarySheet(array $sheets, string $filePath, string $extension): array
    {
        // 1. Look for sheets named ITINERARY
        foreach ($sheets as $s) {
            if (stripos($s['name'], 'ITINERARY') !== false) {
                return $s;
            }
        }

        // 2. Look for sheets named WEEKLY
        foreach ($sheets as $s) {
            if (stripos($s['name'], 'WEEKLY') !== false) {
                return $s;
            }
        }

        // 3. Inspect first 2 sheets for "WEEKLY ITINERARY REPORT" text
        for ($i = 0; $i < min(2, count($sheets)); $i++) {
            try {
                $rows = $this->viewerService->readWorksheet($filePath, $sheets[$i]['id'], $extension);
                for ($r = 0; $r < min(5, count($rows)); $r++) {
                    foreach ($rows[$r] as $cell) {
                        if (is_string($cell) && stripos($cell, 'ITINERARY REPORT') !== false) {
                            return $sheets[$i];
                        }
                    }
                }
            } catch (Throwable) {
                // ignore inspection failure and continue
            }
        }

        // 4. Default to first sheet
        return $sheets[0];
    }

    /**
     * Extract top metadata fields (NAME, FOR THE WEEK, PLATE NUMBER).
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{
     *     driver_name_raw: string,
     *     week_date_raw: string,
     *     plate_number_raw: string,
     *     avg_consumption_in_sheet: ?float
     * }
     */
    protected function extractHeaderMetadata(array $rows): array
    {
        $driverName = '';
        $weekDate = '';
        $plateNumber = '';
        $avgConsumption = null;

        $maxScanRows = min(15, count($rows));

        for ($r = 0; $r < $maxScanRows; $r++) {
            $row = $rows[$r];
            $colCount = count($row);

            for ($c = 0; $c < $colCount; $c++) {
                $val = trim((string) ($row[$c] ?? ''));
                if ($val === '') {
                    continue;
                }

                // Check NAME:
                if (preg_match('/^(?:NAME|DRIVER|OPERATOR)\s*:\s*(.*)$/i', $val, $m)) {
                    $driverName = trim($m[1]);
                    if ($driverName === '' && isset($row[$c + 1])) {
                        $driverName = trim((string) $row[$c + 1]);
                    }
                }

                // Check FOR THE WEEK:
                if (preg_match('/^(?:FOR THE WEEK|WEEK|PERIOD|DATE)\s*:\s*(.*)$/i', $val, $m)) {
                    $weekDate = trim($m[1]);
                    if ($weekDate === '' && isset($row[$c + 1])) {
                        $weekDate = trim((string) $row[$c + 1]);
                    }
                }

                // Check PLATE NUMBER: or EQT CODE:
                if (preg_match('/^(?:PLATE NUMBER|PLATE NO|PLATE|EQTP? CODE|EQUIPMENT)\s*:\s*(.*)$/i', $val, $m)) {
                    $plateNumber = trim($m[1]);
                    if ($plateNumber === '' && isset($row[$c + 1])) {
                        $plateNumber = trim((string) $row[$c + 1]);
                    }
                }
            }
        }

        // If weekDate is an Excel date serial (e.g. 46297), convert to readable date
        if (is_numeric($weekDate) && (float) $weekDate > 30000 && (float) $weekDate < 70000) {
            try {
                $weekDate = ExcelDate::excelToDateTimeObject((float) $weekDate)->format('F d, Y');
            } catch (Throwable) {
                // keep raw
            }
        }

        // Also scan summary block for AVE. CON. OF LITER/KM if present
        $totalRows = count($rows);
        for ($r = max(0, $totalRows - 15); $r < $totalRows; $r++) {
            $row = $rows[$r];
            foreach ($row as $idx => $cell) {
                $cellStr = trim((string) $cell);
                if (preg_match('/(?:AVE\.?\s*CON|AVERAGE\s*CONSUMPTION)/i', $cellStr)) {
                    // check adjacent cell
                    if (isset($row[$idx + 1]) && is_numeric($row[$idx + 1])) {
                        $avgConsumption = (float) $row[$idx + 1];
                    }
                }
            }
        }

        return [
            'driver_name_raw' => $driverName,
            'week_date_raw' => $weekDate,
            'plate_number_raw' => $plateNumber,
            'avg_consumption_in_sheet' => $avgConsumption,
        ];
    }

    /**
     * Resolve Vehicle from plate number/EQT code string (e.g. "IV 5 - LAO 8559", "EV 10", "NDY 5123").
     *
     * @return array{
     *     is_matched: bool,
     *     vehicle: ?Vehicle,
     *     detected_eqtp_code: ?string,
     *     detected_plate: ?string,
     *     confidence: string
     * }
     */
    protected function resolveVehicle(string $plateRaw, string $driverRaw): array
    {
        $raw = trim($plateRaw);
        if ($raw === '') {
            // Attempt match by driver name if plate is empty
            if (! empty($driverRaw)) {
                $vehicle = Vehicle::where('operator_driver', 'like', "%{$driverRaw}%")->first();
                if ($vehicle) {
                    return [
                        'is_matched' => true,
                        'vehicle' => $vehicle,
                        'detected_eqtp_code' => $vehicle->equipment_code,
                        'detected_plate' => $vehicle->plate_number,
                        'confidence' => 'driver_match',
                    ];
                }
            }

            return [
                'is_matched' => false,
                'vehicle' => null,
                'detected_eqtp_code' => null,
                'detected_plate' => null,
                'confidence' => 'none',
            ];
        }

        // Split by hyphen, slash, or commas: e.g. "IV 5 - LAO 8559" -> ["IV 5", "LAO 8559"]
        $tokens = array_map('trim', preg_split('/[\-\/]/', $raw) ?: [$raw]);
        $tokens = array_filter($tokens, fn ($t) => $t !== '');

        // 1. Try exact matches on tokens against equipment_code or plate_number
        foreach ($tokens as $token) {
            $v = Vehicle::where('equipment_code', $token)
                ->orWhere('plate_number', $token)
                ->first();
            if ($v) {
                return [
                    'is_matched' => true,
                    'vehicle' => $v,
                    'detected_eqtp_code' => $v->equipment_code,
                    'detected_plate' => $v->plate_number,
                    'confidence' => 'exact',
                ];
            }
        }

        // 1b. Check if driverRaw itself is an equipment_code (e.g. "EV 10")
        if (! empty($driverRaw)) {
            $v = Vehicle::where('equipment_code', trim($driverRaw))
                ->orWhereRaw("REPLACE(equipment_code, ' ', '') = ?", [preg_replace('/\s+/', '', $driverRaw)])
                ->first();
            if ($v) {
                return [
                    'is_matched' => true,
                    'vehicle' => $v,
                    'detected_eqtp_code' => $v->equipment_code,
                    'detected_plate' => $tokens[0] ?? $v->plate_number,
                    'confidence' => 'name_equipment_code',
                ];
            }
        }

        // 2. Try normalized string without spaces (e.g. "IV5", "LAO8559")
        foreach ($tokens as $token) {
            $clean = preg_replace('/\s+/', '', $token);
            $v = Vehicle::whereRaw("REPLACE(equipment_code, ' ', '') = ?", [$clean])
                ->orWhereRaw("REPLACE(plate_number, ' ', '') = ?", [$clean])
                ->first();
            if ($v) {
                return [
                    'is_matched' => true,
                    'vehicle' => $v,
                    'detected_eqtp_code' => $v->equipment_code,
                    'detected_plate' => $v->plate_number,
                    'confidence' => 'normalized',
                ];
            }
        }

        // 3. Fallback: propose the first token as EQT Code and second as Plate Number
        $detectedEqtp = $tokens[0] ?? $raw;
        $detectedPlate = $tokens[1] ?? null;

        return [
            'is_matched' => false,
            'vehicle' => null,
            'detected_eqtp_code' => $detectedEqtp,
            'detected_plate' => $detectedPlate,
            'confidence' => 'unmatched',
        ];
    }

    /**
     * Locate table column headers and extract valid trip rows.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{
     *     header_index: int,
     *     column_map: array<string, int>,
     *     rows: array<int, array<string, mixed>>
     * }
     */
    protected function extractTableRows(array $rows): array
    {
        $headerIndex = -1;
        $columnMap = [];

        $aliases = [
            'date' => ['date', 'trip_date', 'travel_date', 'dates'],
            'origin' => ['origin', 'orig', 'from', 'start_point', 'starting_point'],
            'start_odo' => ['start_odo', 'startodo', 'start_odometer', 'odometer_start', 'initial_odo', 'odo_start'],
            'destination' => ['destination', 'dest', 'to', 'destination_point', 'place'],
            'end_odo' => ['end_odo', 'endodo', 'end_odometer', 'odometer_end', 'final_odo', 'odo_end'],
            'distance' => ['distance', 'dist', 'distance_km', 'total_distance', 'km', 'kms'],
            'time_in' => ['time_in', 'timein', 'arrival', 'time_arrival'],
            'time_out' => ['time_out', 'timeout', 'departure', 'time_departure'],
            'purpose' => ['purpose', 'cargo', 'remarks', 'reason', 'activity', 'notes'],
        ];

        // Search for header row (contains at least DATE and either DESTINATION or DISTANCE)
        $totalRows = count($rows);
        for ($r = 0; $r < min(20, $totalRows); $r++) {
            $row = $rows[$r];
            $map = [];

            foreach ($row as $colIdx => $cell) {
                $norm = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $cell)));
                if ($norm === '') {
                    continue;
                }

                foreach ($aliases as $field => $fieldAliases) {
                    if (isset($map[$field])) {
                        continue;
                    }
                    foreach ($fieldAliases as $alias) {
                        if ($norm === $alias || str_starts_with($norm, $alias.'_') || str_ends_with($norm, '_'.$alias)) {
                            $map[$field] = $colIdx;
                            break;
                        }
                    }
                }
            }

            if (isset($map['date']) && (isset($map['destination']) || isset($map['distance']))) {
                $headerIndex = $r;
                $columnMap = $map;
                break;
            }
        }

        if ($headerIndex === -1) {
            throw new RuntimeException('Could not detect the table header row. Expected headers: DATE, DESTINATION, DISTANCE, etc.');
        }

        // Read data rows starting after header row
        $extractedRows = [];
        for ($r = $headerIndex + 1; $r < $totalRows; $r++) {
            $row = $rows[$r];

            // Stop condition: summary row (e.g. TOTAL DISTANCE, AVERAGE CONSUMPTION, Prepared by)
            $firstCell = trim((string) ($row[0] ?? $row[1] ?? ''));
            $rowText = implode(' ', array_map('strval', $row));
            if (preg_match('/(?:TOTAL\s*DISTANCE|AVERAGE\s*CONSUMPTION|LITER\s*FOR\s*PO|Prepared\s*by)/i', $rowText)) {
                break;
            }

            // Check if row has meaningful data
            $hasContent = false;
            foreach ($row as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (! $hasContent) {
                continue;
            }

            $dateRaw = isset($columnMap['date']) ? $row[$columnMap['date']] ?? null : null;
            $origin = isset($columnMap['origin']) ? trim((string) ($row[$columnMap['origin']] ?? '')) : '';
            $destination = isset($columnMap['destination']) ? trim((string) ($row[$columnMap['destination']] ?? '')) : '';
            $purpose = isset($columnMap['purpose']) ? trim((string) ($row[$columnMap['purpose']] ?? '')) : '';

            $startOdo = isset($columnMap['start_odo']) ? $this->parseNumeric($row[$columnMap['start_odo']] ?? null) : null;
            $endOdo = isset($columnMap['end_odo']) ? $this->parseNumeric($row[$columnMap['end_odo']] ?? null) : null;
            $distance = isset($columnMap['distance']) ? $this->parseNumeric($row[$columnMap['distance']] ?? null) : null;

            // If distance was not provided or 0, compute from end_odo - start_odo
            if (($distance === null || $distance <= 0) && $startOdo !== null && $endOdo !== null && $endOdo > $startOdo) {
                $distance = round($endOdo - $startOdo, 2);
            }

            $timeIn = isset($columnMap['time_in']) ? $this->parseTime($row[$columnMap['time_in']] ?? null) : '';
            $timeOut = isset($columnMap['time_out']) ? $this->parseTime($row[$columnMap['time_out']] ?? null) : '';

            // Require at least a destination, purpose, or positive distance
            if ($destination === '' && $purpose === '' && ($distance === null || $distance <= 0)) {
                continue;
            }

            $extractedRows[] = [
                'row_index' => $r,
                'date_raw' => $dateRaw,
                'origin' => $origin,
                'start_odo' => $startOdo,
                'destination' => $destination ?: ($purpose ?: 'Stop'),
                'end_odo' => $endOdo,
                'distance' => $distance ?? 0.0,
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'purpose' => $purpose,
            ];
        }

        return [
            'header_index' => $headerIndex,
            'column_map' => $columnMap,
            'rows' => $extractedRows,
        ];
    }

    /**
     * Group extracted trip rows by date.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    protected function groupRowsByDate(array $rows, ?float $avgConsumption, ?string $fallbackWeekDate): array
    {
        $groups = [];
        $lastResolvedDate = null;

        // Try to parse fallback week date if rows lack individual dates
        $fallbackDateStr = null;
        if (! empty($fallbackWeekDate)) {
            $fallbackDateStr = $this->parseDate($fallbackWeekDate);
        }
        if (! $fallbackDateStr) {
            $fallbackDateStr = now()->format('Y-m-d');
        }

        foreach ($rows as $row) {
            $resolvedDate = $this->parseDate($row['date_raw']);

            if (! $resolvedDate) {
                $resolvedDate = $lastResolvedDate ?: $fallbackDateStr;
            }

            $lastResolvedDate = $resolvedDate;
            $row['resolved_date'] = $resolvedDate;

            if (! isset($groups[$resolvedDate])) {
                $groups[$resolvedDate] = [
                    'date' => $resolvedDate,
                    'formatted_date' => Carbon::parse($resolvedDate)->format('F d, Y'),
                    'start_odo' => null,
                    'end_odo' => null,
                    'total_distance' => 0.0,
                    'legs' => [],
                ];
            }

            $groups[$resolvedDate]['legs'][] = $row;
            $groups[$resolvedDate]['total_distance'] += (float) $row['distance'];

            if ($row['start_odo'] !== null && $groups[$resolvedDate]['start_odo'] === null) {
                $groups[$resolvedDate]['start_odo'] = (float) $row['start_odo'];
            }
            if ($row['end_odo'] !== null) {
                $groups[$resolvedDate]['end_odo'] = (float) $row['end_odo'];
            }
        }

        // Calculate liters for PO for each date group
        foreach ($groups as $dateKey => &$group) {
            $group['total_distance'] = round($group['total_distance'], 2);
            $group['fuel_liters_required'] = AdvancedItinerary::calculateFuelLiters($group['total_distance'], $avgConsumption);
            $group['raw_liters'] = ($avgConsumption && $avgConsumption > 0)
                ? round($group['total_distance'] / $avgConsumption, 2)
                : null;
        }
        unset($group);

        return $groups;
    }

    /**
     * Parse date value (handles Excel serial numbers, ISO strings, textual dates).
     */
    protected function parseDate(mixed $val): ?string
    {
        if ($val === null || trim((string) $val) === '') {
            return null;
        }

        // If numeric Excel date serial (e.g. 46274)
        if (is_numeric($val) && (float) $val > 30000 && (float) $val < 70000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $val)->format('Y-m-d');
            } catch (Throwable) {
                // fallback
            }
        }

        $str = trim((string) $val);

        // Remove range parts if user wrote "Sept 19 - 20, 2026" -> extract first date
        if (preg_match('/^([a-zA-Z]+\s+\d{1,2})\s*[\-\–]\s*\d{1,2}(?:st|nd|rd|th)?,\s*(\d{4})/i', $str, $m)) {
            $str = "{$m[1]}, {$m[2]}";
        }

        try {
            return Carbon::parse($str)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parse numeric string, stripping "KM", commas, and spaces.
     */
    protected function parseNumeric(mixed $val): ?float
    {
        if ($val === null) {
            return null;
        }

        if (is_numeric($val)) {
            return (float) $val;
        }

        $clean = trim((string) $val);
        // Remove "KM", "km", commas
        $clean = preg_replace('/[^\d.]/', '', $clean);

        return $clean !== '' && is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Parse time string or Excel fraction of day.
     */
    protected function parseTime(mixed $val): string
    {
        if ($val === null || trim((string) $val) === '') {
            return '';
        }

        // Excel fractional day (e.g. 0.888888 for 9:20 PM)
        if (is_numeric($val) && (float) $val >= 0 && (float) $val < 1) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $val)->format('h:i A');
            } catch (Throwable) {
                // fallback
            }
        }

        $str = trim((string) $val);

        try {
            return Carbon::parse($str)->format('h:i A');
        } catch (Throwable) {
            return $str;
        }
    }
}
