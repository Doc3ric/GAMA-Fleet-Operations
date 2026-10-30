<?php

namespace App\Services;

use App\Models\Device;
use App\Models\GpsReportImport;
use App\Models\GpsReportRecord;
use App\Models\MileageAuditLog;
use App\Models\MileageReconciliation;
use App\Models\Vehicle;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class TrackSolidReportImportService
{
    /**
     * Column key mappings to recognize diverse TrackSolid Pro header variations.
     */
    protected array $headerAliases = [
        'device_name' => ['devicename', 'device', 'vehicle', 'vehiclename', 'unit', 'unitname', 'equipment'],
        'imei' => ['imei', 'imeino', 'imeinumber', 'deviceid'],
        'start_time' => ['starttime', 'start', 'from', 'begintime', 'departuretime', 'startdate'],
        'end_time' => ['endtime', 'end', 'to', 'arrivaltime', 'enddate'],
        'distance' => ['distance', 'rundistance', 'run_distance', 'totaldistance', 'mileage', 'km', 'tripmileage'],
        'start_odometer' => ['startodometer', 'start_odometer', 'startodo', 'beginmileage', 'startmileage'],
        'end_odometer' => ['endodometer', 'end_odometer', 'endodo', 'endmileage', 'finalmileage'],
        'trip_reference' => ['tripreference', 'trip_number', 'tripno', 'reference', 'refno'],
    ];

    /**
     * Parse an uploaded TrackSolid report file for preview before committing.
     *
     * @return array{
     *     filename: string,
     *     total_rows: int,
     *     matched_count: int,
     *     unmatched_count: int,
     *     ambiguous_count: int,
     *     duplicate_count: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function parse(UploadedFile|string $file, ?string $originalName = null): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $fileName = $originalName ?? ($file instanceof UploadedFile ? $file->getClientOriginalName() : basename($filePath));
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (! in_array($extension, ['xlsx', 'csv', 'xls'], true)) {
            throw new RuntimeException("Unsupported file format: .{$extension}. Allowed formats are .xlsx, .csv, and .xls.");
        }

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $sheetRows = $worksheet->toArray(null, true, false, false);

        if (empty($sheetRows)) {
            throw new RuntimeException('The uploaded GPS report file contains no rows.');
        }

        $headerInfo = $this->findHeaderRow($sheetRows);
        if (! $headerInfo) {
            throw new RuntimeException('Could not detect valid TrackSolid Pro headers. Expected columns such as Device Name, IMEI, Start Time, End Time, or Distance/Mileage.');
        }

        [$headerIndex, $colMap] = $headerInfo;

        $parsedRows = [];
        $matchedCount = 0;
        $unmatchedCount = 0;
        $ambiguousCount = 0;
        $duplicateCount = 0;
        $seenKeys = [];

        $totalRows = count($sheetRows);
        for ($i = $headerIndex + 1; $i < $totalRows; $i++) {
            $row = $sheetRows[$i];

            $deviceName = trim((string) $this->getCellValue($row, $colMap, 'device_name'));
            $imei = $this->cleanImei($this->getCellValue($row, $colMap, 'imei'));
            $rawStart = $this->getCellValue($row, $colMap, 'start_time');
            $rawEnd = $this->getCellValue($row, $colMap, 'end_time');
            $rawDistance = $this->getCellValue($row, $colMap, 'distance');
            $rawStartOdo = $this->getCellValue($row, $colMap, 'start_odometer');
            $rawEndOdo = $this->getCellValue($row, $colMap, 'end_odometer');
            $tripRef = trim((string) $this->getCellValue($row, $colMap, 'trip_reference'));

            if (empty($deviceName) && empty($imei) && empty($rawDistance)) {
                continue;
            }

            $startTime = $this->parseDateTime($rawStart);
            $endTime = $this->parseDateTime($rawEnd);
            $distance = $this->parseNumeric($rawDistance);
            $startOdo = $this->parseNumeric($rawStartOdo);
            $endOdo = $this->parseNumeric($rawEndOdo);

            // Deduplication key
            $dupKey = ($imei ?: $deviceName).'|'.($startTime ? $startTime->toIso8601String() : '').'|'.$distance;
            $isDuplicate = isset($seenKeys[$dupKey]);
            if ($isDuplicate) {
                $duplicateCount++;
                $matchStatus = GpsReportRecord::STATUS_DUPLICATE;
                $vehicle = null;
            } else {
                $seenKeys[$dupKey] = true;
                $matchingVehicles = $this->findMatchingVehicles($deviceName, $imei);

                if ($matchingVehicles->count() === 1) {
                    $matchedCount++;
                    $matchStatus = GpsReportRecord::STATUS_MATCHED;
                    $vehicle = $matchingVehicles->first();
                } elseif ($matchingVehicles->count() > 1) {
                    $ambiguousCount++;
                    $matchStatus = GpsReportRecord::STATUS_AMBIGUOUS;
                    $vehicle = null;
                } else {
                    $unmatchedCount++;
                    $matchStatus = GpsReportRecord::STATUS_UNMATCHED;
                    $vehicle = null;
                }
            }

            $parsedRows[] = [
                'row_index' => $i + 1,
                'device_name' => $deviceName,
                'imei' => $imei,
                'start_time' => $startTime?->toDateTimeString(),
                'end_time' => $endTime?->toDateTimeString(),
                'distance_km' => $distance,
                'start_odometer' => $startOdo,
                'end_odometer' => $endOdo,
                'trip_reference' => $tripRef ?: null,
                'match_status' => $matchStatus,
                'vehicle_id' => $vehicle?->id,
                'vehicle_code' => $vehicle?->equipment_code,
                'vehicle_plate' => $vehicle?->plate_number,
                'raw' => [
                    'device_name' => $deviceName,
                    'imei' => $imei,
                    'start_time' => $rawStart,
                    'end_time' => $rawEnd,
                    'distance' => $rawDistance,
                ],
            ];
        }

        return [
            'filename' => $fileName,
            'total_rows' => count($parsedRows),
            'matched_count' => $matchedCount,
            'unmatched_count' => $unmatchedCount,
            'ambiguous_count' => $ambiguousCount,
            'duplicate_count' => $duplicateCount,
            'rows' => $parsedRows,
        ];
    }

    /**
     * Commit the parsed report rows into the database and link to reconciliations.
     *
     * @param  array<string, mixed>  $parsedData
     */
    public function commit(
        UploadedFile|string $file,
        array $parsedData,
        ?int $userId = null,
        bool $linkToReconciliations = true
    ): GpsReportImport {
        return DB::transaction(function () use ($file, $parsedData, $userId, $linkToReconciliations) {
            $userId = $userId ?? auth()->id();
            $disk = config('fleet_reconciliation.evidence_storage_disk', 'public');

            // Store uploaded evidence file permanently
            $filePath = $file instanceof UploadedFile
                ? $file->store('gps-report-imports', $disk)
                : 'gps-report-imports/'.basename($file);

            $fileSize = $file instanceof UploadedFile ? $file->getSize() : (file_exists($file) ? filesize($file) : 0);
            $mimeType = $file instanceof UploadedFile ? $file->getMimeType() : 'application/octet-stream';

            $import = GpsReportImport::create([
                'filename' => $parsedData['filename'],
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'total_rows' => $parsedData['total_rows'],
                'matched_rows' => $parsedData['matched_count'],
                'unmatched_rows' => $parsedData['unmatched_count'],
                'duplicate_rows' => $parsedData['duplicate_count'],
                'status' => GpsReportImport::STATUS_COMMITTED,
                'import_summary' => [
                    'matched' => $parsedData['matched_count'],
                    'unmatched' => $parsedData['unmatched_count'],
                    'ambiguous' => $parsedData['ambiguous_count'],
                    'duplicate' => $parsedData['duplicate_count'],
                    'committed_at' => now()->toIso8601String(),
                ],
                'created_by' => $userId,
            ]);

            $reconciliationService = app(MileageReconciliationService::class);

            foreach ($parsedData['rows'] as $row) {
                $startTime = ! empty($row['start_time']) ? Carbon::parse($row['start_time']) : null;
                $endTime = ! empty($row['end_time']) ? Carbon::parse($row['end_time']) : null;
                $vehicleId = $row['vehicle_id'] ?? null;
                $matchStatus = $row['match_status'];
                $distanceKm = $row['distance_km'];
                $linkedReconciliationId = null;

                // Attempt to link to reconciliation if unambiguous
                if ($linkToReconciliations && $vehicleId && $matchStatus === GpsReportRecord::STATUS_MATCHED && $startTime) {
                    $tripDate = $startTime->toDateString();

                    $matchingReconciliations = MileageReconciliation::where('vehicle_id', $vehicleId)
                        ->whereDate('trip_date', $tripDate)
                        ->get();

                    if ($matchingReconciliations->count() === 1) {
                        $targetRec = $matchingReconciliations->first();

                        // Never silently overwrite approved reconciliations!
                        if (! $targetRec->isApproved()) {
                            $targetRec->gps_reported_km = $distanceKm;
                            $targetRec->computeCalculations();
                            $targetRec->save();

                            $reconciliationService->evaluateExceptions($targetRec);
                            $linkedReconciliationId = $targetRec->id;

                            MileageAuditLog::record(
                                action: MileageAuditLog::ACTION_GPS_LINKED,
                                reconciliationId: $targetRec->id,
                                dispatchId: $targetRec->dispatch_id,
                                userId: $userId,
                                remarks: "Linked TrackSolid Pro GPS distance ({$distanceKm} KM) from report {$parsedData['filename']}."
                            );
                        }
                    } elseif ($matchingReconciliations->count() > 1) {
                        // Ambiguous reconciliation matches on same vehicle and date
                        $matchStatus = GpsReportRecord::STATUS_AMBIGUOUS;
                    }
                }

                GpsReportRecord::create([
                    'gps_report_import_id' => $import->id,
                    'device_name' => $row['device_name'],
                    'imei' => $row['imei'],
                    'vehicle_id' => $vehicleId,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'run_distance_km' => $distanceKm,
                    'start_odometer' => $row['start_odometer'],
                    'end_odometer' => $row['end_odometer'],
                    'raw_data' => $row['raw'],
                    'match_status' => $matchStatus,
                    'mileage_reconciliation_id' => $linkedReconciliationId,
                ]);
            }

            return $import;
        });
    }

    /**
     * Find vehicles matching an IMEI or device name.
     *
     * @return Collection<int, Vehicle>
     */
    public function findMatchingVehicles(?string $deviceName, ?string $imei)
    {
        if ($imei) {
            $device = Device::where('imei', $imei)->with('vehicle')->first();
            if ($device && $device->vehicle) {
                return Vehicle::where('id', $device->vehicle_id)->get();
            }
        }

        if (! $deviceName) {
            return Vehicle::whereRaw('1=0')->get();
        }

        $clean = trim($deviceName);

        // Exact equipment_code or plate_number
        $vehicles = Vehicle::where(function ($q) use ($clean) {
            $q->where('equipment_code', $clean)
                ->orWhere('plate_number', $clean);
        })->get();

        if ($vehicles->isNotEmpty()) {
            return $vehicles;
        }

        // Check if device name matches a known Device record's vehicle
        $device = Device::where('device_name', $clean)->whereNotNull('vehicle_id')->with('vehicle')->first();
        if ($device && $device->vehicle) {
            return Vehicle::where('id', $device->vehicle_id)->get();
        }

        // Fuzzy Tracksolid pattern: e.g. "BT-01", "BT 01", "BT01 - ABC-1234"
        $normalized = $this->normalizeString($clean);
        $all = Vehicle::all();
        $matched = $all->filter(function (Vehicle $v) use ($normalized) {
            return $this->normalizeString((string) $v->equipment_code) === $normalized
                || $this->normalizeString((string) $v->plate_number) === $normalized;
        });

        return $matched;
    }

    protected function findHeaderRow(array $rows): ?array
    {
        $maxScan = min(15, count($rows));

        for ($i = 0; $i < $maxScan; $i++) {
            $row = $rows[$i];
            if (! is_array($row)) {
                continue;
            }

            $columnMap = [];
            foreach ($row as $colIdx => $headerVal) {
                if ($headerVal === null) {
                    continue;
                }
                $normalizedHeader = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $headerVal));

                foreach ($this->headerAliases as $key => $aliases) {
                    if (in_array($normalizedHeader, $aliases, true)) {
                        $columnMap[$key] = $colIdx;
                        break;
                    }
                }
            }

            // Valid header if has at least device_name or imei, and at least distance or start_time
            if ((isset($columnMap['device_name']) || isset($columnMap['imei'])) &&
                (isset($columnMap['distance']) || isset($columnMap['start_time']) || isset($columnMap['start_odometer']))) {
                return [$i, $columnMap];
            }
        }

        return null;
    }

    protected function getCellValue(array $row, array $colMap, string $key): mixed
    {
        if (! isset($colMap[$key])) {
            return null;
        }

        return $row[$colMap[$key]] ?? null;
    }

    protected function cleanImei(mixed $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $imei = preg_replace('/[^0-9]/', '', (string) $val);

        return $imei !== '' ? $imei : null;
    }

    protected function parseDateTime(mixed $val): ?Carbon
    {
        if (empty($val)) {
            return null;
        }

        try {
            return Carbon::parse($val);
        } catch (Exception) {
            return null;
        }
    }

    protected function parseNumeric(mixed $val): ?float
    {
        if ($val === null || trim((string) $val) === '') {
            return null;
        }

        $clean = preg_replace('/[^\d\.\-]/', '', (string) $val);

        return is_numeric($clean) ? (float) $clean : null;
    }

    protected function normalizeString(string $val): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $val));
    }
}
