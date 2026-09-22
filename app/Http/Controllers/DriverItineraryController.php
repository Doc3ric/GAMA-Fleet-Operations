<?php

namespace App\Http\Controllers;

use App\Exports\DriverItineraryExport;
use App\Http\Requests\DriverItineraryFilterRequest;
use App\Http\Requests\DriverItineraryReportRequest;
use App\Models\DriverTrip;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverItineraryService;
use App\Services\LocationRecognitionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DriverItineraryController extends Controller
{
    public function __construct(
        protected DriverItineraryService $itineraryService,
        protected LocationRecognitionService $recognitionService
    ) {}

    /**
     * Display a paginated listing of driver itineraries.
     */
    public function index(DriverItineraryFilterRequest $request): View
    {
        Gate::authorize('viewAny', DriverTrip::class);

        $filters = $request->validated();
        $query = $this->itineraryService->getFilteredQuery($filters);

        $trips = $query->paginate(15)->withQueryString();
        $statistics = $this->itineraryService->getStatistics($filters);

        $drivers = User::where('role', User::ROLE_DRIVER)->orderBy('name')->get();
        $vehicles = Vehicle::orderBy('equipment_code')->get();

        // Perform batch recognition with zero N+1 overhead
        $recognitions = $this->recognitionService->recognizeMany($trips->items());
        $activeLocations = Location::active()->with('aliases')->orderBy('official_name')->get();

        return view('itineraries.index', [
            'trips' => $trips,
            'statistics' => $statistics,
            'drivers' => $drivers,
            'vehicles' => $vehicles,
            'filters' => $filters,
            'recognitions' => $recognitions,
            'activeLocations' => $activeLocations,
        ]);
    }

    /**
     * Display the specified trip detail with Leaflet map view.
     */
    public function show(DriverTrip $trip): View
    {
        Gate::authorize('view', $trip);

        $trip->load(['driver', 'vehicle.vehicleType', 'driverVehicleAssignment', 'location.aliases']);
        $recognition = $this->recognitionService->recognizeForTrip($trip);
        $activeLocations = Location::active()->with('aliases')->orderBy('official_name')->get();

        return view('itineraries.show', [
            'trip' => $trip,
            'recognition' => $recognition,
            'activeLocations' => $activeLocations,
        ]);
    }

    /**
     * Resolve/link an unknown or suggested trip destination to an official Location (Adjustment 4 & 6).
     */
    public function resolveLocation(Request $request, DriverTrip $trip): JsonResponse|RedirectResponse
    {
        Gate::authorize('viewAny', DriverTrip::class);

        $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'save_as_alias' => ['nullable', 'boolean'],
            'backfill_historical' => ['nullable', 'boolean'],
        ]);

        $location = Location::findOrFail($request->input('location_id'));
        $rawDriverText = trim((string) $trip->destination_address);

        // 1. Raw driver text is STRICTLY preserved (Adjustment 1)
        // Only location_id is linked to the trip
        $trip->update(['location_id' => $location->id]);

        $aliasAdded = false;
        $warningMsg = null;

        // 2. If requested, save the driver entry as an alias
        if ($request->boolean('save_as_alias', true) && $rawDriverText !== '') {
            $conflict = $this->recognitionService->findConflictingLocationForAlias($rawDriverText, $location->id);
            if ($conflict) {
                $warningMsg = "Destination linked, but alias '{$rawDriverText}' was NOT saved because it already belongs to active location '{$conflict->official_name}'.";
            } else {
                $location->aliases()->firstOrCreate(['alias' => $rawDriverText]);
                $aliasAdded = true;
            }
        }

        // 3. If requested, safely backfill matching historical trips without touching raw entry (Adjustment 4)
        $backfilledCount = 0;
        if ($request->boolean('backfill_historical', true) && $rawDriverText !== '') {
            $backfilledCount = $this->recognitionService->backfillHistoricalTrips($rawDriverText, $location);
        }

        $this->recognitionService->clearCache();

        $successMsg = "Trip #{$trip->id} destination '{$rawDriverText}' resolved to '{$location->official_name}'.";
        if ($aliasAdded) {
            $successMsg .= " Saved '{$rawDriverText}' as a recognized alias.";
        }
        if ($backfilledCount > 0) {
            $successMsg .= " Automatically updated {$backfilledCount} matching historical trips.";
        }
        if ($warningMsg) {
            $successMsg .= " (Warning: {$warningMsg})";
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'location' => $location,
            ]);
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Generate weekly or custom date-range driver itinerary report preview.
     */
    public function report(DriverItineraryReportRequest $request): View
    {
        Gate::authorize('viewAny', DriverTrip::class);

        $options = $request->validated();
        $reportData = $this->itineraryService->getReportData($options);

        $drivers = User::where('role', User::ROLE_DRIVER)->orderBy('name')->get();
        $vehicles = Vehicle::orderBy('equipment_code')->get();

        return view('itineraries.report', array_merge($reportData, [
            'drivers' => $drivers,
            'vehicles' => $vehicles,
            'options' => $options,
        ]));
    }

    /**
     * Export driver itinerary report to PDF.
     */
    public function exportPdf(DriverItineraryReportRequest $request): Response
    {
        Gate::authorize('viewAny', DriverTrip::class);

        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $options = $request->validated();
        $reportData = $this->itineraryService->getReportData($options);

        $pdf = Pdf::loadView('pdf.driver-itinerary-report', array_merge($reportData, [
            'company' => config('foms.company_name', 'GAMA'),
            'tagline' => config('foms.company_tagline', 'Fleet Operations Management System'),
            'preparedBy' => config('foms.prepared_by', 'GPS Monitoring Specialist'),
        ]))
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true);

        $filename = 'Driver-Itinerary-Report-'.$reportData['start_date']->format('Y-m-d').'-to-'.$reportData['end_date']->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export driver itineraries to Excel using current filter criteria.
     */
    public function exportExcel(DriverItineraryFilterRequest $request): BinaryFileResponse
    {
        Gate::authorize('viewAny', DriverTrip::class);

        $filters = $request->validated();
        $query = $this->itineraryService->getFilteredQuery($filters);

        $filename = 'Driver-Itinerary-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new DriverItineraryExport($query), $filename);
    }
}
