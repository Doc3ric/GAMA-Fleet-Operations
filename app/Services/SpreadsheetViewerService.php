<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SpreadsheetViewerService
{
    public function __construct(
        protected XlsbReaderService $xlsbReader
    ) {}

    /**
     * Get available worksheets from a spreadsheet (.xlsb or .xlsx).
     *
     * @return array<int, array{id: string, name: string, target: string}>
     */
    public function getWorksheets(string $filePath, string $extension): array
    {
        $ext = strtolower(ltrim($extension, '.'));

        if ($ext === 'xlsb') {
            $sheets = $this->xlsbReader->getWorksheets($filePath);

            return array_map(fn ($sheet) => [
                'id' => $sheet['target'],
                'name' => $sheet['name'],
                'target' => $sheet['target'],
            ], $sheets);
        }

        if ($ext === 'xlsx') {
            if (! file_exists($filePath) || ! is_readable($filePath)) {
                throw new RuntimeException("Spreadsheet file not found or not readable: {$filePath}");
            }

            try {
                $reader = IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(true);
                $worksheetNames = $reader->listWorksheetNames($filePath);

                return array_map(fn ($name) => [
                    'id' => $name,
                    'name' => $name,
                    'target' => $name,
                ], $worksheetNames);
            } catch (\Throwable $e) {
                throw new RuntimeException("Failed to read XLSX worksheet metadata: {$e->getMessage()}", 0, $e);
            }
        }

        throw new RuntimeException("Unsupported spreadsheet format: .{$extension}");
    }

    /**
     * Read tabular rows from a selected worksheet.
     *
     * @return array<int, array<int, mixed>> 2D array of rows
     */
    public function readWorksheet(string $filePath, string $sheetIdentifier, string $extension): array
    {
        $ext = strtolower(ltrim($extension, '.'));

        if ($ext === 'xlsb') {
            return $this->xlsbReader->readWorksheet($filePath, $sheetIdentifier);
        }

        if ($ext === 'xlsx') {
            try {
                $reader = IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(true);
                $reader->setLoadSheetsOnly($sheetIdentifier);
                $spreadsheet = $reader->load($filePath);
                $sheet = $spreadsheet->getSheetByName($sheetIdentifier);

                if ($sheet === null) {
                    throw new RuntimeException("Worksheet '{$sheetIdentifier}' not found in workbook.");
                }

                $rawRows = $sheet->toArray(null, true, true, false);

                if (empty($rawRows)) {
                    return [];
                }

                // Find max column count
                $maxCol = 0;
                foreach ($rawRows as $row) {
                    if (count($row) > $maxCol) {
                        $maxCol = count($row);
                    }
                }

                // Normalize cells: null -> ""
                $normalized = [];
                foreach ($rawRows as $row) {
                    $normalizedRow = [];
                    for ($c = 0; $c < $maxCol; $c++) {
                        $val = $row[$c] ?? '';
                        $normalizedRow[$c] = $val === null ? '' : $val;
                    }
                    $normalized[] = $normalizedRow;
                }

                return $normalized;
            } catch (\Throwable $e) {
                throw new RuntimeException("Failed to read XLSX worksheet data: {$e->getMessage()}", 0, $e);
            }
        }

        throw new RuntimeException("Unsupported spreadsheet format: .{$extension}");
    }
}
