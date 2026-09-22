<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelViewerController extends Controller
{
    /**
     * Display the Excel Viewer module.
     */
    public function index(): View
    {
        return view('excel-viewer.index');
    }

    /**
     * Download the original uploaded spreadsheet workbook.
     */
    public function download(Request $request, string $fileId): StreamedResponse
    {
        // Strictly sanitize fileId to prevent directory traversal
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
            abort(404, 'Invalid file identifier.');
        }

        $metaPath = "excel-viewer/{$fileId}.json";
        if (! Storage::disk('local')->exists($metaPath)) {
            abort(404, 'Spreadsheet file record not found.');
        }

        $metadata = json_decode(Storage::disk('local')->get($metaPath) ?: '{}', true);

        // Security check: only the user who uploaded the file (or admin) can download it
        if (! empty($metadata['uploaded_by']) && (int) $metadata['uploaded_by'] !== (int) auth()->id()) {
            abort(403, 'Unauthorized access to this spreadsheet.');
        }

        $storedFile = "excel-viewer/{$metadata['file_name']}";
        if (! Storage::disk('local')->exists($storedFile)) {
            abort(404, 'Physical spreadsheet file not found.');
        }

        $downloadName = $metadata['original_name'] ?? ('workbook.'.$metadata['extension']);

        return Storage::disk('local')->download($storedFile, $downloadName);
    }
}
