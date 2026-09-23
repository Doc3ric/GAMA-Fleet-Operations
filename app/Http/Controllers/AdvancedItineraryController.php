<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdvancedItineraryRequest;
use App\Http\Requests\UpdateAdvancedItineraryRequest;
use App\Models\AdvancedItinerary;
use App\Models\AdvancedItineraryLeg;
use App\Models\Location;
use App\Models\Vehicle;
use App\Services\RoutingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdvancedItineraryController extends Controller
{
    public function __construct(
        protected RoutingService $routingService
    ) {}

    /**
     * Display a listing of advanced itineraries.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AdvancedItinerary::class);

        $query = AdvancedItinerary::with([
            'vehicle',
            'legs.origin',
            'legs.startingPoint',
            'legs.destination',
            'creator',
        ])->latest('itinerary_date');

        if ($request->filled('start_date')) {
            $query->whereDate('itinerary_date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('itinerary_date', '<=', $request->input('end_date'));
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $itineraries = $query->paginate(15)->withQueryString();
        $vehicles = Vehicle::orderBy('equipment_code')->get();

        return view('advanced-itineraries.index', compact('itineraries', 'vehicles'));
    }

    /**
     * Show the form for creating a new advanced itinerary.
     */
    public function create(): View
    {
        Gate::authorize('create', AdvancedItinerary::class);

        $locations = Location::where('status', Location::STATUS_ACTIVE)->orderBy('official_name')->get();
        $vehicles = Vehicle::orderBy('equipment_code')->get();

        return view('advanced-itineraries.create', compact('locations', 'vehicles'));
    }

    /**
     * Store a newly created advanced itinerary in storage.
     */
    public function store(StoreAdvancedItineraryRequest $request): RedirectResponse
    {
        Gate::authorize('create', AdvancedItinerary::class);

        $itinerary = DB::transaction(function () use ($request) {
            $itinerary = AdvancedItinerary::create([
                'vehicle_id' => $request->input('vehicle_id'),
                'itinerary_date' => $request->input('itinerary_date'),
                'title' => $request->input('title'),
                'notes' => $request->input('notes'),
                'status' => $request->input('status'),
                'created_by' => $request->user()->id,
                'updated_by' => null,
            ]);

            foreach ($request->input('legs', []) as $index => $legData) {
                $this->createLeg($itinerary, $legData, $index);
            }

            return $itinerary;
        });

        return redirect()->route('advanced-itineraries.show', $itinerary)->with('success', 'Advanced itinerary created successfully.');
    }

    /**
     * Display the specified advanced itinerary.
     */
    public function show(AdvancedItinerary $advancedItinerary): View
    {
        Gate::authorize('view', $advancedItinerary);

        $advancedItinerary->load([
            'vehicle',
            'legs.origin',
            'legs.startingPoint',
            'legs.destination',
            'creator',
            'updater',
        ]);

        return view('advanced-itineraries.show', compact('advancedItinerary'));
    }

    /**
     * Show the form for editing the specified advanced itinerary.
     */
    public function edit(AdvancedItinerary $advancedItinerary): View
    {
        Gate::authorize('update', $advancedItinerary);

        $locations = Location::where('status', Location::STATUS_ACTIVE)->orderBy('official_name')->get();
        $vehicles = Vehicle::orderBy('equipment_code')->get();
        $advancedItinerary->load([
            'vehicle',
            'legs.origin',
            'legs.startingPoint',
            'legs.destination',
        ]);

        return view('advanced-itineraries.edit', compact('advancedItinerary', 'locations', 'vehicles'));
    }

    /**
     * Update the specified advanced itinerary in storage.
     */
    public function update(UpdateAdvancedItineraryRequest $request, AdvancedItinerary $advancedItinerary): RedirectResponse
    {
        Gate::authorize('update', $advancedItinerary);

        DB::transaction(function () use ($request, $advancedItinerary) {
            $advancedItinerary->update([
                'vehicle_id' => $request->input('vehicle_id'),
                'itinerary_date' => $request->input('itinerary_date'),
                'title' => $request->input('title'),
                'notes' => $request->input('notes'),
                'status' => $request->input('status'),
                'updated_by' => $request->user()->id,
            ]);

            $advancedItinerary->legs()->delete();

            foreach ($request->input('legs', []) as $index => $legData) {
                $this->createLeg($advancedItinerary, $legData, $index);
            }
        });

        return redirect()->route('advanced-itineraries.show', $advancedItinerary)->with('success', 'Advanced itinerary updated successfully.');
    }

    /**
     * Remove the specified advanced itinerary from storage.
     */
    public function destroy(AdvancedItinerary $advancedItinerary): RedirectResponse
    {
        Gate::authorize('delete', $advancedItinerary);

        $advancedItinerary->delete();

        return redirect()->route('advanced-itineraries.index')->with('success', 'Advanced itinerary deleted successfully.');
    }

    /**
     * Recalculate distances for all legs in the itinerary using OSRM routing.
     */
    public function recalculate(AdvancedItinerary $advancedItinerary): RedirectResponse
    {
        Gate::authorize('recalculate', $advancedItinerary);

        $advancedItinerary->load(['legs.origin', 'legs.startingPoint', 'legs.destination']);

        foreach ($advancedItinerary->legs as $leg) {
            if ($leg->origin && $leg->startingPoint && $leg->destination) {
                $calc = $this->routingService->calculateLegDistances(
                    $leg->origin,
                    $leg->startingPoint,
                    $leg->destination
                );

                $leg->update([
                    'distance_origin_to_start' => $calc['origin_to_start'],
                    'distance_start_to_dest' => $calc['start_to_dest'],
                    'total_distance' => $calc['total'],
                    'routing_source' => $calc['source'],
                ]);
            }
        }

        return back()->with('success', 'Itinerary leg distances recalculated successfully.');
    }

    /**
     * Export the advanced itinerary to PDF.
     */
    public function exportPdf(AdvancedItinerary $advancedItinerary): Response
    {
        Gate::authorize('view', $advancedItinerary);

        $advancedItinerary->load([
            'vehicle',
            'legs.origin',
            'legs.startingPoint',
            'legs.destination',
            'creator',
            'updater',
        ]);

        $pdf = Pdf::loadView('pdf.advanced-itinerary-report', compact('advancedItinerary'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true);

        $filename = 'Advanced-Itinerary-'.$advancedItinerary->id.'-'.$advancedItinerary->itinerary_date->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Create or calculate and create an itinerary leg.
     *
     * @param  array<string, mixed>  $legData
     */
    protected function createLeg(AdvancedItinerary $itinerary, array $legData, int $index): AdvancedItineraryLeg
    {
        $originId = $legData['origin_location_id'];
        $startingPointId = $legData['starting_point_location_id'];
        $destId = $legData['destination_location_id'];

        $d1 = isset($legData['distance_origin_to_start']) && $legData['distance_origin_to_start'] !== ''
            ? (float) $legData['distance_origin_to_start']
            : null;

        $d2 = isset($legData['distance_start_to_dest']) && $legData['distance_start_to_dest'] !== ''
            ? (float) $legData['distance_start_to_dest']
            : null;

        $total = isset($legData['total_distance']) && $legData['total_distance'] !== ''
            ? (float) $legData['total_distance']
            : null;

        $source = $legData['routing_source'] ?? null;

        // If distances are missing/empty, calculate automatically via RoutingService
        if ($d1 === null || $d2 === null) {
            $origin = Location::find($originId);
            $startingPoint = Location::find($startingPointId);
            $destination = Location::find($destId);

            if ($origin && $startingPoint && $destination) {
                $calc = $this->routingService->calculateLegDistances($origin, $startingPoint, $destination);
                $d1 = $calc['origin_to_start'];
                $d2 = $calc['start_to_dest'];
                $total = $calc['total'];
                $source = $calc['source'];
            }
        } else {
            if ($total === null) {
                $total = round($d1 + $d2, 2);
            }
            if (empty($source)) {
                $source = RoutingService::SOURCE_MANUAL;
            }
        }

        return $itinerary->legs()->create([
            'sort_order' => $legData['sort_order'] ?? $index,
            'origin_location_id' => $originId,
            'starting_point_location_id' => $startingPointId,
            'destination_location_id' => $destId,
            'distance_origin_to_start' => $d1,
            'distance_start_to_dest' => $d2,
            'total_distance' => $total,
            'routing_source' => $source,
            'purpose' => $legData['purpose'] ?? null,
        ]);
    }
}
