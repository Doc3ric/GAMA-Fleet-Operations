<?php

namespace App\Http\Controllers;

use App\Exports\LocationExport;
use App\Exports\LocationTemplateExport;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Models\LocationAlias;
use App\Services\GeocodingService;
use App\Services\LocationImportService;
use App\Services\LocationRecognitionService;
use App\Services\RoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LocationController extends Controller
{
    public function __construct(
        protected LocationRecognitionService $recognitionService,
        protected LocationImportService $importService,
        protected GeocodingService $geocodingService,
        protected RoutingService $routingService
    ) {}

    /**
     * Search for places, landmarks, or addresses using commercial GIS and local directory.
     */
    public function searchPlaces(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Location::class);

        $query = (string) $request->input('q', '');
        if (trim($query) === '') {
            return response()->json([
                'success' => true,
                'results' => [],
            ]);
        }

        $results = $this->geocodingService->search($query);

        return response()->json([
            'success' => true,
            'query' => $query,
            'results' => $results,
        ]);
    }

    /**
     * Resolve pasted coordinates or Google Maps URL to latitude and longitude.
     */
    public function resolveCoordinates(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Location::class);

        $input = (string) $request->input('input', '');
        $coords = $this->geocodingService->parseCoordinates($input);

        if (! $coords && $this->geocodingService->isGoogleMapsUrl($input)) {
            $coords = $this->geocodingService->resolveGoogleUrl($input);
        }

        if (! $coords) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to extract valid geographic coordinates from input.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'latitude' => $coords['latitude'],
            'longitude' => $coords['longitude'],
        ]);
    }

    /**
     * Display a listing of locations.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Location::class);

        $query = Location::with(['aliases'])
            ->orderBy('official_name');

        if ($search = $request->input('search')) {
            $query->search($search);
        }

        if ($type = $request->input('type')) {
            $query->ofType($type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $locations = $query->paginate(15)->withQueryString();

        $statistics = [
            'total' => Location::count(),
            'active' => Location::active()->count(),
            'inactive' => Location::inactive()->count(),
            'types_count' => Location::distinct('type')->count('type'),
            'aliases_count' => LocationAlias::count(),
        ];

        return view('locations.index', [
            'locations' => $locations,
            'statistics' => $statistics,
            'types' => Location::TYPES,
            'statuses' => Location::STATUSES,
            'filters' => $request->only(['search', 'type', 'status']),
        ]);
    }

    /**
     * Show form to create a new location.
     */
    public function create(): View
    {
        Gate::authorize('create', Location::class);

        return view('locations.create', [
            'types' => Location::TYPES,
            'statuses' => Location::STATUSES,
        ]);
    }

    /**
     * Store a newly created location.
     */
    public function store(StoreLocationRequest $request): RedirectResponse
    {
        Gate::authorize('create', Location::class);

        $data = $request->validated();
        $aliasesInput = $data['aliases'] ?? [];
        unset($data['aliases']);

        // Handle Image Upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('locations', 'public');
            $data['image_path'] = $path;
        }

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        // Check for ambiguous active aliases (Adjustment 3)
        $cleanAliases = [];
        foreach ($aliasesInput as $rawAlias) {
            $alias = trim((string) $rawAlias);
            if ($alias === '') {
                continue;
            }

            $conflict = $this->recognitionService->findConflictingLocationForAlias($alias);
            if ($conflict) {
                throw ValidationException::withMessages([
                    'aliases' => "The alias '{$alias}' is already assigned to active location '{$conflict->official_name}' ({$conflict->code}). An alias cannot point to multiple active locations.",
                ]);
            }
            $cleanAliases[] = $alias;
        }

        $location = Location::create($data);

        // Always save primary code and official name as recognized variations if desired,
        // and register all provided aliases
        foreach (array_unique($cleanAliases) as $alias) {
            LocationAlias::create([
                'location_id' => $location->id,
                'alias' => $alias,
            ]);
        }

        $this->recognitionService->clearCache();

        return redirect()->route('locations.show', $location)
            ->with('success', "Location '{$location->official_name}' created successfully.");
    }

    /**
     * Display the specified location details and usage.
     */
    public function show(Location $location): View
    {
        Gate::authorize('view', $location);

        $location->load(['aliases', 'creator', 'updater']);
        $usageStats = $location->getUsageStats();

        return view('locations.show', [
            'location' => $location,
            'usageStats' => $usageStats,
        ]);
    }

    /**
     * Show the form for editing the specified location.
     */
    public function edit(Location $location): View
    {
        Gate::authorize('update', $location);

        $location->load('aliases');

        return view('locations.edit', [
            'location' => $location,
            'types' => Location::TYPES,
            'statuses' => Location::STATUSES,
        ]);
    }

    /**
     * Update the specified location.
     */
    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        Gate::authorize('update', $location);

        $data = $request->validated();
        $aliasesInput = $data['aliases'] ?? [];
        unset($data['aliases']);

        // Handle Image Upload / replacement
        if ($request->hasFile('image')) {
            if ($location->image_path) {
                Storage::disk('public')->delete($location->image_path);
            }
            $data['image_path'] = $request->file('image')->store('locations', 'public');
        }

        $data['updated_by'] = auth()->id();

        // Check for ambiguous active aliases (Adjustment 3)
        $cleanAliases = [];
        foreach ($aliasesInput as $rawAlias) {
            $alias = trim((string) $rawAlias);
            if ($alias === '') {
                continue;
            }

            $conflict = $this->recognitionService->findConflictingLocationForAlias($alias, $location->id);
            if ($conflict) {
                throw ValidationException::withMessages([
                    'aliases' => "The alias '{$alias}' is already assigned to active location '{$conflict->official_name}' ({$conflict->code}). An alias cannot point to multiple active locations.",
                ]);
            }
            $cleanAliases[] = $alias;
        }

        $location->update($data);

        // Sync aliases: remove ones not in list, add new ones
        $existingAliases = $location->aliases->pluck('alias')->toArray();
        $targetAliases = array_unique($cleanAliases);

        // Delete removed aliases
        $toDelete = array_diff($existingAliases, $targetAliases);
        if (! empty($toDelete)) {
            $location->aliases()->whereIn('alias', $toDelete)->delete();
        }

        // Add new aliases
        $toAdd = array_diff($targetAliases, $existingAliases);
        foreach ($toAdd as $newAlias) {
            $location->aliases()->create(['alias' => $newAlias]);
        }

        $this->recognitionService->clearCache();

        return redirect()->route('locations.show', $location)
            ->with('success', "Location '{$location->official_name}' updated successfully.");
    }

    /**
     * Deactivate or safely delete the specified location (Adjustment 7).
     */
    public function destroy(Location $location): RedirectResponse
    {
        Gate::authorize('delete', $location);

        $tripCount = $location->trips()->count();

        // If historical trips reference this location, deactivation is strictly enforced
        if ($tripCount > 0) {
            $location->update([
                'status' => Location::STATUS_INACTIVE,
                'updated_by' => auth()->id(),
            ]);

            $this->recognitionService->clearCache();

            return redirect()->route('locations.index')
                ->with('success', "Location '{$location->official_name}' has been marked INACTIVE to protect {$tripCount} historical itinerary records.");
        }

        // Otherwise, safe delete
        if ($location->image_path) {
            Storage::disk('public')->delete($location->image_path);
        }

        $name = $location->official_name;
        $location->delete();

        $this->recognitionService->clearCache();

        return redirect()->route('locations.index')
            ->with('success', "Location '{$name}' deleted successfully.");
    }

    /**
     * Display interactive full directory map overview (Requirement 22).
     */
    public function map(Request $request): View
    {
        Gate::authorize('viewAny', Location::class);

        $query = Location::with('aliases');

        if ($type = $request->input('type')) {
            $query->ofType($type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        } else {
            // Default show active
            $query->active();
        }

        $locations = $query->orderBy('official_name')->get();

        return view('locations.map', [
            'locations' => $locations,
            'types' => Location::TYPES,
            'statuses' => Location::STATUSES,
            'filters' => $request->only(['type', 'status']),
        ]);
    }

    /**
     * Add single alias to a location.
     */
    public function storeAlias(Request $request, Location $location): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $location);

        $request->validate([
            'alias' => ['required', 'string', 'max:255'],
        ]);

        $aliasText = trim((string) $request->input('alias'));

        $conflict = $this->recognitionService->findConflictingLocationForAlias($aliasText, $location->id);
        if ($conflict) {
            $errorMsg = "The alias '{$aliasText}' already belongs to active location '{$conflict->official_name}' ({$conflict->code}).";
            if ($request->wantsJson()) {
                return response()->json(['message' => $errorMsg], 422);
            }

            return back()->with('error', $errorMsg);
        }

        $alias = $location->aliases()->firstOrCreate(['alias' => $aliasText]);
        $this->recognitionService->clearCache();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'alias' => $alias,
                'message' => 'Alias added successfully.',
            ]);
        }

        return back()->with('success', "Alias '{$aliasText}' added to {$location->official_name}.");
    }

    /**
     * Delete alias from a location.
     */
    public function destroyAlias(Location $location, LocationAlias $alias): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $location);

        if ($alias->location_id !== $location->id) {
            abort(404);
        }

        $name = $alias->alias;
        $alias->delete();
        $this->recognitionService->clearCache();

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Alias deleted.']);
        }

        return back()->with('success', "Alias '{$name}' removed.");
    }

    /**
     * Calculate road distances between three locations: Origin → Starting Point → Destination.
     * Used by the Advanced Itinerary create/edit form to auto-populate distances.
     */
    public function calculateDistance(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Location::class);

        $request->validate([
            'origin_id' => ['required', 'exists:locations,id'],
            'waypoint_id' => ['required', 'exists:locations,id'],
            'destination_id' => ['required', 'exists:locations,id'],
        ]);

        $origin = Location::findOrFail((int) $request->input('origin_id'));
        $waypoint = Location::findOrFail((int) $request->input('waypoint_id'));
        $destination = Location::findOrFail((int) $request->input('destination_id'));

        // Validate that all three locations have stored coordinates
        foreach ([
            [$origin, 'Origin'],
            [$waypoint, 'Starting Point'],
            [$destination, 'Destination'],
        ] as [$loc, $label]) {
            if (! $loc->latitude || ! $loc->longitude) {
                return response()->json([
                    'success' => false,
                    'message' => "Location '{$loc->official_name}' ({$label}) does not have coordinates stored. Please update the location record first.",
                ], 422);
            }
        }

        $result = $this->routingService->calculateLegDistances($origin, $waypoint, $destination);

        return response()->json([
            'success' => true,
            'origin_to_start' => $result['origin_to_start'],
            'start_to_dest' => $result['start_to_dest'],
            'total' => $result['total'],
            'duration_origin_to_start_minutes' => $result['duration_origin_to_start_minutes'],
            'duration_start_to_dest_minutes' => $result['duration_start_to_dest_minutes'],
            'total_duration_minutes' => $result['total_duration_minutes'],
            'formatted_origin_to_start_duration' => RoutingService::formatDuration($result['duration_origin_to_start_minutes']),
            'formatted_start_to_dest_duration' => RoutingService::formatDuration($result['duration_start_to_dest_minutes']),
            'formatted_total_duration' => RoutingService::formatDuration($result['total_duration_minutes']),
            'source' => $result['source'],
            'origin' => ['name' => $origin->official_name],
            'waypoint' => ['name' => $waypoint->official_name],
            'destination' => ['name' => $destination->official_name],
        ]);
    }

    /**
     * Export locations to Excel.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        Gate::authorize('viewAny', Location::class);

        $query = Location::query();
        if ($search = $request->input('search')) {
            $query->search($search);
        }
        if ($type = $request->input('type')) {
            $query->ofType($type);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $filename = 'Locations-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new LocationExport($query), $filename);
    }

    /**
     * Download Excel import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        Gate::authorize('viewAny', Location::class);

        return Excel::download(new LocationTemplateExport, 'location-import-template.xlsx');
    }

    /**
     * Import locations from uploaded Excel file.
     */
    public function importData(Request $request): RedirectResponse
    {
        Gate::authorize('create', Location::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $result = $this->importService->import($request->file('file'), auth()->id());

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        $msg = $result['message'];
        if (! empty($result['errors'])) {
            $msg .= ' Warnings: '.implode(' ', array_slice($result['errors'], 0, 3));
        }

        return redirect()->route('locations.index')->with('success', $msg);
    }
}
