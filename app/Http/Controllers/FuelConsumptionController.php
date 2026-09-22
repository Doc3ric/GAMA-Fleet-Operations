<?php

namespace App\Http\Controllers;

use App\Exports\FuelConsumptionExport;
use App\Http\Requests\StoreFuelConsumptionRequest;
use App\Http\Requests\UpdateFuelConsumptionRequest;
use App\Models\FuelConsumptionTest;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FuelConsumptionCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FuelConsumptionController extends Controller
{
    public function __construct(
        protected FuelConsumptionCalculationService $calculator
    ) {}

    /**
     * Display a listing of average fuel consumption tests.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', FuelConsumptionTest::class);

        $query = FuelConsumptionTest::with(['vehicle.vehicleType', 'driver', 'creator'])
            ->orderByDesc('test_date')
            ->orderByDesc('id');

        if ($vehicleId = $request->input('vehicle_id')) {
            $query->forVehicle((int) $vehicleId);
        }

        if ($search = $request->input('search')) {
            $query->search($search);
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($startDate || $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        // Statistics across filtered query or all tests
        $statsQuery = clone $query;
        $totalTests = (clone $statsQuery)->count();
        $avgKmL = $totalTests > 0 ? (float) ((clone $statsQuery)->avg('average_fuel_consumption')) : 0.0;
        $bestKmL = $totalTests > 0 ? (float) ((clone $statsQuery)->max('average_fuel_consumption')) : 0.0;
        $lowestKmL = $totalTests > 0 ? (float) ((clone $statsQuery)->min('average_fuel_consumption')) : 0.0;
        $totalDistance = $totalTests > 0 ? (float) ((clone $statsQuery)->sum('distance_travelled')) : 0.0;
        $totalFuel = $totalTests > 0 ? (float) ((clone $statsQuery)->sum('fuel_consumed_liters')) : 0.0;

        $tests = $query->paginate(15)->withQueryString();
        $vehicles = Vehicle::orderBy('equipment_code')->get();

        return view('fuel-consumption.index', compact(
            'tests',
            'vehicles',
            'totalTests',
            'avgKmL',
            'bestKmL',
            'lowestKmL',
            'totalDistance',
            'totalFuel'
        ));
    }

    /**
     * Show the form for creating a new fuel consumption test.
     */
    public function create(): View
    {
        Gate::authorize('create', FuelConsumptionTest::class);

        $vehicles = Vehicle::with('vehicleType')->orderBy('equipment_code')->get();
        $drivers = User::where('role', User::ROLE_DRIVER)->orderBy('name')->get();

        return view('fuel-consumption.create', compact('vehicles', 'drivers'));
    }

    /**
     * Store a newly created fuel consumption test.
     */
    public function store(StoreFuelConsumptionRequest $request): RedirectResponse
    {
        Gate::authorize('create', FuelConsumptionTest::class);

        $validated = $request->validated();

        // Authoritative server-side calculation
        $startOdo = (float) $validated['start_odometer'];
        $endOdo = (float) $validated['end_odometer'];
        $fuelLiters = (float) $validated['fuel_consumed_liters'];

        $calculated = $this->calculator->calculateFromReadings($startOdo, $endOdo, $fuelLiters);

        // If driver_id is provided and driver_name is empty, prefill driver_name
        $driverName = $validated['driver_name'] ?? null;
        if (! empty($validated['driver_id']) && empty($driverName)) {
            $driverUser = User::find($validated['driver_id']);
            $driverName = $driverUser?->name;
        }

        // Resolve Vehicle: existing vehicle ID or generate new vehicle from manual specification
        $targetVehicleId = null;
        $customEquipmentCode = trim($validated['custom_equipment_code'] ?? '');
        $customPlateNumber = trim($validated['custom_plate_number'] ?? '');
        $customModel = trim($validated['custom_model'] ?? '');

        if ($validated['vehicle_id'] === 'specify' && ! empty($customEquipmentCode)) {
            $vehicle = Vehicle::firstOrCreate(
                ['equipment_code' => $customEquipmentCode],
                [
                    'plate_number' => $customPlateNumber ?: null,
                    'model' => $customModel ?: null,
                    'operator_driver' => $driverName ?: null,
                    'gps_status' => 'NO',
                    'created_by' => auth()->id(),
                ]
            );
            $targetVehicleId = $vehicle->id;
        } elseif (is_numeric($validated['vehicle_id'])) {
            $targetVehicleId = (int) $validated['vehicle_id'];
        }

        $test = FuelConsumptionTest::create([
            'test_date' => $validated['test_date'],
            'vehicle_id' => $targetVehicleId,
            'custom_equipment_code' => $customEquipmentCode ?: null,
            'custom_plate_number' => $customPlateNumber ?: null,
            'custom_model' => $customModel ?: null,
            'driver_id' => $validated['driver_id'] ?? null,
            'driver_name' => $driverName,
            'start_odometer' => $startOdo,
            'end_odometer' => $endOdo,
            'distance_travelled' => $calculated['distance_travelled'],
            'fuel_consumed_liters' => $fuelLiters,
            'average_fuel_consumption' => $calculated['average_fuel_consumption'],
            'test_route' => $validated['test_route'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'attested_by' => $validated['attested_by'] ?? null,
            'requested_by' => $validated['requested_by'] ?? null,
            'created_by' => auth()->id(),
        ]);

        // Process attachments
        $uploads = [];
        if ($request->hasFile('start_odometer_image')) {
            $uploads['start_odometer_image'] = $request->file('start_odometer_image')
                ->store("fuel-tests/{$test->id}", 'public');
        }
        if ($request->hasFile('end_odometer_image')) {
            $uploads['end_odometer_image'] = $request->file('end_odometer_image')
                ->store("fuel-tests/{$test->id}", 'public');
        }
        if ($request->hasFile('fuel_receipt_image')) {
            $uploads['fuel_receipt_image'] = $request->file('fuel_receipt_image')
                ->store("fuel-tests/{$test->id}", 'public');
        }

        if (! empty($uploads)) {
            $test->update($uploads);
        }

        $notice = "Fuel consumption test recorded successfully. Calculated: {$calculated['distance_travelled']} km @ {$calculated['average_fuel_consumption']} km/L.";
        if ($this->calculator->isShortDistance($calculated['distance_travelled'])) {
            $notice .= ' Note: Test distance is relatively short ('.$calculated['distance_travelled'].' km).';
        }

        return redirect()
            ->route('fuel-consumption.show', $test)
            ->with('success', $notice);
    }

    /**
     * Display the specified fuel consumption test.
     */
    public function show(FuelConsumptionTest $fuelConsumption): View
    {
        Gate::authorize('view', $fuelConsumption);

        $fuelConsumption->load(['vehicle.vehicleType', 'driver', 'creator']);

        return view('fuel-consumption.show', [
            'test' => $fuelConsumption,
        ]);
    }

    /**
     * Show the form for editing the specified fuel consumption test.
     */
    public function edit(FuelConsumptionTest $fuelConsumption): View
    {
        Gate::authorize('update', $fuelConsumption);

        $fuelConsumption->load(['vehicle', 'driver']);
        $vehicles = Vehicle::with('vehicleType')->orderBy('equipment_code')->get();
        $drivers = User::where('role', User::ROLE_DRIVER)->orderBy('name')->get();

        return view('fuel-consumption.edit', [
            'test' => $fuelConsumption,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
        ]);
    }

    /**
     * Update the specified fuel consumption test.
     */
    public function update(UpdateFuelConsumptionRequest $request, FuelConsumptionTest $fuelConsumption): RedirectResponse
    {
        Gate::authorize('update', $fuelConsumption);

        $validated = $request->validated();

        $startOdo = (float) $validated['start_odometer'];
        $endOdo = (float) $validated['end_odometer'];
        $fuelLiters = (float) $validated['fuel_consumed_liters'];

        $calculated = $this->calculator->calculateFromReadings($startOdo, $endOdo, $fuelLiters);

        $driverName = $validated['driver_name'] ?? null;
        if (! empty($validated['driver_id']) && empty($driverName)) {
            $driverUser = User::find($validated['driver_id']);
            $driverName = $driverUser?->name;
        }

        // Resolve Vehicle: existing vehicle ID or generate new vehicle from manual specification
        $targetVehicleId = null;
        $customEquipmentCode = trim($validated['custom_equipment_code'] ?? '');
        $customPlateNumber = trim($validated['custom_plate_number'] ?? '');
        $customModel = trim($validated['custom_model'] ?? '');

        if ($validated['vehicle_id'] === 'specify' && ! empty($customEquipmentCode)) {
            $vehicle = Vehicle::firstOrCreate(
                ['equipment_code' => $customEquipmentCode],
                [
                    'plate_number' => $customPlateNumber ?: null,
                    'model' => $customModel ?: null,
                    'operator_driver' => $driverName ?: null,
                    'gps_status' => 'NO',
                    'created_by' => auth()->id(),
                ]
            );
            $targetVehicleId = $vehicle->id;
        } elseif (is_numeric($validated['vehicle_id'])) {
            $targetVehicleId = (int) $validated['vehicle_id'];
        }

        $updateData = [
            'test_date' => $validated['test_date'],
            'vehicle_id' => $targetVehicleId,
            'custom_equipment_code' => $customEquipmentCode ?: null,
            'custom_plate_number' => $customPlateNumber ?: null,
            'custom_model' => $customModel ?: null,
            'driver_id' => $validated['driver_id'] ?? null,
            'driver_name' => $driverName,
            'start_odometer' => $startOdo,
            'end_odometer' => $endOdo,
            'distance_travelled' => $calculated['distance_travelled'],
            'fuel_consumed_liters' => $fuelLiters,
            'average_fuel_consumption' => $calculated['average_fuel_consumption'],
            'test_route' => $validated['test_route'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'attested_by' => $validated['attested_by'] ?? null,
            'requested_by' => $validated['requested_by'] ?? null,
        ];

        // Handle replacement of attachments
        if ($request->hasFile('start_odometer_image')) {
            if ($fuelConsumption->start_odometer_image) {
                Storage::disk('public')->delete($fuelConsumption->start_odometer_image);
            }
            $updateData['start_odometer_image'] = $request->file('start_odometer_image')
                ->store("fuel-tests/{$fuelConsumption->id}", 'public');
        }
        if ($request->hasFile('end_odometer_image')) {
            if ($fuelConsumption->end_odometer_image) {
                Storage::disk('public')->delete($fuelConsumption->end_odometer_image);
            }
            $updateData['end_odometer_image'] = $request->file('end_odometer_image')
                ->store("fuel-tests/{$fuelConsumption->id}", 'public');
        }
        if ($request->hasFile('fuel_receipt_image')) {
            if ($fuelConsumption->fuel_receipt_image) {
                Storage::disk('public')->delete($fuelConsumption->fuel_receipt_image);
            }
            $updateData['fuel_receipt_image'] = $request->file('fuel_receipt_image')
                ->store("fuel-tests/{$fuelConsumption->id}", 'public');
        }

        $fuelConsumption->update($updateData);

        return redirect()
            ->route('fuel-consumption.show', $fuelConsumption)
            ->with('success', "Fuel consumption test updated successfully. Recalculated: {$calculated['distance_travelled']} km @ {$calculated['average_fuel_consumption']} km/L.");
    }

    /**
     * Remove the specified fuel consumption test.
     */
    public function destroy(FuelConsumptionTest $fuelConsumption): RedirectResponse
    {
        Gate::authorize('delete', $fuelConsumption);

        // Delete uploaded files
        if ($fuelConsumption->start_odometer_image) {
            Storage::disk('public')->delete($fuelConsumption->start_odometer_image);
        }
        if ($fuelConsumption->end_odometer_image) {
            Storage::disk('public')->delete($fuelConsumption->end_odometer_image);
        }
        if ($fuelConsumption->fuel_receipt_image) {
            Storage::disk('public')->delete($fuelConsumption->fuel_receipt_image);
        }

        $vehicleCode = $fuelConsumption->vehicle?->equipment_code ?? 'record';
        $fuelConsumption->delete();

        return redirect()
            ->route('fuel-consumption.index')
            ->with('success', "Fuel consumption test for {$vehicleCode} deleted successfully.");
    }

    /**
     * Export a single test report to PDF.
     */
    public function exportPdf(FuelConsumptionTest $fuelConsumption): Response
    {
        Gate::authorize('view', $fuelConsumption);

        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $fuelConsumption->load(['vehicle.vehicleType', 'driver', 'creator']);

        $pdf = Pdf::loadView('pdf.fuel-consumption-single', [
            'test' => $fuelConsumption,
            'company' => config('foms.company_name', 'GAMA'),
            'tagline' => config('foms.company_tagline', 'Fleet Operations Management System'),
            'preparedBy' => config('foms.prepared_by', 'GPS Monitoring Specialist'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true);

        $filename = 'Average-Fuel-Consumption-'.($fuelConsumption->vehicle?->equipment_code ?? 'Test').'-'.$fuelConsumption->test_date->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export filtered fuel consumption tests to a summary PDF.
     */
    public function exportAllPdf(Request $request): Response
    {
        Gate::authorize('viewAny', FuelConsumptionTest::class);

        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $query = FuelConsumptionTest::with(['vehicle', 'driver'])->orderByDesc('test_date');

        if ($vehicleId = $request->input('vehicle_id')) {
            $query->forVehicle((int) $vehicleId);
        }
        if ($search = $request->input('search')) {
            $query->search($search);
        }
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($startDate || $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        $tests = $query->get();

        $pdf = Pdf::loadView('pdf.fuel-consumption-summary', [
            'tests' => $tests,
            'company' => config('foms.company_name', 'GAMA'),
            'tagline' => config('foms.company_tagline', 'Fleet Operations Management System'),
            'preparedBy' => config('foms.prepared_by', 'GPS Monitoring Specialist'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true);

        $filename = 'Fuel-Consumption-Summary-'.now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export tests to Excel.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        Gate::authorize('viewAny', FuelConsumptionTest::class);

        $query = FuelConsumptionTest::with(['vehicle', 'driver'])->orderByDesc('test_date');

        if ($vehicleId = $request->input('vehicle_id')) {
            $query->forVehicle((int) $vehicleId);
        }
        if ($search = $request->input('search')) {
            $query->search($search);
        }
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($startDate || $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        $filename = 'Average-Fuel-Consumption-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new FuelConsumptionExport($query), $filename);
    }
}
