<?php

namespace App\Http\Controllers;

use App\Exports\MileageReconciliationExport;
use App\Models\Device;
use App\Models\MileageAuditLog;
use App\Models\MileageEvidence;
use App\Models\MileageReconciliation;
use App\Models\ReconciliationException;
use App\Models\Vehicle;
use App\Services\MileageReconciliationService;
use App\Services\TrackSolidApiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MileageReconciliationController extends Controller
{
    public function __construct(
        protected MileageReconciliationService $reconciliationService
    ) {}

    /**
     * Display the Mileage Reconciliation Dashboard.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MileageReconciliation::class);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $vehicleId = $request->filled('vehicle_id') ? (int) $request->input('vehicle_id') : null;
        $gpsFilter = $request->input('gps'); // 'all', 'gps', 'non_gps'
        $status = $request->input('status');
        $exceptionFilter = $request->input('exception'); // 'all', 'yes', 'no'

        $statistics = $this->reconciliationService->getStatistics($startDate, $endDate, $vehicleId);

        $query = MileageReconciliation::with(['dispatch', 'vehicle', 'evidences', 'reviewer'])
            ->latest('trip_date')
            ->latest('id');

        if ($startDate) {
            $query->whereDate('trip_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('trip_date', '<=', $endDate);
        }
        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }
        if ($gpsFilter === 'gps') {
            $query->where('is_gps_vehicle', true);
        } elseif ($gpsFilter === 'non_gps') {
            $query->where('is_gps_vehicle', false);
        }
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($exceptionFilter === 'yes') {
            $query->where('has_exceptions', true);
        } elseif ($exceptionFilter === 'no') {
            $query->where('has_exceptions', false);
        }

        $reconciliations = $query->paginate(15)->withQueryString();
        $vehicles = Vehicle::orderBy('equipment_code')->get();

        return view('mileage-reconciliations.index', [
            'reconciliations' => $reconciliations,
            'statistics' => $statistics,
            'vehicles' => $vehicles,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'vehicle_id' => $vehicleId,
                'gps' => $gpsFilter,
                'status' => $status,
                'exception' => $exceptionFilter,
            ],
        ]);
    }

    /**
     * Display the specified reconciliation detail and review screen.
     */
    public function show(MileageReconciliation $mileageReconciliation): View
    {
        Gate::authorize('view', $mileageReconciliation);

        $mileageReconciliation->load([
            'dispatch.vehicle',
            'dispatch.itinerary.legs',
            'vehicle',
            'evidences.verifier',
            'evidences.creator',
            'exceptions.resolver',
            'auditLogs.user',
            'reviewer',
            'submitter',
        ]);

        return view('mileage-reconciliations.show', [
            'reconciliation' => $mileageReconciliation,
        ]);
    }

    /**
     * Update odometer readings or details for a draft/revision reconciliation.
     */
    public function update(Request $request, MileageReconciliation $mileageReconciliation): RedirectResponse
    {
        Gate::authorize('update', $mileageReconciliation);

        $validated = $request->validate([
            'starting_odometer' => ['required', 'numeric', 'min:0'],
            'ending_odometer' => ['required', 'numeric', 'min:0'],
            'planned_distance_km' => ['nullable', 'numeric', 'min:0'],
            'gps_reported_km' => ['nullable', 'numeric', 'min:0'],
            'is_gps_vehicle' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->reconciliationService->updateReconciliation($mileageReconciliation, $validated, $request->user()->id);

        return redirect()->back()
            ->with('status', 'Mileage reconciliation readings updated successfully.');
    }

    /**
     * Submit reconciliation for review after validation.
     */
    public function submit(MileageReconciliation $mileageReconciliation): RedirectResponse
    {
        Gate::authorize('submit', $mileageReconciliation);

        try {
            $this->reconciliationService->submit($mileageReconciliation, auth()->id());

            return redirect()->back()
                ->with('status', "Reconciliation {$mileageReconciliation->reconciliation_number} submitted for review.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['submission_error' => $e->getMessage()]);
        }
    }

    /**
     * Approve reconciliation by authorized reviewer.
     */
    public function approve(Request $request, MileageReconciliation $mileageReconciliation): RedirectResponse
    {
        Gate::authorize('approve', $mileageReconciliation);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->reconciliationService->approve($mileageReconciliation, $request->user()->id, $validated['remarks'] ?? null);

        return redirect()->back()
            ->with('status', "Reconciliation {$mileageReconciliation->reconciliation_number} has been officially approved.");
    }

    /**
     * Reject reconciliation with mandatory remarks.
     */
    public function reject(Request $request, MileageReconciliation $mileageReconciliation): RedirectResponse
    {
        Gate::authorize('reject', $mileageReconciliation);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $this->reconciliationService->reject($mileageReconciliation, $request->user()->id, $validated['remarks']);

        return redirect()->back()
            ->with('status', "Reconciliation {$mileageReconciliation->reconciliation_number} rejected. Remarks recorded.");
    }

    /**
     * Reopen approved or rejected reconciliation for documented revision.
     */
    public function reopen(Request $request, MileageReconciliation $mileageReconciliation): RedirectResponse
    {
        Gate::authorize('reopen', $mileageReconciliation);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $this->reconciliationService->reopen($mileageReconciliation, $request->user()->id, $validated['reason']);

        return redirect()->back()
            ->with('status', "Reconciliation {$mileageReconciliation->reconciliation_number} reopened for revision.");
    }

    /**
     * Upload supporting evidence (photo, verified reading form, GPS report).
     */
    public function uploadEvidence(Request $request, MileageReconciliation $mileageReconciliation): RedirectResponse
    {
        Gate::authorize('uploadEvidence', $mileageReconciliation);

        $validated = $request->validate([
            'evidence_type' => ['required', 'string', 'in:odometer_photo,verified_reading_form,gps_report,other'],
            'evidence_file' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,pdf,xlsx,csv'], // 10MB max
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $request->file('evidence_file');
        $disk = config('fleet_reconciliation.evidence_storage_disk', 'public');
        $path = $file->store('reconciliation-evidence', $disk);

        $evidence = MileageEvidence::create([
            'mileage_reconciliation_id' => $mileageReconciliation->id,
            'evidence_type' => $validated['evidence_type'],
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $this->reconciliationService->evaluateExceptions($mileageReconciliation);

        return redirect()->back()
            ->with('status', "Supporting evidence '{$evidence->original_filename}' uploaded successfully.");
    }

    /**
     * Authorized private download of evidence file.
     */
    public function downloadEvidence(MileageEvidence $evidence): BinaryFileResponse
    {
        Gate::authorize('view', $evidence->reconciliation);

        $disk = config('fleet_reconciliation.evidence_storage_disk', 'public');

        if (! Storage::disk($disk)->exists($evidence->file_path)) {
            abort(404, 'Evidence file not found.');
        }

        $fullPath = Storage::disk($disk)->path($evidence->file_path);

        return response()->download($fullPath, $evidence->original_filename);
    }

    /**
     * Resolve an exception.
     */
    public function resolveException(Request $request, ReconciliationException $exception): RedirectResponse
    {
        Gate::authorize('approve', $exception->reconciliation);

        $validated = $request->validate([
            'resolution_remarks' => ['required', 'string', 'max:500'],
        ]);

        $exception->resolve($request->user()->id, $validated['resolution_remarks']);
        $this->reconciliationService->evaluateExceptions($exception->reconciliation);

        return redirect()->back()
            ->with('status', 'Exception resolved successfully.');
    }

    /**
     * Waive an exception.
     */
    public function waiveException(Request $request, ReconciliationException $exception): RedirectResponse
    {
        Gate::authorize('approve', $exception->reconciliation);

        $validated = $request->validate([
            'resolution_remarks' => ['required', 'string', 'max:500'],
        ]);

        $exception->waive($request->user()->id, $validated['resolution_remarks']);
        $this->reconciliationService->evaluateExceptions($exception->reconciliation);

        return redirect()->back()
            ->with('status', 'Exception waived by reviewer.');
    }

    /**
     * Pull live GPS mileage & odometer telemetry directly from TrackSolid Pro API.
     */
    public function syncGps(MileageReconciliation $mileageReconciliation, TrackSolidApiService $apiService): RedirectResponse
    {
        Gate::authorize('update', $mileageReconciliation);

        $vehicle = $mileageReconciliation->vehicle;
        if (! $vehicle) {
            return redirect()->back()->withErrors(['vehicle' => 'No vehicle attached to this reconciliation.']);
        }

        $device = $vehicle->device ?: Device::where('vehicle_id', $vehicle->id)->first();
        if (! $device || empty($device->imei)) {
            return redirect()->back()->withErrors(['gps' => "Vehicle {$vehicle->equipment_code} does not have an assigned GPS device IMEI in Vehicle Master."]);
        }

        $dispatch = $mileageReconciliation->dispatch;
        $start = $dispatch?->actual_departure_time
            ?: ($dispatch?->planned_departure_time
                ?: Carbon::parse($mileageReconciliation->trip_date)->startOfDay());

        $startTime = $start->format('Y-m-d H:i:s');
        $endTime = $start->copy()->endOfDay()->format('Y-m-d H:i:s');

        $telemetry = $apiService->getMileageForDevice($device->imei, $startTime, $endTime);

        if (! $telemetry || $telemetry['run_distance_km'] === null) {
            return redirect()->back()->withErrors(['gps' => "No GPS trip telemetry returned from TrackSolid for device IMEI {$device->imei} on {$start->format('M d, Y')}."]);
        }

        $gpsKm = (float) $telemetry['run_distance_km'];
        $mileageReconciliation->gps_reported_km = $gpsKm;
        $mileageReconciliation->is_gps_vehicle = true;

        if ($mileageReconciliation->starting_odometer === null && ! empty($telemetry['start_odometer'])) {
            $mileageReconciliation->starting_odometer = $telemetry['start_odometer'];
        }
        if ($mileageReconciliation->ending_odometer === null && ! empty($telemetry['end_odometer'])) {
            $mileageReconciliation->ending_odometer = $telemetry['end_odometer'];
        }

        $mileageReconciliation->save();
        $this->reconciliationService->recalculateAndEvaluate($mileageReconciliation);

        MileageAuditLog::record(
            $mileageReconciliation,
            'SYNC_GPS',
            "Synchronized {$gpsKm} KM from TrackSolid Pro for device IMEI {$device->imei}.",
            ['telemetry' => $telemetry]
        );

        return redirect()->back()->with('status', "Successfully pulled {$gpsKm} KM live from TrackSolid Pro (Device IMEI: {$device->imei})!");
    }

    /**
     * Export reconciliations to Excel.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        Gate::authorize('export', MileageReconciliation::class);

        $query = $this->buildFilteredQuery($request);
        $fileName = 'Mileage_Reconciliation_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new MileageReconciliationExport($query), $fileName);
    }

    /**
     * Export reconciliations to PDF.
     */
    public function exportPdf(Request $request): Response
    {
        Gate::authorize('export', MileageReconciliation::class);

        $query = $this->buildFilteredQuery($request);
        $records = $query->get();

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $vehicleId = $request->filled('vehicle_id') ? (int) $request->input('vehicle_id') : null;

        $vehicleFilter = null;
        if ($vehicleId) {
            $v = Vehicle::find($vehicleId);
            $vehicleFilter = $v ? "{$v->equipment_code} ({$v->plate_number})" : null;
        }

        $dateRangeText = ($startDate && $endDate)
            ? "{$startDate} to {$endDate}"
            : ($startDate ? "From {$startDate}" : ($endDate ? "Up to {$endDate}" : 'All Records'));

        $pdf = Pdf::loadView('pdf.mileage-reconciliation-report', [
            'records' => $records,
            'dateRangeText' => $dateRangeText,
            'vehicleFilter' => $vehicleFilter,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('Mileage_Reconciliation_Report_'.now()->format('Ymd_His').'.pdf');
    }

    protected function buildFilteredQuery(Request $request)
    {
        $query = MileageReconciliation::with(['dispatch', 'vehicle', 'reviewer'])
            ->latest('trip_date')
            ->latest('id');

        if ($request->filled('start_date')) {
            $query->whereDate('trip_date', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('trip_date', '<=', $request->input('end_date'));
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }
        if ($request->input('gps') === 'gps') {
            $query->where('is_gps_vehicle', true);
        } elseif ($request->input('gps') === 'non_gps') {
            $query->where('is_gps_vehicle', false);
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }
        if ($request->input('exception') === 'yes') {
            $query->where('has_exceptions', true);
        } elseif ($request->input('exception') === 'no') {
            $query->where('has_exceptions', false);
        }

        return $query;
    }
}
