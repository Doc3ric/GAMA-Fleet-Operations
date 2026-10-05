<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\WeeklyItineraryTemplateExport;
use App\Models\AdvancedItinerary;
use App\Services\WeeklyItineraryImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FuelPoImportController extends Controller
{
    public function __construct(
        protected WeeklyItineraryImportService $importService
    ) {}

    /**
     * Download the standard blank Weekly Itinerary Report Excel template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        Gate::authorize('viewAny', AdvancedItinerary::class);

        return Excel::download(
            new WeeklyItineraryTemplateExport,
            'Weekly-Itinerary-Report-Template.xlsx'
        );
    }

    /**
     * Parse and preview an uploaded Weekly Itinerary Report without saving.
     */
    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('create', AdvancedItinerary::class);

        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'extensions:xlsx,xlsb,xls'],
        ]);

        $uploadedFile = $request->file('file');
        if (! $uploadedFile) {
            return response()->json([
                'success' => false,
                'message' => 'No spreadsheet file was provided.',
            ], 422);
        }

        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $token = (string) Str::uuid();
        $storedName = "itinerary-imports/{$token}.{$extension}";

        try {
            // Save temporary file privately for confirm step
            Storage::disk('local')->putFileAs('itinerary-imports', $uploadedFile, "{$token}.{$extension}");
            $fullPath = Storage::disk('local')->path($storedName);

            $parsed = $this->importService->parse($fullPath, $uploadedFile->getClientOriginalName());

            return response()->json([
                'success' => true,
                'import_token' => $token,
                'extension' => $extension,
                'file_name' => $parsed['file_name'],
                'sheet_name' => $parsed['sheet_name'],
                'header' => $parsed['header'],
                'vehicle_match' => [
                    'is_matched' => $parsed['vehicle_match']['is_matched'],
                    'vehicle_id' => $parsed['vehicle_match']['vehicle']?->id,
                    'equipment_code' => $parsed['vehicle_match']['vehicle']?->equipment_code ?? $parsed['vehicle_match']['detected_eqtp_code'],
                    'plate_number' => $parsed['vehicle_match']['vehicle']?->plate_number ?? $parsed['vehicle_match']['detected_plate'],
                    'model' => $parsed['vehicle_match']['vehicle']?->model,
                    'avg_consumption' => $parsed['avg_consumption'],
                    'confidence' => $parsed['vehicle_match']['confidence'],
                ],
                'summary' => $parsed['summary'],
                'date_groups' => array_values($parsed['date_groups']),
            ]);
        } catch (Throwable $e) {
            // Clean up temporary file on failure
            Storage::disk('local')->delete($storedName);

            return response()->json([
                'success' => false,
                'message' => 'Spreadsheet processing failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Commit the previewed Weekly Itinerary Report into the database.
     */
    public function confirm(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', AdvancedItinerary::class);

        $validated = $request->validate([
            'import_token' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'extension' => ['required', 'string', 'in:xlsx,xlsb,xls'],
            'vehicle_id' => ['nullable'],
            'custom_equipment_code' => ['nullable', 'string', 'max:50'],
            'custom_plate_number' => ['nullable', 'string', 'max:50'],
            'custom_average_consumption' => ['nullable', 'numeric', 'min:0.01'],
            'driver_name' => ['nullable', 'string', 'max:255'],
        ]);

        $token = $validated['import_token'];
        $ext = $validated['extension'];
        $storedName = "itinerary-imports/{$token}.{$ext}";

        if (! Storage::disk('local')->exists($storedName)) {
            $msg = 'Import session expired or file not found. Please upload the spreadsheet again.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 404);
            }

            return redirect()->route('fuel-po.index')->withErrors(['file' => $msg]);
        }

        $fullPath = Storage::disk('local')->path($storedName);

        try {
            $options = [
                'vehicle_id' => ! empty($validated['vehicle_id']) ? (int) $validated['vehicle_id'] : null,
                'custom_equipment_code' => $validated['custom_equipment_code'] ?? null,
                'custom_plate_number' => $validated['custom_plate_number'] ?? null,
                'custom_average_consumption' => ! empty($validated['custom_average_consumption']) ? (float) $validated['custom_average_consumption'] : null,
                'driver_name' => $validated['driver_name'] ?? null,
                'status' => AdvancedItinerary::STATUS_FINALIZED,
            ];

            $result = $this->importService->import($fullPath, (int) auth()->id(), $options);

            // Clean up temporary file
            Storage::disk('local')->delete($storedName);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'count' => $result['count'],
                    'total_legs' => $result['total_legs'],
                    'message' => $result['message'],
                ]);
            }

            return redirect()->route('fuel-po.index')->with('success', $result['message']);
        } catch (Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to commit imported itinerary records: '.$e->getMessage(),
                ], 422);
            }

            return redirect()->route('fuel-po.index')->withErrors(['file' => $e->getMessage()]);
        }
    }
}
