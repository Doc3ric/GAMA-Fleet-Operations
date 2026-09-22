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

    public ?string $errorMessage = null;

    /**
     * Handle file upload and parse worksheets.
     */
    public function updatedFile(): void
    {
        $this->errorMessage = null;

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
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now()->toIso8601String(),
            ];
            Storage::disk('local')->put("excel-viewer/{$id}.json", json_encode($metadata));

            $this->fileId = $id;
            $this->originalName = $origName;
            $this->extension = $ext;
            $this->fileSize = $size;

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
        $this->activeSheetId = $sheetId;
        $this->page = 1;
        $this->search = '';

        if (! $this->fileId || ! $this->extension) {
            return;
        }

        try {
            $storedName = "{$this->fileId}.{$this->extension}";
            $fullPath = Storage::disk('local')->path("excel-viewer/{$storedName}");

            $service = app(SpreadsheetViewerService::class);
            $this->rows = $service->readWorksheet($fullPath, $sheetId, $this->extension);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to read worksheet data: '.$e->getMessage();
            $this->rows = [];
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
        $this->search = '';
        $this->page = 1;
        $this->errorMessage = null;
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
                $dataRows = array_slice($this->rows, 1);
            } else {
                // Column letters A, B, C...
                for ($c = 0; $c < $columnCount; $c++) {
                    $headers[] = $this->getColumnLetter($c);
                }
                $dataRows = $this->rows;
            }
        }

        // Apply search filter across all cells in each row
        $searchTrimmed = trim($this->search);
        if ($searchTrimmed !== '') {
            $dataRows = array_values(array_filter($dataRows, function ($row) use ($searchTrimmed) {
                foreach ($row as $cell) {
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
