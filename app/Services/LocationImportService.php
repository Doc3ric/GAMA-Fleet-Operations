<?php

namespace App\Services;

use App\Models\Location;
use App\Models\LocationAlias;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LocationImportService
{
    /**
     * Column key mappings to recognize header variations.
     *
     * @var array<string, list<string>>
     */
    protected array $headerAliases = [
        'code' => ['code', 'locationcode', 'loccode', 'shortcode', 'primarycode'],
        'official_name' => ['officialname', 'name', 'locationname', 'officiallocation', 'officiallocationname'],
        'type' => ['type', 'locationtype', 'category'],
        'aliases' => ['aliases', 'alias', 'othernames', 'drivernames', 'nicknames'],
        'latitude' => ['latitude', 'lat'],
        'longitude' => ['longitude', 'long', 'lng'],
        'address' => ['address', 'fulladdress', 'streetaddress', 'street'],
        'barangay' => ['barangay', 'brgy'],
        'municipality' => ['municipality', 'city', 'municipalitycity', 'town'],
        'province' => ['province', 'state', 'region'],
        'status' => ['status', 'state'],
        'notes' => ['notes', 'description', 'remarks'],
    ];

    public function __construct(
        protected LocationRecognitionService $recognitionService
    ) {}

    /**
     * Import records from uploaded Excel or CSV file.
     *
     * @return array{
     *     success: bool,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     errors: list<string>,
     *     message: string
     * }
     */
    public function import(UploadedFile|string $file, ?int $userId = null): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, false, false);

        if (empty($rows)) {
            return [
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['The uploaded file contains no data.'],
                'message' => 'The uploaded file contains no data.',
            ];
        }

        $headerInfo = $this->findHeaderRow($rows);
        if (! $headerInfo) {
            return [
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['Could not find valid column headers. Required headers include Code, Official Name, Latitude, Longitude, and Address.'],
                'message' => 'Could not find valid column headers.',
            ];
        }

        [$headerRowIndex, $columnMap] = $headerInfo;

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $totalRows = count($rows);
        for ($i = $headerRowIndex + 1; $i < $totalRows; $i++) {
            $row = $rows[$i];

            // Skip empty rows
            if (empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                continue;
            }

            $code = isset($columnMap['code']) ? trim((string) ($row[$columnMap['code']] ?? '')) : '';
            $officialName = isset($columnMap['official_name']) ? trim((string) ($row[$columnMap['official_name']] ?? '')) : '';

            if ($code === '' || $officialName === '') {
                $skipped++;
                $errors[] = 'Row '.($i + 1).': Skipped because Location Code or Official Name is missing.';

                continue;
            }

            $latVal = isset($columnMap['latitude']) ? trim((string) ($row[$columnMap['latitude']] ?? '')) : '';
            $lngVal = isset($columnMap['longitude']) ? trim((string) ($row[$columnMap['longitude']] ?? '')) : '';

            if (! is_numeric($latVal) || ! is_numeric($lngVal)) {
                $skipped++;
                $errors[] = 'Row '.($i + 1)." ({$code}): Invalid coordinates ($latVal, $lngVal).";

                continue;
            }

            $lat = (float) $latVal;
            $lng = (float) $lngVal;

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                $skipped++;
                $errors[] = 'Row '.($i + 1)." ({$code}): Coordinates out of geographic range.";

                continue;
            }

            $type = isset($columnMap['type']) ? trim((string) ($row[$columnMap['type']] ?? '')) : Location::TYPE_OTHER;
            if (! in_array($type, Location::TYPES)) {
                $type = Location::TYPE_OTHER;
            }

            $status = isset($columnMap['status']) ? trim((string) ($row[$columnMap['status']] ?? '')) : Location::STATUS_ACTIVE;
            if (! in_array($status, Location::STATUSES)) {
                $status = Location::STATUS_ACTIVE;
            }

            $address = isset($columnMap['address']) ? trim((string) ($row[$columnMap['address']] ?? '')) : '';
            if ($address === '') {
                $address = 'Address not specified';
            }

            $barangay = isset($columnMap['barangay']) ? trim((string) ($row[$columnMap['barangay']] ?? '')) : null;
            $municipality = isset($columnMap['municipality']) ? trim((string) ($row[$columnMap['municipality']] ?? '')) : null;
            $province = isset($columnMap['province']) ? trim((string) ($row[$columnMap['province']] ?? '')) : null;
            $notes = isset($columnMap['notes']) ? trim((string) ($row[$columnMap['notes']] ?? '')) : null;

            // Check if active location with this code already exists
            $location = Location::where('code', $code)->first();

            $locationData = [
                'code' => $code,
                'official_name' => $officialName,
                'type' => $type,
                'latitude' => $lat,
                'longitude' => $lng,
                'address' => $address,
                'barangay' => $barangay ?: null,
                'municipality' => $municipality ?: null,
                'province' => $province ?: null,
                'status' => $status,
                'notes' => $notes ?: null,
            ];

            if ($location) {
                $locationData['updated_by'] = $userId;
                $location->update($locationData);
                $updated++;
            } else {
                $locationData['created_by'] = $userId;
                $locationData['updated_by'] = $userId;
                $location = Location::create($locationData);
                $created++;
            }

            // Parse and sync multiple aliases (semicolon or comma delimited)
            $aliasesRaw = isset($columnMap['aliases']) ? (string) ($row[$columnMap['aliases']] ?? '') : '';
            if ($aliasesRaw !== '') {
                // Split by semicolon or comma
                $aliasList = preg_split('/[;,]+/', $aliasesRaw);
                if ($aliasList) {
                    foreach ($aliasList as $aliasItem) {
                        $cleanAlias = trim($aliasItem);
                        if ($cleanAlias === '') {
                            continue;
                        }

                        // Check ambiguous active aliases (Adjustment 3)
                        $conflict = $this->recognitionService->findConflictingLocationForAlias($cleanAlias, $location->id);
                        if ($conflict) {
                            $errors[] = 'Row '.($i + 1)." ({$code}): Alias '{$cleanAlias}' already belongs to active location '{$conflict->official_name}' (#{$conflict->code}). Skipped alias.";

                            continue;
                        }

                        LocationAlias::firstOrCreate([
                            'location_id' => $location->id,
                            'alias' => $cleanAlias,
                        ]);
                    }
                }
            }
        }

        $this->recognitionService->clearCache();

        return [
            'success' => true,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => "Import completed: {$created} created, {$updated} updated, {$skipped} skipped.",
        ];
    }

    /**
     * Locate the header row and map column names to indexes.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: int, 1: array<string, int>}|null
     */
    protected function findHeaderRow(array $rows): ?array
    {
        $maxScan = min(10, count($rows));

        for ($i = 0; $i < $maxScan; $i++) {
            $row = $rows[$i];
            $columnMap = [];

            foreach ($row as $colIndex => $cellValue) {
                if ($cellValue === null) {
                    continue;
                }

                $cleanHeader = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $cellValue) ?? '');
                if ($cleanHeader === '') {
                    continue;
                }

                foreach ($this->headerAliases as $key => $aliases) {
                    if (isset($columnMap[$key])) {
                        continue;
                    }

                    foreach ($aliases as $alias) {
                        $cleanAlias = strtolower(preg_replace('/[^a-z0-9]/', '', $alias) ?? '');
                        if ($cleanHeader === $cleanAlias) {
                            $columnMap[$key] = $colIndex;
                            break;
                        }
                    }
                }
            }

            // A valid header row must at least contain code and official_name
            if (isset($columnMap['code']) && isset($columnMap['official_name'])) {
                return [$i, $columnMap];
            }
        }

        return null;
    }
}
