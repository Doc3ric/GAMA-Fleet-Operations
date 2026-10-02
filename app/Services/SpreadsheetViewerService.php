<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

    /**
     * Save/export an edited workbook as a standard .xlsx file.
     *
     * @param  string  $filePath  Original source workbook path
     * @param  string  $extension  Original file extension (xlsb or xlsx)
     * @param  array<int, array{id: string, name: string, target: string}>  $sheets
     * @param  array<string, array<int, array<int, mixed>>>  $allSheetsData
     * @param  string  $outputPath  Destination .xlsx file path
     */
    public function saveWorkbook(
        string $filePath,
        string $extension,
        array $sheets,
        array $allSheetsData,
        string $outputPath
    ): void {
        $spreadsheet = new Spreadsheet;
        $sheetIndex = 0;

        foreach ($sheets as $s) {
            $sheetId = $s['id'];
            $sheetName = $s['name'];

            // Clean title per Excel sheet name constraints (max 31 chars, no forbidden chars)
            $cleanTitle = mb_substr(str_replace(['\\', '/', '?', '*', ':', '[', ']'], '', $sheetName), 0, 31);
            if ($cleanTitle === '') {
                $cleanTitle = 'Sheet '.($sheetIndex + 1);
            }

            if ($sheetIndex === 0) {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle($cleanTitle);
            } else {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($cleanTitle);
            }

            // Retrieve sheet rows (either modified in-memory data, or read from original file)
            $rows = $allSheetsData[$sheetId] ?? $this->readWorksheet($filePath, $sheetId, $extension);

            if (! empty($rows)) {
                $sheet->fromArray($rows, null, 'A1');
            }

            $sheetIndex++;
        }

        // Ensure output directory exists
        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
    }
}
