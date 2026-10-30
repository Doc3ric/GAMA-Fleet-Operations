<?php

namespace App\Http\Controllers;

use App\Exports\DispatchPunctualityExport;
use App\Models\AdvancedItinerary;
use App\Models\FleetDispatch;
use App\Models\Vehicle;
use App\Services\DispatchMonitoringService;
use App\Services\MileageReconciliationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FleetDispatchController extends Controller
{
    public function __construct(
        protected DispatchMonitoringService $dispatchService,
        protected MileageReconciliationService $reconciliationService
    ) {}

    /**
     * Display the On-Time Dispatch Rate Dashboard and dispatch table.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', FleetDispatch::class);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $vehicleId = $request->filled('vehicle_id') ? (int) $request->input('vehicle_id') : null;
        $status = $request->input('status');
        $driver = $request->input('driver');

        // Statistics respect date range and vehicle filter
        $statistics = $this->dispatchService->getStatistics($startDate, $endDate, $vehicleId);

        $query = FleetDispatch::with(['vehicle', 'reconciliation', 'itinerary'])
            ->latest('trip_date')
            ->latest('planned_departure_time');

        if ($startDate) {
            $query->whereDate('trip_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('trip_date', '<=', $endDate);
        }
        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }
        if ($status && $status !== 'all') {
            $query->where('dispatch_status', $status);
        }
        if ($driver) {
            $query->where('driver_name', 'like', "%{$driver}%");
        }

        $dispatches = $query->paginate(15)->withQueryString();
        $vehicles = Vehicle::orderBy('equipment_code')->get();

        // Recent finalized itineraries for quick-linking into new dispatches
        $approvedItineraries = AdvancedItinerary::with('vehicle')
            ->where('status', AdvancedItinerary::STATUS_FINALIZED)
            ->whereDoesntHave('dispatches')
            ->latest('itinerary_date')
            ->limit(20)
            ->get();

        return view('fleet-dispatches.index', [
            'dispatches' => $dispatches,
            'statistics' => $statistics,
            'vehicles' => $vehicles,
            'approvedItineraries' => $approvedItineraries,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'vehicle_id' => $vehicleId,
                'status' => $status,
                'driver' => $driver,
            ],
        ]);
    }

    /**
     * Store a new dispatch record (and initialize its mileage reconciliation).
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', FleetDispatch::class);

        $validated = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'advanced_itinerary_id' => ['nullable', 'exists:advanced_itineraries,id'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'trip_date' => ['required', 'date'],
            'starting_point' => ['required', 'string', 'max:200'],
            'destination' => ['required', 'string', 'max:255'],
            'planned_route' => ['nullable', 'string'],
            'planned_distance_km' => ['required', 'numeric', 'min:0'],
            'planned_departure_time' => ['required', 'date'],
            'actual_departure_time' => ['nullable', 'date'],
            'departure_source' => ['nullable', 'string', 'in:authorized_dispatch,driver_submitted,gps_movement'],
            'grace_period_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'create_reconciliation' => ['nullable', 'boolean'],
            'starting_odometer' => ['nullable', 'numeric', 'min:0'],
            'ending_odometer' => ['nullable', 'numeric', 'min:0'],
        ]);

        $dispatch = $this->dispatchService->createDispatch($validated, $request->user()->id);

        // Automatically create associated Mileage Reconciliation in DRAFT
        if ($request->boolean('create_reconciliation', true)) {
            $this->reconciliationService->createReconciliation($dispatch, [
                'planned_distance_km' => $dispatch->planned_distance_km,
                'starting_odometer' => $validated['starting_odometer'] ?? null,
                'ending_odometer' => $validated['ending_odometer'] ?? null,
                'notes' => $dispatch->notes,
            ], $request->user()->id);
        }

        return redirect()->route('fleet-dispatches.index')
            ->with('status', "Dispatch trip {$dispatch->trip_number} created successfully.");
    }

    /**
     * Record actual departure time for a trip.
     */
    public function recordDeparture(Request $request, FleetDispatch $dispatch): RedirectResponse
    {
        Gate::authorize('recordDeparture', $dispatch);

        $validated = $request->validate([
            'actual_departure_time' => ['required', 'date'],
            'departure_source' => ['required', 'string', 'in:authorized_dispatch,driver_submitted,gps_movement'],
        ]);

        $this->dispatchService->recordDeparture(
            $dispatch,
            Carbon::parse($validated['actual_departure_time']),
            $validated['departure_source'],
            $request->user()->id
        );

        return redirect()->back()
            ->with('status', "Actual departure recorded for {$dispatch->trip_number}. Status updated to {$dispatch->dispatch_status}.");
    }

    /**
     * 1-Click quick departure using the current timestamp.
     */
    public function departNow(Request $request, FleetDispatch $dispatch): RedirectResponse
    {
        Gate::authorize('recordDeparture', $dispatch);

        $now = Carbon::now();
        $this->dispatchService->recordDeparture(
            $dispatch,
            $now,
            'authorized_dispatch',
            $request->user()->id
        );

        return redirect()->back()
            ->with('status', "Trip {$dispatch->trip_number} departed at {$now->format('h:i A')} ({$dispatch->dispatch_status})!");
    }

    /**
     * Auto-generate active trip dispatches from existing approved itineraries.
     */
    public function generateFromItineraries(Request $request): RedirectResponse
    {
        Gate::authorize('create', FleetDispatch::class);

        $itineraries = AdvancedItinerary::with(['vehicle', 'legs'])
            ->whereDoesntHave('dispatches')
            ->latest('itinerary_date')
            ->limit(25)
            ->get();

        if ($itineraries->isEmpty()) {
            return redirect()->back()->withErrors(['itineraries' => 'No unassigned itineraries found to convert into dispatches.']);
        }

        $createdCount = 0;
        foreach ($itineraries as $itinerary) {
            $vehicle = $itinerary->vehicle;
            if (! $vehicle) {
                continue;
            }

            $date = $itinerary->itinerary_date ?: Carbon::today();
            $plannedDep = $date->copy()->setHour(8)->setMinute(0);

            $firstLeg = $itinerary->legs->first();
            $lastLeg = $itinerary->legs->last();

            $startingPoint = $firstLeg?->origin ?? 'Cagayan de Oro Main Yard';
            $destination = $lastLeg?->destination ?? ($firstLeg?->destination ?? 'Regional Depot');
            $plannedDistance = (float) ($itinerary->total_distance_km ?? 0.0);
            if ($plannedDistance <= 0.0) {
                $plannedDistance = 85.0;
            }

            $dispatch = $this->dispatchService->createDispatch([
                'vehicle_id' => $vehicle->id,
                'advanced_itinerary_id' => $itinerary->id,
                'trip_date' => $date->format('Y-m-d'),
                'driver_name' => $itinerary->driver_name ?: 'Assigned Fleet Driver',
                'starting_point' => $startingPoint,
                'destination' => $destination,
                'planned_route' => $itinerary->route_name ?: 'Approved Distribution Route',
                'planned_distance_km' => $plannedDistance,
                'planned_departure_time' => $plannedDep,
                'grace_period_minutes' => 15,
                'notes' => "Auto-generated from Itinerary #{$itinerary->id}",
            ], $request->user()->id);

            $prevOdo = (float) ($vehicle->odometer ?? 25000.0);
            $this->reconciliationService->initializeForDispatch($dispatch, [
                'starting_odometer' => $prevOdo,
                'ending_odometer' => $prevOdo + $plannedDistance,
                'notes' => 'Generated from operational itinerary.',
            ], $request->user()->id);

            $createdCount++;
        }

        return redirect()->back()
            ->with('status', "Successfully generated {$createdCount} active trip dispatches and reconciliations from your itineraries!");
    }

    /**
     * Export dispatch punctuality report to Excel.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        Gate::authorize('export', FleetDispatch::class);

        $query = $this->buildFilteredQuery($request);
        $fileName = 'Dispatch_Punctuality_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new DispatchPunctualityExport($query), $fileName);
    }

    /**
     * Export dispatch punctuality report to PDF.
     */
    public function exportPdf(Request $request): Response
    {
        Gate::authorize('export', FleetDispatch::class);

        $query = $this->buildFilteredQuery($request);
        $dispatches = $query->get();

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $vehicleId = $request->filled('vehicle_id') ? (int) $request->input('vehicle_id') : null;
        $statistics = $this->dispatchService->getStatistics($startDate, $endDate, $vehicleId);

        $vehicleFilter = null;
        if ($vehicleId) {
            $v = Vehicle::find($vehicleId);
            $vehicleFilter = $v ? "{$v->equipment_code} ({$v->plate_number})" : null;
        }

        $dateRangeText = ($startDate && $endDate)
            ? "{$startDate} to {$endDate}"
            : ($startDate ? "From {$startDate}" : ($endDate ? "Up to {$endDate}" : 'All Time'));

        $pdf = Pdf::loadView('pdf.dispatch-punctuality-report', [
            'dispatches' => $dispatches,
            'statistics' => $statistics,
            'dateRangeText' => $dateRangeText,
            'vehicleFilter' => $vehicleFilter,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('Dispatch_Punctuality_Report_'.now()->format('Ymd_His').'.pdf');
    }

    protected function buildFilteredQuery(Request $request)
    {
        $query = FleetDispatch::with(['vehicle'])->latest('trip_date')->latest('planned_departure_time');

        if ($request->filled('start_date')) {
            $query->whereDate('trip_date', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('trip_date', '<=', $request->input('end_date'));
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('dispatch_status', $request->input('status'));
        }
        if ($request->filled('driver')) {
            $query->where('driver_name', 'like', '%'.$request->input('driver').'%');
        }

        return $query;
    }
}
