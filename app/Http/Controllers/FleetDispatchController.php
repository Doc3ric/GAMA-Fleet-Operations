<?php

namespace App\Http\Controllers;

use App\Exports\DispatchPunctualityExport;
use App\Models\AdvancedItinerary;
use App\Models\DispatchMonitorEvent;
use App\Models\FleetDispatch;
use App\Models\Location;
use App\Models\Vehicle;
use App\Services\DispatchGpsTrackingService;
use App\Services\DispatchMonitoringService;
use App\Services\MileageReconciliationService;
use App\Services\RoutingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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
        protected MileageReconciliationService $reconciliationService,
        protected DispatchGpsTrackingService $trackingService,
        protected RoutingService $routingService
    ) {}

    /**
     * Display the On-Time Dispatch Rate Dashboard, dispatch table, and live monitoring alerts.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', FleetDispatch::class);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $vehicleId = $request->filled('vehicle_id') ? (int) $request->input('vehicle_id') : null;
        $status = $request->input('status');
        $monitoringStatus = $request->input('monitoring_status');
        $driver = $request->input('driver');

        // Statistics respect date range and vehicle filter
        $statistics = $this->dispatchService->getStatistics($startDate, $endDate, $vehicleId);

        $query = FleetDispatch::with(['vehicle.device', 'reconciliation', 'itinerary', 'activeMonitorEvents'])
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
        if ($monitoringStatus && $monitoringStatus !== 'all') {
            $query->where('monitoring_status', $monitoringStatus);
        }
        if ($driver) {
            $query->where('driver_name', 'like', "%{$driver}%");
        }

        $dispatches = $query->paginate(15)->withQueryString();
        $vehicles = Vehicle::with('device')->orderBy('equipment_code')->get();

        // Recent finalized itineraries for quick-linking into new dispatches
        $approvedItineraries = AdvancedItinerary::with(['vehicle', 'legs'])
            ->where('status', AdvancedItinerary::STATUS_FINALIZED)
            ->whereDoesntHave('dispatches')
            ->latest('itinerary_date')
            ->limit(20)
            ->get();

        // Preloaded location master list for origin/destination pickers
        $locations = Location::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('official_name')
            ->get(['id', 'official_name', 'latitude', 'longitude']);

        // Active monitoring alerts (Vehicles not following assigned routes or schedule)
        $activeAlerts = DispatchMonitorEvent::with(['dispatch.vehicle', 'vehicle'])
            ->active()
            ->latest()
            ->limit(25)
            ->get();

        return view('fleet-dispatches.index', [
            'dispatches' => $dispatches,
            'statistics' => $statistics,
            'vehicles' => $vehicles,
            'locations' => $locations,
            'activeAlerts' => $activeAlerts,
            'approvedItineraries' => $approvedItineraries,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'vehicle_id' => $vehicleId,
                'status' => $status,
                'monitoring_status' => $monitoringStatus,
                'driver' => $driver,
            ],
        ]);
    }

    /**
     * Store a new dispatch record with multi-stop waypoints and initialize mileage reconciliation.
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
            'origin_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'origin_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'destination' => ['required', 'string', 'max:255'],
            'destination_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'planned_route' => ['nullable'],
            'waypoints' => ['nullable', 'array'],
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

        // Process structured multi-stop waypoints into planned_route JSON if provided
        $waypoints = $request->input('waypoints', []);
        $plannedRouteData = [];

        if (! empty($waypoints) && is_array($waypoints)) {
            $formattedWaypoints = [];
            foreach ($waypoints as $wp) {
                if (! empty($wp['address']) || ! empty($wp['name'])) {
                    $formattedWaypoints[] = [
                        'name' => (string) ($wp['name'] ?? $wp['address']),
                        'address' => (string) ($wp['address'] ?? ($wp['name'] ?? '')),
                        'lat' => isset($wp['lat']) && is_numeric($wp['lat']) ? (float) $wp['lat'] : null,
                        'lng' => isset($wp['lng']) && is_numeric($wp['lng']) ? (float) $wp['lng'] : null,
                    ];
                }
            }

            $plannedRouteData = [
                'origin' => [
                    'name' => $validated['starting_point'],
                    'lat' => $validated['origin_latitude'] ?? null,
                    'lng' => $validated['origin_longitude'] ?? null,
                ],
                'intermediate_destinations' => $formattedWaypoints,
                'waypoints' => array_values(array_filter(array_merge(
                    [[
                        'name' => $validated['starting_point'],
                        'lat' => $validated['origin_latitude'] ?? null,
                        'lng' => $validated['origin_longitude'] ?? null,
                    ]],
                    $formattedWaypoints,
                    [[
                        'name' => $validated['destination'],
                        'lat' => $validated['destination_latitude'] ?? null,
                        'lng' => $validated['destination_longitude'] ?? null,
                    ]]
                ), fn ($pt) => $pt['lat'] !== null && $pt['lng'] !== null)),
                'destination' => [
                    'name' => $validated['destination'],
                    'lat' => $validated['destination_latitude'] ?? null,
                    'lng' => $validated['destination_longitude'] ?? null,
                ],
                'distance_km' => (float) $validated['planned_distance_km'],
            ];

            $validated['planned_route'] = json_encode($plannedRouteData);
        } elseif (is_array($request->input('planned_route'))) {
            $validated['planned_route'] = json_encode($request->input('planned_route'));
        }

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
     * API: Calculate multi-point route distance, duration, and geometry.
     */
    public function calculateRoute(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FleetDispatch::class);

        $request->validate([
            'points' => ['required', 'array', 'min:2'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'points.*.name' => ['nullable', 'string'],
        ]);

        $points = $request->input('points', []);
        $result = $this->routingService->calculateMultiPointRoute($points);

        return response()->json($result);
    }

    /**
     * Run automated dispatch monitoring check on-demand.
     */
    public function runMonitoring(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('viewAny', FleetDispatch::class);

        $stats = $this->trackingService->monitorActiveDispatches();

        $message = sprintf(
            'Monitoring check complete: %d dispatches scanned (%d normal, %d possible exceptions, %d confirmed, %d GPS unavailable).',
            $stats['scanned'],
            $stats['normal'],
            $stats['possible_exceptions'],
            $stats['confirmed_exceptions'],
            $stats['gps_unavailable']
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'stats' => $stats, 'message' => $message]);
        }

        return redirect()->back()->with('status', $message);
    }

    /**
     * Resolve an active dispatch monitoring event with remarks.
     */
    public function resolveEvent(Request $request, DispatchMonitorEvent $event): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $event->dispatch);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $event->resolve($request->user()->id, $validated['remarks'] ?? 'Manually acknowledged & resolved by operator.');

        // Re-evaluate parent dispatch monitoring status
        $dispatch = $event->dispatch;
        if ($dispatch) {
            $hasConfirmed = $dispatch->activeMonitorEvents()->confirmed()->exists();
            $hasPossible = $dispatch->activeMonitorEvents()->possible()->exists();

            $status = $hasConfirmed
                ? FleetDispatch::MONITORING_CONFIRMED_EXCEPTION
                : ($hasPossible ? FleetDispatch::MONITORING_POSSIBLE_EXCEPTION : FleetDispatch::MONITORING_NORMAL);

            $dispatch->update(['monitoring_status' => $status]);
        }

        $msg = "Alert for {$event->vehicle?->equipment_code} resolved.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->back()->with('status', $msg);
    }

    /**
     * API: Get live GPS track, planned route corridor, and live telemetry for a dispatch.
     */
    public function liveTrack(FleetDispatch $dispatch): JsonResponse
    {
        Gate::authorize('view', $dispatch);

        $dispatch->loadMissing(['vehicle.device', 'activeMonitorEvents']);
        $vehicle = $dispatch->vehicle;
        $imei = $vehicle?->device?->imei;

        $livePoint = null;
        if ($imei) {
            $batch = $this->trackingService->trackSolidService->getDeviceLocationsWithStatus([trim((string) $imei)]);
            $liveLoc = $batch['locations'][trim((string) $imei)] ?? null;
            if ($liveLoc) {
                $livePoint = $this->trackingService->extractValidPoint($liveLoc, Carbon::now());
            }
        }

        $waypoints = $this->trackingService->extractRouteWaypoints($dispatch);

        return response()->json([
            'dispatch' => [
                'id' => $dispatch->id,
                'trip_number' => $dispatch->trip_number,
                'trip_date' => $dispatch->trip_date->format('Y-m-d'),
                'vehicle_code' => $vehicle?->equipment_code,
                'plate_number' => $vehicle?->plate_number,
                'driver_name' => $dispatch->driver_name,
                'starting_point' => $dispatch->starting_point,
                'destination' => $dispatch->destination,
                'planned_distance_km' => $dispatch->planned_distance_km,
                'dispatch_status' => $dispatch->dispatch_status,
                'monitoring_status' => $dispatch->monitoring_status,
                'delay_minutes' => $dispatch->delay_minutes,
            ],
            'live_point' => $livePoint,
            'waypoints' => $waypoints,
            'planned_route_data' => $dispatch->planned_route_data,
            'active_events' => $dispatch->activeMonitorEvents,
        ]);
    }

    /**
     * API: Fetch unread in-app notifications for authenticated user.
     */
    public function notificationFeed(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['unread_count' => 0, 'notifications' => []]);
        }

        $unreadCount = $user->unreadNotifications()->count();
        $recentNotifications = $user->notifications()->latest()->limit(15)->get()->map(function ($n) {
            return [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read' => $n->read_at !== null,
                'created_at_human' => $n->created_at?->diffForHumans() ?? 'Just now',
            ];
        });

        return response()->json([
            'unread_count' => $unreadCount,
            'notifications' => $recentNotifications,
        ]);
    }

    /**
     * API: Mark in-app notification as read.
     */
    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Mark all in-app notifications as read.
     */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
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

            $startingPoint = $firstLeg?->originLocation?->official_name ?? ($firstLeg?->origin ?? 'Cagayan de Oro Main Yard');
            $destination = $lastLeg?->destinationLocation?->official_name ?? ($lastLeg?->destination ?? ($itinerary->destination ?: 'Regional Depot'));
            $plannedDistance = max(0.0, (float) $itinerary->total_distance);

            $dispatch = $this->dispatchService->createDispatch([
                'vehicle_id' => $vehicle->id,
                'advanced_itinerary_id' => $itinerary->id,
                'trip_date' => $date->format('Y-m-d'),
                'driver_name' => $itinerary->driver_name ?: 'Assigned Fleet Driver',
                'starting_point' => $startingPoint,
                'origin_latitude' => $firstLeg?->originLocation?->latitude,
                'origin_longitude' => $firstLeg?->originLocation?->longitude,
                'destination' => $destination,
                'destination_latitude' => $lastLeg?->destinationLocation?->latitude,
                'destination_longitude' => $lastLeg?->destinationLocation?->longitude,
                'planned_route' => $itinerary->title ?: ($itinerary->destination ?: 'Approved Distribution Route'),
                'planned_distance_km' => $plannedDistance,
                'planned_departure_time' => $plannedDep,
                'grace_period_minutes' => 15,
                'notes' => "Auto-generated from Itinerary #{$itinerary->id}",
            ], $request->user()->id);

            // Initialize Draft Mileage Reconciliation
            $this->reconciliationService->createReconciliation($dispatch, [
                'planned_distance_km' => $dispatch->planned_distance_km > 0 ? $dispatch->planned_distance_km : null,
                'notes' => $dispatch->notes,
            ], $request->user()->id);

            $createdCount++;
        }

        return redirect()->route('fleet-dispatches.index')
            ->with('status', "Successfully generated {$createdCount} trip dispatches from approved itineraries.");
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
