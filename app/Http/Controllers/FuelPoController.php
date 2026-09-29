<?php

namespace App\Http\Controllers;

use App\Exports\FuelPoExport;
use App\Models\AdvancedItinerary;
use App\Models\Location;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FuelPoController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AdvancedItinerary::class);

        $query = $this->buildFilterQuery($request);

        // Precompute metrics before pagination
        $allMatching = (clone $query)->get();
        $totalFuelLiters = (float) $allMatching->sum(function (AdvancedItinerary $it) {
            return $it->fuel_liters ?? 0.0;
        });
        $totalDistance = (float) $allMatching->sum(function (AdvancedItinerary $it) {
            return $it->total_distance;
        });

        $metrics = [
            'total_count' => $allMatching->count(),
            'checked_count' => $allMatching->where('po_checked', true)->count(),
            'unchecked_count' => $allMatching->where('po_checked', false)->count(),
            'total_fuel_liters' => $totalFuelLiters,
            'total_distance' => $totalDistance,
        ];

        $records = $query->paginate(20)->withQueryString();
        $vehicles = Vehicle::orderBy('equipment_code')->get(['id', 'equipment_code', 'plate_number', 'model']);

        return view('fuel-po.index', compact('records', 'vehicles', 'metrics'));
    }

    public function create(): View
    {
        Gate::authorize('create', AdvancedItinerary::class);

        $vehicles = Vehicle::orderBy('equipment_code')->get();
        $locations = Location::where('status', Location::STATUS_ACTIVE)->with('aliases')->orderBy('official_name')->get();

        return view('fuel-po.create', compact('vehicles', 'locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', AdvancedItinerary::class);

        $validated = $request->validate([
            'itinerary_date' => ['required', 'date'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'destination' => ['nullable', 'string', 'max:255'],
            'total_distance' => ['nullable', 'numeric', 'min:0'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:DRAFT,FINALIZED'],
            'destinations' => ['nullable', 'array'],
            'destinations.*.name' => ['nullable', 'string', 'max:255'],
            'destinations.*.distance' => ['nullable', 'numeric', 'min:0'],
            'destinations.*.purpose' => ['nullable', 'string', 'max:255'],
            'legs' => ['nullable', 'array'],
            'legs.*.sort_order' => ['nullable', 'integer'],
            'legs.*.origin_location_id' => ['nullable', 'exists:locations,id'],
            'legs.*.starting_point_location_id' => ['nullable', 'exists:locations,id'],
            'legs.*.destination_location_id' => ['nullable', 'exists:locations,id'],
            'legs.*.distance_origin_to_start' => ['nullable', 'numeric', 'min:0'],
            'legs.*.distance_start_to_dest' => ['nullable', 'numeric', 'min:0'],
            'legs.*.total_distance' => ['nullable', 'numeric', 'min:0'],
            'legs.*.duration_origin_to_start_minutes' => ['nullable', 'numeric', 'min:0'],
            'legs.*.duration_start_to_dest_minutes' => ['nullable', 'numeric', 'min:0'],
            'legs.*.total_duration_minutes' => ['nullable', 'numeric', 'min:0'],
            'legs.*.routing_source' => ['nullable', 'string', 'max:30'],
            'legs.*.purpose' => ['nullable', 'string', 'max:255'],
        ]);

        $itinerary = DB::transaction(function () use ($validated) {
            $destRows = $validated['destinations'] ?? [];
            $legs = $validated['legs'] ?? [];
            $totalDistance = (float) ($validated['total_distance'] ?? 0);
            $totalDuration = 0;
            $destination = $validated['destination'] ?? null;

            if (! empty($destRows)) {
                $calcDist = 0.0;
                $rowNames = [];
                foreach ($destRows as $row) {
                    $rowDist = isset($row['distance']) && is_numeric($row['distance']) ? (float) $row['distance'] : 0.0;
                    $calcDist += $rowDist;
                    if (! empty($row['name'])) {
                        $rowNames[] = trim($row['name']);
                    }
                }
                if ($calcDist > 0) {
                    $totalDistance = $calcDist;
                }
                if (empty($destination) && ! empty($rowNames)) {
                    $destination = implode(' → ', $rowNames);
                }
            } elseif (! empty($legs)) {
                $calcDist = 0.0;
                foreach ($legs as $legData) {
                    $legDist = isset($legData['total_distance']) && is_numeric($legData['total_distance'])
                        ? (float) $legData['total_distance']
                        : (float) ($legData['distance_origin_to_start'] ?? 0) + (float) ($legData['distance_start_to_dest'] ?? 0);
                    $calcDist += $legDist;
                }
                if ($calcDist > 0) {
                    $totalDistance = $calcDist;
                }
            }

            if ($destination && mb_strlen($destination) > 255) {
                $destination = mb_substr($destination, 0, 252).'...';
            }

            $itinerary = AdvancedItinerary::create([
                'itinerary_date' => $validated['itinerary_date'],
                'vehicle_id' => $validated['vehicle_id'],
                'title' => $validated['title'] ?? null,
                'destination' => $destination,
                'total_distance' => $totalDistance > 0 ? $totalDistance : null,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? AdvancedItinerary::STATUS_FINALIZED,
                'created_by' => auth()->id(),
                'po_checked' => false,
            ]);

            if (! empty($destRows)) {
                foreach ($destRows as $index => $row) {
                    $rowDist = isset($row['distance']) && is_numeric($row['distance']) ? (float) $row['distance'] : null;
                    $rowName = ! empty($row['name']) ? trim($row['name']) : null;
                    $purpose = ! empty($row['purpose']) ? trim($row['purpose']) : null;

                    $matchedLocId = null;
                    if ($rowName) {
                        $matchedLocId = Location::where('official_name', $rowName)
                            ->orWhere('code', $rowName)
                            ->value('id');
                    }

                    $itinerary->legs()->create([
                        'sort_order' => $index,
                        'destination_location_id' => $matchedLocId,
                        'total_distance' => $rowDist,
                        'routing_source' => 'manual',
                        'purpose' => $purpose ?: $rowName,
                    ]);
                }
            } elseif (! empty($legs)) {
                foreach ($legs as $index => $legData) {
                    $legDist = isset($legData['total_distance']) && is_numeric($legData['total_distance'])
                        ? (float) $legData['total_distance']
                        : (float) ($legData['distance_origin_to_start'] ?? 0) + (float) ($legData['distance_start_to_dest'] ?? 0);

                    $legDuration = isset($legData['total_duration_minutes']) && is_numeric($legData['total_duration_minutes'])
                        ? (int) $legData['total_duration_minutes']
                        : null;

                    if ($legDuration !== null) {
                        $totalDuration += $legDuration;
                    }

                    $itinerary->legs()->create([
                        'sort_order' => $legData['sort_order'] ?? $index,
                        'origin_location_id' => $legData['origin_location_id'] ?? null,
                        'starting_point_location_id' => $legData['starting_point_location_id'] ?? null,
                        'destination_location_id' => $legData['destination_location_id'] ?? null,
                        'distance_origin_to_start' => $legData['distance_origin_to_start'] ?? null,
                        'distance_start_to_dest' => $legData['distance_start_to_dest'] ?? null,
                        'total_distance' => $legDist > 0 ? $legDist : null,
                        'duration_origin_to_start_minutes' => $legData['duration_origin_to_start_minutes'] ?? null,
                        'duration_start_to_dest_minutes' => $legData['duration_start_to_dest_minutes'] ?? null,
                        'total_duration_minutes' => $legDuration,
                        'routing_source' => $legData['routing_source'] ?? 'manual',
                        'purpose' => $legData['purpose'] ?? null,
                    ]);
                }
            }

            // Safe Fuel Liter calculation: Distance / Average Consumption (Threshold Rounding: >= 0.10 rounds up)
            $vehicle = $itinerary->vehicle;
            $avgConsumption = $vehicle?->average_fuel_consumption ?? $vehicle?->average_consumption;
            $fuelLiters = AdvancedItinerary::calculateFuelLiters($totalDistance, $avgConsumption ? (float) $avgConsumption : null);

            $itinerary->update([
                'total_duration_minutes' => $totalDuration > 0 ? $totalDuration : null,
                'fuel_liters_required' => $fuelLiters,
            ]);

            return $itinerary;
        });

        return redirect()
            ->route('fuel-po.index')
            ->with('success', "Fuel PO record #{$itinerary->id} created successfully.");
    }

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
            'poChecker',
        ]);

        return view('fuel-po.show', ['fuelPo' => $advancedItinerary]);
    }

    public function edit(AdvancedItinerary $advancedItinerary): View
    {
        Gate::authorize('update', $advancedItinerary);

        $advancedItinerary->load(['legs', 'vehicle']);
        $vehicles = Vehicle::orderBy('equipment_code')->get();
        $locations = Location::where('status', Location::STATUS_ACTIVE)->with('aliases')->orderBy('official_name')->get();

        return view('fuel-po.edit', ['fuelPo' => $advancedItinerary, 'vehicles' => $vehicles, 'locations' => $locations]);
    }

    public function update(Request $request, AdvancedItinerary $advancedItinerary): RedirectResponse
    {
        Gate::authorize('update', $advancedItinerary);

        $validated = $request->validate([
            'itinerary_date' => ['required', 'date'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'destination' => ['nullable', 'string', 'max:255'],
            'total_distance' => ['nullable', 'numeric', 'min:0'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:DRAFT,FINALIZED'],
            'legs' => ['nullable', 'array'],
            'legs.*.sort_order' => ['nullable', 'integer'],
            'legs.*.origin_location_id' => ['nullable', 'exists:locations,id'],
            'legs.*.starting_point_location_id' => ['nullable', 'exists:locations,id'],
            'legs.*.destination_location_id' => ['nullable', 'exists:locations,id'],
            'legs.*.distance_origin_to_start' => ['nullable', 'numeric', 'min:0'],
            'legs.*.distance_start_to_dest' => ['nullable', 'numeric', 'min:0'],
            'legs.*.total_distance' => ['nullable', 'numeric', 'min:0'],
            'legs.*.duration_origin_to_start_minutes' => ['nullable', 'numeric', 'min:0'],
            'legs.*.duration_start_to_dest_minutes' => ['nullable', 'numeric', 'min:0'],
            'legs.*.total_duration_minutes' => ['nullable', 'numeric', 'min:0'],
            'legs.*.routing_source' => ['nullable', 'string', 'max:30'],
            'legs.*.purpose' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($advancedItinerary, $validated) {
            $totalDistance = (float) ($validated['total_distance'] ?? 0);
            $totalDuration = 0;
            $legs = $validated['legs'] ?? [];

            if (! empty($legs)) {
                $calcDist = 0.0;
                foreach ($legs as $legData) {
                    $legDist = isset($legData['total_distance']) && is_numeric($legData['total_distance'])
                        ? (float) $legData['total_distance']
                        : (float) ($legData['distance_origin_to_start'] ?? 0) + (float) ($legData['distance_start_to_dest'] ?? 0);
                    $calcDist += $legDist;
                }
                if ($calcDist > 0) {
                    $totalDistance = $calcDist;
                }
            }

            $advancedItinerary->update([
                'itinerary_date' => $validated['itinerary_date'],
                'vehicle_id' => $validated['vehicle_id'] ?? null,
                'title' => $validated['title'] ?? null,
                'destination' => $validated['destination'] ?? null,
                'total_distance' => $totalDistance > 0 ? $totalDistance : null,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'],
                'updated_by' => auth()->id(),
            ]);

            if (! empty($legs)) {
                $advancedItinerary->legs()->delete();
                foreach ($legs as $index => $legData) {
                    $legDist = isset($legData['total_distance']) && is_numeric($legData['total_distance'])
                        ? (float) $legData['total_distance']
                        : (float) ($legData['distance_origin_to_start'] ?? 0) + (float) ($legData['distance_start_to_dest'] ?? 0);

                    $legDuration = isset($legData['total_duration_minutes']) && is_numeric($legData['total_duration_minutes'])
                        ? (int) $legData['total_duration_minutes']
                        : null;

                    if ($legDuration !== null) {
                        $totalDuration += $legDuration;
                    }

                    $advancedItinerary->legs()->create([
                        'sort_order' => $legData['sort_order'] ?? $index,
                        'origin_location_id' => $legData['origin_location_id'] ?? null,
                        'starting_point_location_id' => $legData['starting_point_location_id'] ?? null,
                        'destination_location_id' => $legData['destination_location_id'] ?? null,
                        'distance_origin_to_start' => $legData['distance_origin_to_start'] ?? null,
                        'distance_start_to_dest' => $legData['distance_start_to_dest'] ?? null,
                        'total_distance' => $legDist > 0 ? $legDist : null,
                        'duration_origin_to_start_minutes' => $legData['duration_origin_to_start_minutes'] ?? null,
                        'duration_start_to_dest_minutes' => $legData['duration_start_to_dest_minutes'] ?? null,
                        'total_duration_minutes' => $legDuration,
                        'routing_source' => $legData['routing_source'] ?? 'manual',
                        'purpose' => $legData['purpose'] ?? null,
                    ]);
                }
            }

            $vehicle = $advancedItinerary->vehicle;
            $avgConsumption = $vehicle?->average_fuel_consumption ?? $vehicle?->average_consumption;
            $fuelLiters = AdvancedItinerary::calculateFuelLiters($totalDistance, $avgConsumption ? (float) $avgConsumption : null);

            $advancedItinerary->update([
                'total_duration_minutes' => $totalDuration > 0 ? $totalDuration : null,
                'fuel_liters_required' => $fuelLiters,
            ]);
        });

        return redirect()
            ->route('fuel-po.show', $advancedItinerary)
            ->with('success', "Fuel PO record #{$advancedItinerary->id} updated successfully.");
    }

    public function destroy(AdvancedItinerary $advancedItinerary): RedirectResponse
    {
        Gate::authorize('delete', $advancedItinerary);

        $id = $advancedItinerary->id;
        $advancedItinerary->delete();

        return redirect()
            ->route('fuel-po.index')
            ->with('success', "Fuel PO record #{$id} deleted successfully.");
    }

    public function bulkChecklist(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('updateChecklist', AdvancedItinerary::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:advanced_itineraries,id'],
            'action' => ['required', 'string', 'in:check,uncheck,toggle'],
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];
        $count = count($ids);

        if ($action === 'check') {
            AdvancedItinerary::whereIn('id', $ids)->update([
                'po_checked' => true,
                'po_checked_at' => now(),
                'po_checked_by' => auth()->id(),
            ]);
            $message = "Successfully marked {$count} Fuel PO record(s) as CHECKED (☑).";
        } elseif ($action === 'uncheck') {
            AdvancedItinerary::whereIn('id', $ids)->update([
                'po_checked' => false,
                'po_checked_at' => null,
                'po_checked_by' => null,
            ]);
            $message = "Successfully marked {$count} Fuel PO record(s) as UNCHECKED (☐).";
        } else {
            $records = AdvancedItinerary::whereIn('id', $ids)->get();
            foreach ($records as $record) {
                $record->togglePoCheck(auth()->id());
            }
            $message = "Successfully toggled checklist state for {$count} Fuel PO record(s).";
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $count,
                'action' => $action,
            ]);
        }

        return back()->with('success', $message);
    }

    public function toggleChecklist(Request $request, AdvancedItinerary $advancedItinerary): JsonResponse|RedirectResponse
    {
        Gate::authorize('updateChecklist', $advancedItinerary);

        $isChecked = $advancedItinerary->togglePoCheck(auth()->id());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'id' => $advancedItinerary->id,
                'po_checked' => $isChecked,
                'symbol' => $isChecked ? '☑' : '☐',
                'label' => $isChecked ? 'CHECKED' : 'UNCHECKED',
                'checked_at' => $isChecked && $advancedItinerary->po_checked_at ? $advancedItinerary->po_checked_at->format('M d, Y h:i A') : null,
                'checker_name' => $isChecked && $advancedItinerary->poChecker ? $advancedItinerary->poChecker->name : null,
                'message' => $isChecked ? 'PO Checklist marked as checked (☐ → ☑).' : 'PO Checklist unchecked.',
            ]);
        }

        $message = $isChecked
            ? "Fuel PO #{$advancedItinerary->id} marked as checked (☐ → ☑)."
            : "Fuel PO #{$advancedItinerary->id} unchecked.";

        return back()->with('success', $message);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        Gate::authorize('export', AdvancedItinerary::class);

        $query = $this->buildFilterQuery($request);
        $filename = 'fuel-po-checklist-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new FuelPoExport($query), $filename);
    }

    public function exportPdf(Request $request): Response
    {
        Gate::authorize('export', AdvancedItinerary::class);

        $query = $this->buildFilterQuery($request);
        $records = $query->get();

        $totalFuelLiters = (float) $records->sum(function (AdvancedItinerary $it) {
            return $it->fuel_liters ?? 0.0;
        });
        $totalDistance = (float) $records->sum(function (AdvancedItinerary $it) {
            return $it->total_distance;
        });

        $metrics = [
            'total_count' => $records->count(),
            'checked_count' => $records->where('po_checked', true)->count(),
            'unchecked_count' => $records->where('po_checked', false)->count(),
            'total_fuel_liters' => $totalFuelLiters,
            'total_distance' => $totalDistance,
        ];

        $pdf = Pdf::loadView('pdf.fuel-po-report', [
            'records' => $records,
            'metrics' => $metrics,
            'generatedAt' => now(),
            'generatedBy' => auth()->user()?->name ?? 'System',
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf->stream('fuel-po-checklist-'.now()->format('Y-m-d').'.pdf');
    }

    public function downloadSinglePdf(AdvancedItinerary $advancedItinerary): Response
    {
        Gate::authorize('view', $advancedItinerary);

        $advancedItinerary->load([
            'vehicle',
            'legs.origin',
            'legs.startingPoint',
            'legs.destination',
            'creator',
            'poChecker',
        ]);

        $pdf = Pdf::loadView('pdf.fuel-po-single', [
            'fuelPo' => $advancedItinerary,
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf->stream("fuel-po-record-{$advancedItinerary->id}.pdf");
    }

    /**
     * Build the query for index, excel, and pdf exports.
     *
     * @return Builder<AdvancedItinerary>
     */
    protected function buildFilterQuery(Request $request): Builder
    {
        $query = AdvancedItinerary::with([
            'vehicle',
            'legs.destination',
            'creator',
            'poChecker',
        ])->orderByDesc('itinerary_date')->orderByDesc('id');

        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', function (Builder $vq) use ($search) {
                        $vq->where('equipment_code', 'like', "%{$search}%")
                            ->orWhere('plate_number', 'like', "%{$search}%")
                            ->orWhere('operator_driver', 'like', "%{$search}%")
                            ->orWhere('user', 'like', "%{$search}%")
                            ->orWhere('project_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('legs.destination', function (Builder $dq) use ($search) {
                        $dq->where('official_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($vehicleId = $request->input('vehicle_id')) {
            $query->where('vehicle_id', $vehicleId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($poStatus = $request->input('po_status')) {
            if ($poStatus === 'checked') {
                $query->where('po_checked', true);
            } elseif ($poStatus === 'unchecked') {
                $query->where('po_checked', false);
            }
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('itinerary_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('itinerary_date', '<=', $dateTo);
        }

        return $query;
    }
}
