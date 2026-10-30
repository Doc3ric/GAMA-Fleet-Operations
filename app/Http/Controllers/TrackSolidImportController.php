<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\GpsReportImport;
use App\Models\Vehicle;
use App\Services\TrackSolidApiService;
use App\Services\TrackSolidReportImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackSolidImportController extends Controller
{
    public function __construct(
        protected TrackSolidReportImportService $importService
    ) {}

    /**
     * Display the TrackSolid report upload form and recent imports history.
     */
    public function index(): View
    {
        $recentImports = GpsReportImport::with('creator')
            ->withCount('records')
            ->latest()
            ->paginate(10);

        return view('tracksolid-reports.index', [
            'recentImports' => $recentImports,
        ]);
    }

    /**
     * Parse and preview an uploaded TrackSolid report.
     */
    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'report_file' => ['required', 'file', 'max:15360', 'mimes:xlsx,csv,xls'], // 15MB max
        ]);

        $file = $request->file('report_file');

        try {
            $parsed = $this->importService->parse($file);

            // Store temporary file path and parsed data in session for confirmation
            $tempPath = $file->store('temp-imports', 'local');

            session([
                'tracksolid_preview_data' => $parsed,
                'tracksolid_temp_file' => $tempPath,
                'tracksolid_original_name' => $file->getClientOriginalName(),
            ]);

            return view('tracksolid-reports.preview', [
                'preview' => $parsed,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('tracksolid-reports.index')
                ->withErrors(['report_file' => 'Import Error: '.$e->getMessage()]);
        }
    }

    /**
     * Confirm and commit previewed TrackSolid report into database.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $previewData = session('tracksolid_preview_data');
        $tempFile = session('tracksolid_temp_file');
        $originalName = session('tracksolid_original_name');

        if (! $previewData || ! $tempFile) {
            return redirect()->route('tracksolid-reports.index')
                ->withErrors(['report_file' => 'Session expired. Please upload the report again.']);
        }

        $fullTempPath = storage_path('app/'.$tempFile);
        $linkReconciliations = $request->boolean('link_reconciliations', true);

        try {
            $import = $this->importService->commit(
                file: $fullTempPath,
                parsedData: $previewData,
                userId: $request->user()->id,
                linkToReconciliations: $linkReconciliations
            );

            // Clean up session and temp file
            session()->forget(['tracksolid_preview_data', 'tracksolid_temp_file', 'tracksolid_original_name']);
            if (file_exists($fullTempPath)) {
                @unlink($fullTempPath);
            }

            return redirect()->route('tracksolid-reports.index')
                ->with('status', "TrackSolid report '{$import->filename}' successfully imported! {$import->matched_rows} matched, {$import->unmatched_rows} unmatched, {$import->duplicate_rows} duplicates.");
        } catch (\Exception $e) {
            return redirect()->route('tracksolid-reports.index')
                ->withErrors(['report_file' => 'Commit Failed: '.$e->getMessage()]);
        }
    }

    /**
     * 1-Click live synchronization of all fleet devices from TrackSolid Pro Open API.
     */
    public function syncDevicesApi(TrackSolidApiService $apiService): RedirectResponse
    {
        $devices = $apiService->getDeviceList();

        if (empty($devices)) {
            return redirect()->route('tracksolid-reports.index')
                ->withErrors(['api' => 'Unable to retrieve devices from TrackSolid Pro. Please check API credentials or network connectivity.']);
        }

        $matched = 0;
        $totalSynced = 0;

        foreach ($devices as $d) {
            $imei = trim((string) ($d['imei'] ?? ''));
            if (empty($imei)) {
                continue;
            }

            $deviceName = trim((string) ($d['deviceName'] ?? ''));
            $plateNumber = trim((string) ($d['vehicleNumber'] ?? ''));

            // Match vehicle by plate number or equipment code
            $vehicle = null;
            if (! empty($plateNumber)) {
                $vehicle = Vehicle::where('plate_number', $plateNumber)
                    ->orWhere('plate_number', str_replace(' ', '', $plateNumber))
                    ->first();
            }

            if (! $vehicle && ! empty($deviceName)) {
                $vehicle = Vehicle::where('equipment_code', $deviceName)
                    ->orWhere('plate_number', $deviceName)
                    ->first();
            }

            $deviceModel = Device::firstOrNew(['imei' => $imei]);
            $deviceModel->device_name = $deviceName ?: ($vehicle?->equipment_code ?? "GPS-{$imei}");
            $deviceModel->sim = $d['sim'] ?? $deviceModel->sim;
            $deviceModel->model = $d['mcType'] ?? $deviceModel->model;
            $deviceModel->group_name = $d['deviceGroup'] ?? $deviceModel->group_name;

            if ($vehicle) {
                $deviceModel->vehicle_id = $vehicle->id;
                $vehicle->gps_status = 'YES';
                $vehicle->save();
                $matched++;
            }

            $deviceModel->save();
            $totalSynced++;
        }

        return redirect()->route('tracksolid-reports.index')
            ->with('status', "Successfully pulled {$totalSynced} live GPS devices from TrackSolid Pro! {$matched} vehicles were automatically matched and updated to GPS-active.");
    }
}
