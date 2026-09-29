<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AverageFuelConsumptionController extends Controller
{
    /**
     * Display the Average Fuel Consumption List.
     */
    public function index(Request $request): View
    {
        $query = Vehicle::query()->orderBy('equipment_code');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('equipment_code', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%")
                    ->orWhere('operator_driver', 'like', "%{$search}%")
                    ->orWhere('user', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%");
            });
        }

        // Filter: only configured or all
        if ($request->input('filter') === 'configured') {
            $query->whereNotNull('average_fuel_consumption')->where('average_fuel_consumption', '>', 0);
        } elseif ($request->input('filter') === 'unconfigured') {
            $query->where(function ($q) {
                $q->whereNull('average_fuel_consumption')->orWhere('average_fuel_consumption', '<=', 0);
            });
        }

        $vehicles = $query->paginate(20)->withQueryString();

        // All active vehicles for the dropdown modal
        $allVehicles = Vehicle::orderBy('equipment_code')->get(['id', 'equipment_code', 'model', 'plate_number', 'user', 'average_fuel_consumption']);

        // Fleet stats
        $totalConfigured = Vehicle::whereNotNull('average_fuel_consumption')->where('average_fuel_consumption', '>', 0)->count();
        $totalVehicles = Vehicle::count();
        $fleetAvg = Vehicle::whereNotNull('average_fuel_consumption')->where('average_fuel_consumption', '>', 0)->avg('average_fuel_consumption') ?? 0;
        $highestAvg = Vehicle::whereNotNull('average_fuel_consumption')->where('average_fuel_consumption', '>', 0)->max('average_fuel_consumption') ?? 0;
        $lowestAvg = Vehicle::whereNotNull('average_fuel_consumption')->where('average_fuel_consumption', '>', 0)->min('average_fuel_consumption') ?? 0;

        return view('average-fuel-consumption.index', compact(
            'vehicles',
            'allVehicles',
            'totalConfigured',
            'totalVehicles',
            'fleetAvg',
            'highestAvg',
            'lowestAvg'
        ));
    }

    /**
     * Store or set average fuel consumption for a vehicle.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'average_fuel_consumption' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
        ]);

        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $vehicle->update([
            'average_fuel_consumption' => $validated['average_fuel_consumption'],
        ]);

        return redirect()
            ->route('average-fuel-consumption.index')
            ->with('success', "Average fuel consumption for {$vehicle->equipment_code} set to {$validated['average_fuel_consumption']} KM/L.");
    }

    /**
     * Update average fuel consumption for a vehicle.
     */
    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validate([
            'average_fuel_consumption' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
        ]);

        $vehicle->update([
            'average_fuel_consumption' => $validated['average_fuel_consumption'],
        ]);

        return redirect()
            ->route('average-fuel-consumption.index')
            ->with('success', "Average fuel consumption for {$vehicle->equipment_code} updated to {$validated['average_fuel_consumption']} KM/L.");
    }

    /**
     * Remove / reset average fuel consumption for a vehicle.
     */
    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update([
            'average_fuel_consumption' => null,
        ]);

        return redirect()
            ->route('average-fuel-consumption.index')
            ->with('success', "Average fuel consumption for {$vehicle->equipment_code} has been cleared.");
    }
}
