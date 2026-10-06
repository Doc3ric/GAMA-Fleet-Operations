<?php

namespace App\Http\Controllers;

use App\Exports\FuelPoExport;
use App\Models\AdvancedItinerary;
use App\Models\Location;
use App\Models\Vehicle;
use App\Services\WeeklyItineraryImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FuelPoController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AdvancedItinerary::class);

        [$preloadedImport, $preloadedImportError] = $this->resolveBridgedImport($request);

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

        return view('fuel-po.index', compact('records', 'vehicles', 'metrics', 'preloadedImport', 'preloadedImportError'));
    }

    public function create(): View
    {
        Gate::authorize('create', AdvancedItinerary::class);

        $vehicles = Vehicle::orderBy('equipment_code')->get();
        $locations = Location::where('status', Location::STATUS_ACTIVE)->with('aliases')->orderBy('official_name')->get();

        return view('fuel-po.create', compact('vehicles', 'locations'));
    }

    /**
     * Resolve target vehicle ID from either registered dropdown selection or manual specification.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveVehicleId(array $data, ?string $driverName = null): ?int
    {
        $vehicleMode = $data['vehicle_mode'] ?? 'dropdown';
        $customEquipmentCode = trim((string) ($data['custom_equipment_code'] ?? ''));

        if ($vehicleMode === 'manual' || ($data['vehicle_id'] ?? null) === 'manual' || ! empty($customEquipmentCode)) {
            if (empty($customEquipmentCode)) {
                return null;
            }

            $customAvg = isset($data['custom_average_consumption']) && is_numeric($data['custom_average_consumption'])
                ? (float) $data['custom_average_consumption']
                : null;
            $customPlate = trim((string) ($data['custom_plate_number'] ?? ''));
            $customModel = trim((string) ($data['custom_model'] ?? ''));
            $customUser = trim((string) ($data['custom_user'] ?? ''));
            $customProject = trim((string) ($data['custom_project_code'] ?? ''));

            $vehicle = Vehicle::firstOrCreate(
                ['equipment_code' => $customEquipmentCode],
                [
                    'plate_number' => $customPlate ?: null,
                    'model' => $customModel ?: null,
                    'operator_driver' => $driverName ?: null,
                    'user' => $customUser ?: null,
                    'project_code' => $customProject ?: null,
                    'average_fuel_consumption' => $customAvg,
                    'gps_status' => 'NO',
                    'created_by' => auth()->id(),
                ]
            );

            // Update attributes if existing vehicle was missing them or user provided new values
            $updates = [];
            if ($customAvg !== null && ($vehicle->average_fuel_consumption === null || (float) $vehicle->average_fuel_consumption !== $customAvg)) {
                $updates['average_fuel_consumption'] = $customAvg;
            }
            if (! empty($customPlate) && empty($vehicle->plate_number)) {
                $updates['plate_number'] = $customPlate;
            }
            if (! empty($customModel) && empty($vehicle->model)) {
                $updates['model'] = $customModel;
            }
            if (! empty($customUser) && empty($vehicle->user)) {
                $updates['user'] = $customUser;
            }
            if (! empty($customProject) && empty($vehicle->project_code)) {
                $updates['project_code'] = $customProject;
            }
            if (! empty($updates)) {
                $vehicle->update($updates);
            }

            return $vehicle->id;
        }

        if (isset($data['vehicle_id']) && is_numeric($data['vehicle_id'])) {
            $vehicle = Vehicle::find((int) $data['vehicle_id']);
            if ($vehicle) {
                $customAvg = isset($data['custom_average_consumption']) && is_numeric($data['custom_average_consumption'])
                    ? (float) $data['custom_average_consumption']
                    : null;
                if ($customAvg !== null && (float) $customAvg > 0) {
                    $existingAvg = $vehicle->average_fuel_consumption !== null ? round((float) $vehicle->average_fuel_consumption, 2) : null;
                    if ($existingAvg === null || $existingAvg !== round($customAvg, 2)) {
                        $vehicle->update(['average_fuel_consumption' => $customAvg]);
                    }
                }
            }

            return (int) $data['vehicle_id'];
        }

        return null;
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', AdvancedItinerary::class);

        $validated = $request->validate([
            'itinerary_date' => ['required', 'date'],
            'vehicle_mode' => ['nullable', 'string', 'in:dropdown,manual'],
            'vehicle_id' => ['nullable'],
            'custom_equipment_code' => ['nullable', 'string', 'max:20'],
            'custom_model' => ['nullable', 'string', 'max:100'],
            'custom_plate_number' => ['nullable', 'string', 'max:50'],
            'custom_user' => ['nullable', 'string', 'max:100'],
            'custom_project_code' => ['nullable', 'string', 'max:50'],
            'custom_average_consumption' => ['nullable', 'numeric', 'min:0.01'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:65535'],
            'total_distance' => ['nullable', 'numeric', 'min:0'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:DRAFT,FINALIZED'],
            'calculation_method' => ['nullable', 'string', 'in:divide,multiply'],
            'destinations' => ['nullable', 'array'],
            'destinations.*.name' => ['nullable', 'string', 'max:255'],
            'destinations.*.start_odo' => ['nullable', 'numeric', 'min:0'],
            'destinations.*.end_odo' => ['nullable', 'numeric', 'min:0'],
            'destinations.*.distance' => ['nullable', 'numeric', 'min:0'],
            'destinations.*.purpose' => ['nullable', 'string', 'max:255'],
            'start_odo' => ['nullable', 'numeric', 'min:0'],
            'end_odo' => ['nullable', 'numeric', 'min:0'],
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

        $targetVehicleId = $this->resolveVehicleId($validated, $validated['driver_name'] ?? null);

        if (! $targetVehicleId) {
            return back()->withInput()->withErrors([
                'vehicle_id' => 'Please select a registered vehicle or specify a manual Equipment Code.',
                'custom_equipment_code' => 'Please enter the Vehicle / Equipment Code.',
            ]);
        }

        $itinerary = DB::transaction(function () use ($validated, $targetVehicleId) {
            $destRows = $validated['destinations'] ?? [];
            $legs = $validated['legs'] ?? [];
            $totalDistance = (float) ($validated['total_distance'] ?? 0);
            $totalDuration = 0;
            $destination = $validated['destination'] ?? null;

            $firstStartOdo = null;
            $lastEndOdo = null;

            if (! empty($destRows)) {
                $calcDist = 0.0;
                $rowNames = [];
                foreach ($destRows as $row) {
                    $rowDist = isset($row['distance']) && is_numeric($row['distance']) ? (float) $row['distance'] : 0.0;
                    $calcDist += $rowDist;
                    if (! empty($row['name'])) {
                        $rowNames[] = trim($row['name']);
                    }
                    if ($firstStartOdo === null && isset($row['start_odo']) && is_numeric($row['start_odo'])) {
                        $firstStartOdo = (float) $row['start_odo'];
                    }
                    if (isset($row['end_odo']) && is_numeric($row['end_odo'])) {
                        $lastEndOdo = (float) $row['end_odo'];
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

            if ($destination && mb_strlen($destination) > 65000) {
                $destination = mb_substr($destination, 0, 64997).'...';
            }

            $vehicle = Vehicle::find($targetVehicleId);
            $calculationMethod = $validated['calculation_method'] ?? null;
            if (empty($calculationMethod)) {
                $calculationMethod = AdvancedItinerary::detectCalculationMethod($vehicle?->equipment_code ?? $validated['custom_equipment_code'] ?? null);
            }

            $itinerary = AdvancedItinerary::create([
                'itinerary_date' => $validated['itinerary_date'],
                'vehicle_id' => $targetVehicleId,
                'driver_name' => $validated['driver_name'] ?? null,
                'title' => $validated['title'] ?? null,
                'destination' => $destination,
                'start_odo' => $firstStartOdo ?? (isset($validated['start_odo']) && is_numeric($validated['start_odo']) ? (float) $validated['start_odo'] : null),
                'end_odo' => $lastEndOdo ?? (isset($validated['end_odo']) && is_numeric($validated['end_odo']) ? (float) $validated['end_odo'] : null),
                'total_distance' => $totalDistance > 0 ? $totalDistance : null,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? AdvancedItinerary::STATUS_FINALIZED,
                'calculation_method' => $calculationMethod,
                'created_by' => auth()->id(),
                'po_checked' => false,
            ]);

            if (! empty($destRows)) {
                foreach ($destRows as $index => $row) {
                    $rowDist = isset($row['distance']) && is_numeric($row['distance']) ? (float) $row['distance'] : null;
                    $rowName = ! empty($row['name']) ? trim($row['name']) : null;
                    $purpose = ! empty($row['purpose']) ? trim($row['purpose']) : null;
                    $rowStartOdo = isset($row['start_odo']) && is_numeric($row['start_odo']) ? (float) $row['start_odo'] : null;
                    $rowEndOdo = isset($row['end_odo']) && is_numeric($row['end_odo']) ? (float) $row['end_odo'] : null;

                    $matchedLocId = null;
                    if ($rowName) {
                        $matchedLocId = Location::where('official_name', $rowName)
                            ->orWhere('code', $rowName)
                            ->value('id');
                    }

                    $itinerary->legs()->create([
                        'sort_order' => $index,
                        'destination_location_id' => $matchedLocId,
                        'start_odo' => $rowStartOdo,
                        'end_odo' => $rowEndOdo,
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
            $vehicle = $itinerary->vehicle ?: $vehicle;
            $customAvg = isset($validated['custom_average_consumption']) && is_numeric($validated['custom_average_consumption'])
                ? (float) $validated['custom_average_consumption']
                : null;
            $avgConsumption = $customAvg ?? $vehicle?->average_fuel_consumption ?? $vehicle?->average_consumption;
            $fuelLiters = AdvancedItinerary::calculateFuelLiters($totalDistance, $avgConsumption ? (float) $avgConsumption : null, $calculationMethod);

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

        $advancedItinerary->load(['legs.destination', 'vehicle']);
        $vehicles = Vehicle::orderBy('equipment_code')->get();
        $locations = Location::where('status', Location::STATUS_ACTIVE)->with('aliases')->orderBy('official_name')->get();

        return view('fuel-po.edit', ['fuelPo' => $advancedItinerary, 'vehicles' => $vehicles, 'locations' => $locations]);
    }

    public function update(Request $request, AdvancedItinerary $advancedItinerary): RedirectResponse
    {
        Gate::authorize('update', $advancedItinerary);

        $validated = $request->validate([
            'itinerary_date' => ['required', 'date'],
            'vehicle_mode' => ['nullable', 'string', 'in:dropdown,manual'],
            'vehicle_id' => ['nullable'],
            'custom_equipment_code' => ['nullable', 'string', 'max:20'],
            'custom_model' => ['nullable', 'string', 'max:100'],
            'custom_plate_number' => ['nullable', 'string', 'max:50'],
            'custom_user' => ['nullable', 'string', 'max:100'],
            'custom_project_code' => ['nullable', 'string', 'max:50'],
            'custom_average_consumption' => ['nullable', 'numeric', 'min:0.01'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:65535'],
            'total_distance' => ['nullable', 'numeric', 'min:0'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:DRAFT,FINALIZED'],
            'calculation_method' => ['nullable', 'string', 'in:divide,multiply'],
            'destinations' => ['nullable', 'array'],
            'destinations.*.name' => ['nullable', 'string', 'max:255'],
            'destinations.*.start_odo' => ['nullable', 'numeric', 'min:0'],
            'destinations.*.end_odo' => ['nullable', 'numeric', 'min:0'],
            'destinations.*.distance' => ['nullable', 'numeric', 'min:0'],
            'destinations.*.purpose' => ['nullable', 'string', 'max:255'],
            'start_odo' => ['nullable', 'numeric', 'min:0'],
            'end_odo' => ['nullable', 'numeric', 'min:0'],
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

        $targetVehicleId = $this->resolveVehicleId($validated, $validated['driver_name'] ?? null);

        if (! $targetVehicleId) {
            $targetVehicleId = $advancedItinerary->vehicle_id;
            if ($targetVehicleId) {
                $vehicle = Vehicle::find($targetVehicleId);
                $customAvg = isset($validated['custom_average_consumption']) && is_numeric($validated['custom_average_consumption'])
                    ? (float) $validated['custom_average_consumption']
                    : null;
                if ($vehicle && $customAvg !== null && (float) $customAvg > 0) {
                    $existingAvg = $vehicle->average_fuel_consumption !== null ? round((float) $vehicle->average_fuel_consumption, 2) : null;
                    if ($existingAvg === null || $existingAvg !== round($customAvg, 2)) {
                        $vehicle->update(['average_fuel_consumption' => $customAvg]);
                    }
                }
            }
        }

        if (! $targetVehicleId) {
            return back()->withInput()->withErrors([
                'vehicle_id' => 'Please select a registered vehicle or specify a manual Equipment Code.',
                'custom_equipment_code' => 'Please enter the Vehicle / Equipment Code.',
            ]);
        }

        DB::transaction(function () use ($advancedItinerary, $validated, $targetVehicleId) {
            $destRows = $validated['destinations'] ?? [];
            $legs = $validated['legs'] ?? [];
            $totalDistance = (float) ($validated['total_distance'] ?? 0);
            $totalDuration = 0;
            $destination = $validated['destination'] ?? null;

            $firstStartOdo = null;
            $lastEndOdo = null;

            if (! empty($destRows)) {
                $calcDist = 0.0;
                $rowNames = [];
                foreach ($destRows as $row) {
                    $rowDist = isset($row['distance']) && is_numeric($row['distance']) ? (float) $row['distance'] : 0.0;
                    $calcDist += $rowDist;
                    if (! empty($row['name'])) {
                        $rowNames[] = trim($row['name']);
                    }
                    if ($firstStartOdo === null && isset($row['start_odo']) && is_numeric($row['start_odo'])) {
                        $firstStartOdo = (float) $row['start_odo'];
                    }
                    if (isset($row['end_odo']) && is_numeric($row['end_odo'])) {
                        $lastEndOdo = (float) $row['end_odo'];
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

            if ($destination && mb_strlen($destination) > 65000) {
                $destination = mb_substr($destination, 0, 64997).'...';
            }

            $vehicle = Vehicle::find($targetVehicleId);
            $calculationMethod = $validated['calculation_method'] ?? null;
            if (empty($calculationMethod)) {
                $calculationMethod = AdvancedItinerary::detectCalculationMethod($vehicle?->equipment_code ?? $validated['custom_equipment_code'] ?? null);
            }

            $advancedItinerary->update([
                'itinerary_date' => $validated['itinerary_date'],
                'vehicle_id' => $targetVehicleId,
                'driver_name' => $validated['driver_name'] ?? null,
                'title' => $validated['title'] ?? null,
                'destination' => $destination,
                'start_odo' => $firstStartOdo ?? (isset($validated['start_odo']) && is_numeric($validated['start_odo']) ? (float) $validated['start_odo'] : null),
                'end_odo' => $lastEndOdo ?? (isset($validated['end_odo']) && is_numeric($validated['end_odo']) ? (float) $validated['end_odo'] : null),
                'total_distance' => $totalDistance > 0 ? $totalDistance : null,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'],
                'calculation_method' => $calculationMethod,
                'updated_by' => auth()->id(),
            ]);

            if (! empty($destRows)) {
                $advancedItinerary->legs()->delete();
                foreach ($destRows as $index => $row) {
                    $rowDist = isset($row['distance']) && is_numeric($row['distance']) ? (float) $row['distance'] : null;
                    $rowName = ! empty($row['name']) ? trim($row['name']) : null;
                    $purpose = ! empty($row['purpose']) ? trim($row['purpose']) : null;
                    $rowStartOdo = isset($row['start_odo']) && is_numeric($row['start_odo']) ? (float) $row['start_odo'] : null;
                    $rowEndOdo = isset($row['end_odo']) && is_numeric($row['end_odo']) ? (float) $row['end_odo'] : null;

                    $matchedLocId = null;
                    if ($rowName) {
                        $matchedLocId = Location::where('official_name', $rowName)
                            ->orWhere('code', $rowName)
                            ->value('id');
                    }

                    $advancedItinerary->legs()->create([
                        'sort_order' => $index,
                        'destination_location_id' => $matchedLocId,
                        'start_odo' => $rowStartOdo,
                        'end_odo' => $rowEndOdo,
                        'total_distance' => $rowDist,
                        'routing_source' => 'manual',
                        'purpose' => $purpose ?: $rowName,
                    ]);
                }
            } elseif (! empty($legs)) {
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

            $advancedItinerary->load('vehicle');
            $vehicle = $advancedItinerary->vehicle ?: $vehicle;
            $customAvg = isset($validated['custom_average_consumption']) && is_numeric($validated['custom_average_consumption'])
                ? (float) $validated['custom_average_consumption']
                : null;
            $avgConsumption = $customAvg ?? $vehicle?->average_fuel_consumption ?? $vehicle?->average_consumption;
            $fuelLiters = AdvancedItinerary::calculateFuelLiters($totalDistance, $avgConsumption ? (float) $avgConsumption : null, $calculationMethod);

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

    public function bulkDelete(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', AdvancedItinerary::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:advanced_itineraries,id'],
        ]);

        $ids = $validated['ids'];
        $count = count($ids);

        AdvancedItinerary::whereIn('id', $ids)->delete();

        $message = "Successfully deleted {$count} Fuel PO record(s).";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $count,
            ]);
        }

        return redirect()
            ->route('fuel-po.index')
            ->with('success', $message);
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

        if ($ids = $request->input('ids')) {
            $idArray = is_array($ids) ? $ids : explode(',', (string) $ids);
            $idArray = array_values(array_filter(array_map('intval', $idArray)));
            if (! empty($idArray)) {
                $query->whereIn('advanced_itineraries.id', $idArray);
            }
        }

        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('advanced_itineraries.driver_name', 'like', "%{$search}%")
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

    /**
     * Resolve preloaded import preview if bridging from Excel Viewer.
     *
     * @return array{0: ?array<string, mixed>, 1: ?string}
     */
    protected function resolveBridgedImport(Request $request): array
    {
        if (! $request->filled('bridge_file_id')) {
            return [null, null];
        }

        $bridgeFileId = (string) $request->query('bridge_file_id');
        $useEdited = (bool) $request->query('use_edited', 0);

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $bridgeFileId)) {
            return [null, 'Invalid spreadsheet identifier format.'];
        }

        $metaPath = "excel-viewer/{$bridgeFileId}.json";
        if (! Storage::disk('local')->exists($metaPath)) {
            return [null, 'Spreadsheet session or metadata file not found.'];
        }

        $metadata = json_decode((string) Storage::disk('local')->get($metaPath), true) ?: [];
        $isOwnerOrAdmin = (isset($metadata['uploaded_by']) && (int) $metadata['uploaded_by'] === (int) auth()->id())
            || (bool) auth()->user()?->isAdmin();

        if (! $isOwnerOrAdmin) {
            return [null, 'Unauthorized access to the requested spreadsheet.'];
        }

        $ext = $metadata['extension'] ?? 'xlsx';
        $sourceFile = "excel-viewer/{$bridgeFileId}.{$ext}";
        $originalName = $metadata['original_name'] ?? 'Weekly-Itinerary-Report.xlsx';

        if ($useEdited && ! empty($metadata['has_edited_file']) && Storage::disk('local')->exists("excel-viewer/{$bridgeFileId}_edited.xlsx")) {
            $sourceFile = "excel-viewer/{$bridgeFileId}_edited.xlsx";
            $ext = 'xlsx';
            $originalName = $metadata['edited_download_name'] ?? ($metadata['original_name'] ?? 'Weekly-Itinerary-Report.xlsx');
        }

        if (! Storage::disk('local')->exists($sourceFile)) {
            return [null, 'The selected spreadsheet file could not be found in storage.'];
        }

        $token = (string) Str::uuid();
        $destRel = "itinerary-imports/{$token}.{$ext}";

        try {
            Storage::disk('local')->copy($sourceFile, $destRel);
            $fullPath = Storage::disk('local')->path($destRel);

            /** @var WeeklyItineraryImportService $importService */
            $importService = app(WeeklyItineraryImportService::class);
            $parsed = $importService->parse($fullPath, $originalName);

            $preloadedImport = [
                'success' => true,
                'import_token' => $token,
                'extension' => $ext,
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
                'is_bridged' => true,
            ];

            return [$preloadedImport, null];
        } catch (Throwable $e) {
            Storage::disk('local')->delete($destRel);

            return [null, 'Could not parse bridged spreadsheet as Weekly Itinerary Report: '.$e->getMessage()];
        }
    }
}
