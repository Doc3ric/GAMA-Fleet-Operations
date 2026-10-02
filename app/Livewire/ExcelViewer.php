<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SpreadsheetViewerService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class ExcelViewer extends Component
{
    use WithFileUploads;

    public $file = null;

    public ?string $fileId = null;

    public ?string $originalName = null;

    public ?string $extension = null;

    public ?int $fileSize = null;

    public array $sheets = [];

    public ?string $activeSheetId = null;

    public array $rows = [];

    public string $search = '';

    public int $perPage = 25;

    public int $page = 1;

    public bool $useFirstRowAsHeader = true;

    public bool $isEditMode = false;

    public array $allSheetsData = [];

    public bool $hasUnsavedChanges = false;

    public bool $hasEditedFile = false;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    /**
     * Handle file upload and parse worksheets.
     */
    public function updatedFile(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $this->validate([
            'file' => [
                'required',
                'file',
                'extensions:xlsb,xlsx',
                'max:20480', // 20 MB maximum
            ],
        ], [
            'file.required' => 'Please select a spreadsheet file to upload.',
            'file.extensions' => 'Only .xlsb and .xlsx workbooks are supported.',
            'file.max' => 'The spreadsheet size may not exceed 20 MB.',
        ]);

        try {
            $ext = strtolower($this->file->getClientOriginalExtension());
            $origName = $this->file->getClientOriginalName();
            $size = $this->file->getSize();

            $id = Str::uuid()->toString();
            $storedName = "{$id}.{$ext}";

            // Store securely on the private local disk
            $this->file->storeAs('excel-viewer', $storedName, 'local');

            // Save metadata for secure authenticated downloading
            $metadata = [
                'id' => $id,
                'file_name' => $storedName,
                'original_name' => $origName,
                'extension' => $ext,
                'size' => $size,
                'has_edited_file' => false,
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now()->toIso8601String(),
            ];
            Storage::disk('local')->put("excel-viewer/{$id}.json", json_encode($metadata));

            $this->fileId = $id;
            $this->originalName = $origName;
            $this->extension = $ext;
            $this->fileSize = $size;
            $this->allSheetsData = [];
            $this->isEditMode = false;
            $this->hasUnsavedChanges = false;
            $this->hasEditedFile = false;

            // Inspect worksheets
            $service = app(SpreadsheetViewerService::class);
            $fullPath = Storage::disk('local')->path("excel-viewer/{$storedName}");
            $this->sheets = $service->getWorksheets($fullPath, $ext);

            if (empty($this->sheets)) {
                $this->errorMessage = 'No worksheets found in the uploaded workbook.';
                $this->rows = [];
                $this->activeSheetId = null;

                return;
            }

            // Select the first sheet by default
            $this->selectSheet($this->sheets[0]['id']);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to process spreadsheet: '.$e->getMessage();
            $this->rows = [];
            $this->sheets = [];
            $this->activeSheetId = null;
        } finally {
            // Reset the temporary file upload input
            $this->file = null;
        }
    }

    /**
     * Switch active worksheet and read its tabular data.
     */
    public function selectSheet(string $sheetId): void
    {
        $this->errorMessage = null;

        // Persist current sheet data in memory cache before switching
        if ($this->activeSheetId && ! empty($this->rows)) {
            $this->allSheetsData[$this->activeSheetId] = $this->rows;
        }

        $this->activeSheetId = $sheetId;
        $this->page = 1;
        $this->search = '';

        if (! $this->fileId || ! $this->extension) {
            return;
        }

        // If sheet data was already cached or modified in-memory, load it
        if (isset($this->allSheetsData[$sheetId])) {
            $this->rows = $this->allSheetsData[$sheetId];

            return;
        }

        try {
            $storedName = "{$this->fileId}.{$this->extension}";
            $fullPath = Storage::disk('local')->path("excel-viewer/{$storedName}");

            $service = app(SpreadsheetViewerService::class);
            $this->rows = $service->readWorksheet($fullPath, $sheetId, $this->extension);
            $this->allSheetsData[$sheetId] = $this->rows;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to read worksheet data: '.$e->getMessage();
            $this->rows = [];
        }
    }

    /**
     * Toggle Edit Mode on or off.
     */
    public function toggleEditMode(): void
    {
        $this->isEditMode = ! $this->isEditMode;
        $this->successMessage = null;
    }

    /**
     * Update a cell value.
     */
    public function updateCell(int $rowIndex, int $colIndex, mixed $value): void
    {
        $valStr = (string) $value;
        $this->rows[$rowIndex][$colIndex] = $valStr;

        if ($this->activeSheetId) {
            $this->allSheetsData[$this->activeSheetId][$rowIndex][$colIndex] = $valStr;
        }

        $this->hasUnsavedChanges = true;
        $this->successMessage = null;
    }

    /**
     * Add a new blank row to the active sheet.
     */
    public function addRow(): void
    {
        $colCount = ! empty($this->rows) ? count($this->rows[0]) : 5;
        $newRow = array_fill(0, $colCount, '');

        $this->rows[] = $newRow;

        if ($this->activeSheetId) {
            $this->allSheetsData[$this->activeSheetId] = $this->rows;
        }

        $this->hasUnsavedChanges = true;
        $this->successMessage = null;

        // Reset search so newly added row is visible
        $this->search = '';

        // Navigate to last page so new row is immediately in view
        $dataRowsCount = $this->useFirstRowAsHeader ? max(0, count($this->rows) - 1) : count($this->rows);
        $this->page = max(1, (int) ceil($dataRowsCount / $this->perPage));
    }

    /**
     * Delete a row by its index in $this->rows.
     */
    public function deleteRow(int $rowIndex): void
    {
        if (isset($this->rows[$rowIndex])) {
            array_splice($this->rows, $rowIndex, 1);
            $this->rows = array_values($this->rows);

            if ($this->activeSheetId) {
                $this->allSheetsData[$this->activeSheetId] = $this->rows;
            }

            $this->hasUnsavedChanges = true;
            $this->successMessage = null;
        }
    }

    /**
     * Save all edited sheets into a standard Excel (.xlsx) file.
     */
    public function saveChanges(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if (! $this->fileId || ! $this->extension) {
            $this->errorMessage = 'No active workbook to save.';

            return;
        }

        try {
            // Sync current active sheet rows
            if ($this->activeSheetId) {
                $this->allSheetsData[$this->activeSheetId] = $this->rows;
            }

            $storedName = "{$this->fileId}.{$this->extension}";
            $fullPath = Storage::disk('local')->path("excel-viewer/{$storedName}");
            $editedStoredName = "{$this->fileId}_edited.xlsx";
            $editedFullPath = Storage::disk('local')->path("excel-viewer/{$editedStoredName}");

            $service = app(SpreadsheetViewerService::class);
            $service->saveWorkbook(
                $fullPath,
                $this->extension,
                $this->sheets,
                $this->allSheetsData,
                $editedFullPath
            );

            // Update metadata JSON file
            $metaPath = "excel-viewer/{$this->fileId}.json";
            $meta = [];
            if (Storage::disk('local')->exists($metaPath)) {
                $meta = json_decode(Storage::disk('local')->get($metaPath) ?: '{}', true);
            }
            $baseOriginal = pathinfo($this->originalName ?? 'workbook', PATHINFO_FILENAME);
            $meta['has_edited_file'] = true;
            $meta['edited_file_name'] = $editedStoredName;
            $meta['edited_download_name'] = "{$baseOriginal} (Edited).xlsx";
            $meta['edited_at'] = now()->toIso8601String();
            Storage::disk('local')->put($metaPath, json_encode($meta));

            $this->hasUnsavedChanges = false;
            $this->hasEditedFile = true;
            $this->successMessage = 'Workbook changes saved successfully! You can download the updated Excel (.xlsx) file.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to save workbook changes: '.$e->getMessage();
        }
    }

    /**
     * Reset pagination when search query updates.
     */
    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    /**
     * Reset pagination when page size updates.
     */
    public function updatedPerPage(): void
    {
        $this->page = 1;
    }

    /**
     * Toggle header mode.
     */
    public function toggleFirstRowAsHeader(): void
    {
        $this->useFirstRowAsHeader = ! $this->useFirstRowAsHeader;
        $this->page = 1;
    }

    /**
     * Set page number.
     */
    public function gotoPage(int $pageNumber): void
    {
        $this->page = max(1, $pageNumber);
    }

    /**
     * Go to next page.
     */
    public function nextPage(int $totalPages): void
    {
        if ($this->page < $totalPages) {
            $this->page++;
        }
    }

    /**
     * Go to previous page.
     */
    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    /**
     * Clear the loaded file and reset viewer.
     */
    public function clearFile(): void
    {
        $this->file = null;
        $this->fileId = null;
        $this->originalName = null;
        $this->extension = null;
        $this->fileSize = null;
        $this->sheets = [];
        $this->activeSheetId = null;
        $this->rows = [];
        $this->allSheetsData = [];
        $this->isEditMode = false;
        $this->hasUnsavedChanges = false;
        $this->hasEditedFile = false;
        $this->search = '';
        $this->page = 1;
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    /**
     * Convert zero-based column index into Excel column letter (0 -> A, 1 -> B, 26 -> AA).
     */
    public function getColumnLetter(int $colIndex): string
    {
        $letter = '';
        $colIndex += 1;

        while ($colIndex > 0) {
            $mod = ($colIndex - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $colIndex = intdiv($colIndex - $mod, 26);
        }

        return $letter;
    }

    public function render(): View
    {
        $headers = [];
        $dataRows = [];

        if (! empty($this->rows)) {
            $columnCount = count($this->rows[0]);

            if ($this->useFirstRowAsHeader && count($this->rows) > 0) {
                // First row is used as header
                foreach ($this->rows[0] as $colIdx => $colVal) {
                    $valStr = trim((string) $colVal);
                    $headers[] = $valStr !== '' ? $valStr : $this->getColumnLetter($colIdx);
                }
                for ($r = 1; $r < count($this->rows); $r++) {
                    $dataRows[] = [
                        '_rowIndex' => $r,
                        'cells' => $this->rows[$r],
                    ];
                }
            } else {
                // Column letters A, B, C...
                for ($c = 0; $c < $columnCount; $c++) {
                    $headers[] = $this->getColumnLetter($c);
                }
                for ($r = 0; $r < count($this->rows); $r++) {
                    $dataRows[] = [
                        '_rowIndex' => $r,
                        'cells' => $this->rows[$r],
                    ];
                }
            }
        }

        // Apply search filter across cells in each row
        $searchTrimmed = trim($this->search);
        if ($searchTrimmed !== '') {
            $dataRows = array_values(array_filter($dataRows, function ($rowItem) use ($searchTrimmed) {
                foreach ($rowItem['cells'] as $cell) {
                    if (stripos((string) $cell, $searchTrimmed) !== false) {
                        return true;
                    }
                }

                return false;
            }));
        }

        $totalRows = count($dataRows);
        $totalPages = max(1, (int) ceil($totalRows / $this->perPage));

        // Constrain page bounds
        if ($this->page > $totalPages) {
            $this->page = $totalPages;
        }

        $offset = ($this->page - 1) * $this->perPage;
        $pagedRows = array_slice($dataRows, $offset, $this->perPage);

        return view('livewire.excel-viewer', [
            'headers' => $headers,
            'pagedRows' => $pagedRows,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'startRow' => $totalRows > 0 ? $offset + 1 : 0,
            'endRow' => min($offset + $this->perPage, $totalRows),
        ]);
    }
}
