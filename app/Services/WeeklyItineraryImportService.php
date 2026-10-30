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
     * Detects all itinerary tables across all worksheets.
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

        // 1. Locate worksheets
        $sheets = $this->viewerService->getWorksheets($filePath, $extension);
        if (empty($sheets)) {
            throw new RuntimeException('No worksheets found in the uploaded workbook.');
        }

        // 2. Scan sheets for Weekly Itinerary tables
        $allTables = [];

        // Check if there is a primary itinerary sheet, but scan all eligible sheets
        foreach ($sheets as $sheet) {
            try {
                $rows = $this->viewerService->readWorksheet($filePath, $sheet['id'], $extension);
                if (empty($rows)) {
                    continue;
                }

                $sheetTables = $this->extractTablesFromWorksheet($rows, $sheet['name']);
                foreach ($sheetTables as $st) {
                    $allTables[] = $st;
                }
            } catch (Throwable) {
                // Ignore errors reading a specific sheet and continue with others
                continue;
            }
        }

        if (empty($allTables)) {
            throw new RuntimeException('No valid Weekly Itinerary tables found in the uploaded workbook. Expected headers: DATE, DESTINATION, DISTANCE, etc.');
        }

        // Re-index tables across workbook
        foreach ($allTables as $idx => &$t) {
            $t['table_index'] = $idx;
        }
        unset($t);

        // 3. Compute overall workbook metrics
        $overallDistance = 0.0;
        $overallLegs = 0;
        $overallFuelLiters = 0.0;

        foreach ($allTables as $table) {
            $overallDistance += (float) $table['total_distance'];
            $overallLegs += count($table['legs']);
            if ($table['fuel_liters_required'] !== null) {
                $overallFuelLiters += (float) $table['fuel_liters_required'];
            }
        }

        $primaryTable = $allTables[0];

        // Legacy date_groups mapped 1-to-1 with tables for backwards compatibility
        $legacyDateGroups = array_map(function ($table) {
            return [
                'date' => $table['itinerary_date'],
                'formatted_date' => Carbon::parse($table['itinerary_date'])->format('F d, Y'),
                'start_odo' => $table['start_odo'],
                'end_odo' => $table['end_odo'],
                'total_distance' => $table['total_distance'],
                'fuel_liters_required' => $table['fuel_liters_required'],
                'raw_liters' => ($table['avg_consumption'] && $table['avg_consumption'] > 0)
                    ? round($table['total_distance'] / $table['avg_consumption'], 2)
                    : null,
                'legs' => $table['legs'],
            ];
        }, $allTables);

        return [
            'file_name' => $fileName,
            'sheet_name' => $primaryTable['sheet_name'],
            'header' => $primaryTable['header'],
            'vehicle_match' => $primaryTable['vehicle_match'],
            'avg_consumption' => $primaryTable['avg_consumption'],
            'summary' => [
                'total_tables' => count($allTables),
                'total_distance' => round($overallDistance, 2),
                'total_legs' => $overallLegs,
                'total_dates' => count($allTables),
                'overall_fuel_liters' => $overallFuelLiters > 0 ? (float) $overallFuelLiters : null,
            ],
            'tables' => $allTables,
            'date_groups' => $legacyDateGroups,
            'column_map' => $primaryTable['column_map'] ?? [],
        ];
    }

    /**
     * Extract all Weekly Itinerary tables from a single worksheet.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function extractTablesFromWorksheet(array $rows, string $sheetName): array
    {
        $totalRows = count($rows);
        if ($totalRows === 0) {
            return [];
        }

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

        // 1. Locate all table header rows in this sheet
        $headerIndices = [];

        for ($r = 0; $r < $totalRows; $r++) {
            $row = $rows[$r];
            $rowText = implode(' ', array_map('strval', $row));

            // Summary rows are not headers
            if (preg_match('/(?:TOTAL\s*DISTANCE|AVERAGE\s*CONSUMPTION|AVE\.?\s*CON|LITER\s*FOR\s*PO|Prepared\s*by)/i', $rowText)) {
                continue;
            }

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

            // An itinerary table header MUST have DATE and (DESTINATION or DISTANCE)
            if (isset($map['date']) && (isset($map['destination']) || isset($map['distance']))) {
                $headerIndices[] = [
                    'row' => $r,
                    'map' => $map,
                ];
            }
        }

        if (empty($headerIndices)) {
            return [];
        }

        // Sheet-level metadata fallback
        $sheetMetadata = $this->extractHeaderMetadata($rows, 0, min(15, $totalRows));

        $tables = [];

        for ($i = 0; $i < count($headerIndices); $i++) {
            $hRow = $headerIndices[$i]['row'];
            $colMap = $headerIndices[$i]['map'];

            $prevHeaderRow = $i > 0 ? $headerIndices[$i - 1]['row'] : -1;
            $nextHeaderRow = $i + 1 < count($headerIndices) ? $headerIndices[$i + 1]['row'] : $totalRows;

            // Metadata for this table (scan above current header row)
            $scanMetaStart = max(0, $prevHeaderRow + 1, $hRow - 20);
            $tableMetadata = $this->extractHeaderMetadata($rows, $scanMetaStart, $hRow);

            $driverName = $tableMetadata['driver_name_raw'] ?: $sheetMetadata['driver_name_raw'];
            $weekDate = $tableMetadata['week_date_raw'] ?: $sheetMetadata['week_date_raw'];
            $plateNumber = $tableMetadata['plate_number_raw'] ?: $sheetMetadata['plate_number_raw'];
            $avgFromHeader = $tableMetadata['avg_consumption_in_sheet'] ?? $sheetMetadata['avg_consumption_in_sheet'];

            // Extract trip rows (legs)
            $legs = [];
            $summaryStartRow = null;

            for ($r = $hRow + 1; $r < $nextHeaderRow; $r++) {
                $row = $rows[$r];
                $rowText = implode(' ', array_map('strval', $row));

                // Stop condition: summary row
                if (preg_match('/(?:TOTAL\s*DISTANCE|AVERAGE\s*CONSUMPTION|AVE\.?\s*CON|LITER\s*FOR\s*PO|NO\.?\s*OF\s*LITERS|Prepared\s*by|Checked\s*by)/i', $rowText)) {
                    $summaryStartRow = $r;
                    break;
                }

                // Stop condition: next metadata block
                if (preg_match('/^(?:NAME|DRIVER|OPERATOR|PLATE NUMBER|FOR THE WEEK)\s*:/i', trim((string) ($row[0] ?? $row[1] ?? '')))) {
                    $summaryStartRow = $r;
                    break;
                }

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

                $dateRaw = isset($colMap['date']) ? $row[$colMap['date']] ?? null : null;
                $origin = isset($colMap['origin']) ? trim((string) ($row[$colMap['origin']] ?? '')) : '';
                $destination = isset($colMap['destination']) ? trim((string) ($row[$colMap['destination']] ?? '')) : '';
                $purpose = isset($colMap['purpose']) ? trim((string) ($row[$colMap['purpose']] ?? '')) : '';
                $startOdo = isset($colMap['start_odo']) ? $this->parseNumeric($row[$colMap['start_odo']] ?? null) : null;
                $endOdo = isset($colMap['end_odo']) ? $this->parseNumeric($row[$colMap['end_odo']] ?? null) : null;
                $distance = isset($colMap['distance']) ? $this->parseNumeric($row[$colMap['distance']] ?? null) : null;

                if (($distance === null || $distance <= 0) && $startOdo !== null && $endOdo !== null && $endOdo > $startOdo) {
                    $distance = round($endOdo - $startOdo, 2);
                }

                $timeIn = isset($colMap['time_in']) ? $this->parseTime($row[$colMap['time_in']] ?? null) : '';
                $timeOut = isset($colMap['time_out']) ? $this->parseTime($row[$colMap['time_out']] ?? null) : '';

                if ($destination === '' && $purpose === '' && ($distance === null || $distance <= 0) && $dateRaw === null) {
                    continue;
                }

                $legs[] = [
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

            // Extract summary block
            $summaryTotalDist = null;
            $summaryAvgCons = null;
            $summaryLiterPo = null;

            $scanSumStart = $summaryStartRow ?? ($hRow + count($legs) + 1);
            $scanSumEnd = min($nextHeaderRow, $scanSumStart + 15);

            for ($s = $scanSumStart; $s < $scanSumEnd; $s++) {
                if (! isset($rows[$s])) {
                    break;
                }
                $sRow = $rows[$s];
                $sText = implode(' ', array_map('strval', $sRow));

                if (preg_match('/TOTAL\s*DISTANCE/i', $sText)) {
                    $val = $this->findNumericInRow($sRow, $colMap['distance'] ?? null);
                    if ($val !== null && $val > 0) {
                        $summaryTotalDist = $val;
                    }
                }
                if (preg_match('/(?:AVE\.?\s*CON|AVERAGE\s*CONSUMPTION)/i', $sText)) {
                    $val = $this->findNumericInRow($sRow);
                    if ($val !== null && $val > 0) {
                        $summaryAvgCons = $val;
                    }
                }
                if (preg_match('/(?:LITER(?:S)?\s*FOR\s*PO|NO\.?\s*OF\s*LITERS)/i', $sText)) {
                    $val = $this->findNumericInRow($sRow);
                    if ($val !== null && $val > 0) {
                        $summaryLiterPo = $val;
                    }
                }
            }

            $legsDistSum = round((float) array_sum(array_column($legs, 'distance')), 2);
            $tableTotalDistance = $summaryTotalDist ?? ($legsDistSum > 0 ? $legsDistSum : 0.0);

            // Skip empty / dummy templates
            if ($tableTotalDistance <= 0 && empty($legs)) {
                continue;
            }

            // Resolve vehicle
            $vehicleMatch = $this->resolveVehicle($plateNumber, $driverName);
            $detectedEqtp = $vehicleMatch['vehicle']?->equipment_code ?? $vehicleMatch['detected_eqtp_code'] ?? '—';
            $detectedPlate = $vehicleMatch['vehicle']?->plate_number ?? $vehicleMatch['detected_plate'] ?? '—';

            $calcMethod = AdvancedItinerary::detectCalculationMethod($detectedEqtp);
            $avgConsumption = $summaryAvgCons ?? $avgFromHeader ?? $vehicleMatch['vehicle']?->average_fuel_consumption ?? $vehicleMatch['vehicle']?->average_consumption ?? null;
            if ($avgConsumption !== null) {
                $avgConsumption = (float) $avgConsumption;
            }

            if ($summaryLiterPo !== null && $summaryLiterPo > 0) {
                $fuelLiters = (float) $summaryLiterPo;
            } else {
                $fuelLiters = AdvancedItinerary::calculateFuelLiters($tableTotalDistance, $avgConsumption, $calcMethod);
            }

            // Itinerary date
            $itineraryDate = $this->parseDate($weekDate);
            if (! $itineraryDate && ! empty($legs)) {
                $itineraryDate = $this->parseDate($legs[0]['date_raw']);
            }
            if (! $itineraryDate) {
                $itineraryDate = now()->format('Y-m-d');
            }

            // Start & End Odometer
            $startOdo = null;
            $endOdo = null;
            foreach ($legs as $l) {
                if ($startOdo === null && $l['start_odo'] !== null) {
                    $startOdo = $l['start_odo'];
                }
                if ($l['end_odo'] !== null) {
                    $endOdo = $l['end_odo'];
                }
            }

            // Destination and Purpose Summary
            $destinations = array_values(array_unique(array_filter(array_column($legs, 'destination'))));
            $destSummary = implode(' → ', $destinations);
            if (mb_strlen($destSummary) > 255) {
                $destSummary = mb_substr($destSummary, 0, 252).'...';
            }

            $purposes = array_values(array_unique(array_filter(array_column($legs, 'purpose'))));
            $purposeSummary = implode('; ', $purposes);

            $tables[] = [
                'table_index' => count($tables),
                'sheet_name' => $sheetName,
                'header' => [
                    'driver_name' => $driverName,
                    'week_label' => $weekDate ?: Carbon::parse($itineraryDate)->format('F d, Y'),
                    'plate_number_raw' => $plateNumber,
                    'extracted_eqtp_code' => $detectedEqtp,
                    'extracted_plate' => $detectedPlate,
                ],
                'vehicle_match' => [
                    'is_matched' => $vehicleMatch['is_matched'],
                    'vehicle' => $vehicleMatch['vehicle'],
                    'vehicle_id' => $vehicleMatch['vehicle']?->id,
                    'equipment_code' => $detectedEqtp,
                    'plate_number' => $detectedPlate,
                    'detected_eqtp_code' => $detectedEqtp,
                    'detected_plate' => $detectedPlate,
                    'model' => $vehicleMatch['vehicle']?->model,
                    'avg_consumption' => $avgConsumption,
                    'confidence' => $vehicleMatch['confidence'],
                ],
                'avg_consumption' => $avgConsumption,
                'total_distance' => $tableTotalDistance,
                'fuel_liters_required' => $fuelLiters,
                'calculation_method' => $calcMethod,
                'itinerary_date' => $itineraryDate,
                'formatted_date' => Carbon::parse($itineraryDate)->format('F d, Y'),
                'start_odo' => $startOdo,
                'end_odo' => $endOdo,
                'destinations_summary' => $destSummary ?: 'Weekly Itinerary Route',
                'purposes_summary' => $purposeSummary ?: '—',
                'column_map' => $colMap,
                'legs' => $legs,
            ];
        }

        return $tables;
    }

    /**
     * Locate numeric values in a summary row.
     *
     * @param  array<int, mixed>  $row
     */
    protected function findNumericInRow(array $row, ?int $preferredColIdx = null): ?float
    {
        // 1. Check preferred column if given
        if ($preferredColIdx !== null && isset($row[$preferredColIdx])) {
            $val = $this->parseNumeric($row[$preferredColIdx]);
            if ($val !== null && $val > 0) {
                return $val;
            }
        }

        // 2. Check next cell immediately adjacent to the summary label
        foreach ($row as $idx => $cell) {
            $str = trim((string) $cell);
            if (preg_match('/(?:TOTAL\s*DISTANCE|AVE\.?\s*CON|AVERAGE\s*CONSUMPTION|LITER\s*FOR\s*PO|NO\.?\s*OF\s*LITERS)/i', $str)) {
                if (isset($row[$idx + 1])) {
                    $val = $this->parseNumeric($row[$idx + 1]);
                    if ($val !== null && $val > 0) {
                        return $val;
                    }
                }
            }
        }

        // 3. Scan row for any positive numeric cell
        foreach ($row as $cell) {
            $val = $this->parseNumeric($cell);
            if ($val !== null && $val > 0) {
                return $val;
            }
        }

        return null;
    }

    /**
     * Import parsed Weekly Itinerary Report into the database.
     * Creates an AdvancedItinerary record for each table in the spreadsheet.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function import(UploadedFile|string $file, int $userId, array $options = []): array
    {
        $parsed = $this->parse($file, $options['original_name'] ?? null);
        $tables = $parsed['tables'];
        $createdItineraries = [];

        DB::transaction(function () use ($tables, $parsed, $options, $userId, &$createdItineraries) {
            foreach ($tables as $i => $table) {
                $override = null;
                if (! empty($options['tables']) && is_array($options['tables'])) {
                    foreach ($options['tables'] as $tblOpt) {
                        if (isset($tblOpt['table_index']) && (int) $tblOpt['table_index'] === (int) ($table['table_index'] ?? $i)) {
                            $override = $tblOpt;
                            break;
                        }
                    }
                    if (! $override && isset($options['tables'][$i])) {
                        $override = $options['tables'][$i];
                    }
                }
                if (! $override && $i === 0) {
                    $override = $options;
                }
                $override = $override ?: [];

                $targetVehicleId = $override['vehicle_id'] ?? $table['vehicle_match']['vehicle_id'];
                $driverName = $override['driver_name'] ?? $table['header']['driver_name'] ?? '—';
                $eqtpCode = $override['custom_equipment_code'] ?? $table['vehicle_match']['equipment_code'];
                $plate = $override['custom_plate_number'] ?? $table['vehicle_match']['plate_number'];
                $customAvg = isset($override['custom_average_consumption']) && is_numeric($override['custom_average_consumption'])
                    ? (float) $override['custom_average_consumption']
                    : $table['avg_consumption'];

                if (! $targetVehicleId) {
                    if (! empty($eqtpCode) && $eqtpCode !== '—') {
                        $vehicle = Vehicle::firstOrCreate(
                            ['equipment_code' => $eqtpCode],
                            [
                                'plate_number' => $plate && $plate !== '—' ? $plate : null,
                                'operator_driver' => $driverName && $driverName !== '—' ? $driverName : null,
                                'average_fuel_consumption' => $customAvg !== null ? (float) $customAvg : null,
                                'created_by' => $userId,
                            ]
                        );
                        $targetVehicleId = $vehicle->id;
                    } elseif (! empty($plate) && $plate !== '—') {
                        $vehicle = Vehicle::firstOrCreate(
                            ['plate_number' => $plate],
                            [
                                'equipment_code' => $plate,
                                'operator_driver' => $driverName && $driverName !== '—' ? $driverName : null,
                                'average_fuel_consumption' => $customAvg !== null ? (float) $customAvg : null,
                                'created_by' => $userId,
                            ]
                        );
                        $targetVehicleId = $vehicle->id;
                    } else {
                        throw new RuntimeException('Cannot import table #'.($i + 1).' without a recognized vehicle or manual equipment code.');
                    }
                }

                $vehicle = Vehicle::find($targetVehicleId);

                // Update vehicle's average fuel consumption if provided in sheet or custom input
                if ($vehicle && $customAvg !== null && (float) $customAvg > 0) {
                    $existingAvg = $vehicle->average_fuel_consumption !== null ? round((float) $vehicle->average_fuel_consumption, 2) : null;
                    if ($existingAvg === null || $existingAvg !== round((float) $customAvg, 2)) {
                        $vehicle->update(['average_fuel_consumption' => (float) $customAvg]);
                    }
                }

                $calcMethod = $table['calculation_method'] ?: AdvancedItinerary::detectCalculationMethod($vehicle?->equipment_code ?? $eqtpCode);

                $totalDist = (float) $table['total_distance'];
                $fuelLiters = $table['fuel_liters_required'] !== null
                    ? (float) $table['fuel_liters_required']
                    : AdvancedItinerary::calculateFuelLiters($totalDist, $customAvg, $calcMethod);

                $weekLabel = $table['header']['week_label'] ?: $table['formatted_date'];
                $itinerary = AdvancedItinerary::create([
                    'vehicle_id' => $targetVehicleId,
                    'driver_name' => $driverName,
                    'itinerary_date' => $table['itinerary_date'],
                    'title' => "Weekly Itinerary: {$weekLabel} ({$table['itinerary_date']})",
                    'destination' => $table['destinations_summary'] ?: 'Weekly Itinerary Route',
                    'start_odo' => $table['start_odo'],
                    'end_odo' => $table['end_odo'],
                    'total_distance' => $totalDist > 0 ? $totalDist : null,
                    'fuel_liters_required' => $fuelLiters,
                    'calculation_method' => $calcMethod,
                    'notes' => 'Imported from Weekly Itinerary Report: '.$parsed['file_name'],
                    'status' => $options['status'] ?? AdvancedItinerary::STATUS_FINALIZED,
                    'po_checked' => false,
                    'created_by' => $userId,
                ]);

                // Create legs for this table
                foreach ($table['legs'] as $idx => $legData) {
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
     * Extract metadata fields (NAME, FOR THE WEEK, PLATE NUMBER).
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{
     *     driver_name_raw: string,
     *     week_date_raw: string,
     *     plate_number_raw: string,
     *     avg_consumption_in_sheet: ?float
     * }
     */
    protected function extractHeaderMetadata(array $rows, int $startRow = 0, ?int $endRow = null): array
    {
        $driverName = '';
        $weekDate = '';
        $plateNumber = '';
        $avgConsumption = null;

        $maxScanRows = $endRow !== null ? min($endRow, count($rows)) : min(15, count($rows));

        for ($r = max(0, $startRow); $r < $maxScanRows; $r++) {
            $row = $rows[$r] ?? [];
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

                // Check AVE. CON. OF LITE: in header area if present
                if (preg_match('/(?:AVE\.?\s*CON|AVERAGE\s*CONSUMPTION)/i', $val)) {
                    if (isset($row[$c + 1])) {
                        $num = $this->parseNumeric($row[$c + 1]);
                        if ($num !== null && $num > 0) {
                            $avgConsumption = $num;
                        }
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
