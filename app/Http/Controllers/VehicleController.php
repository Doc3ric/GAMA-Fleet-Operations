<?php

namespace App\Http\Controllers;

use App\Exports\VehicleTemplateExport;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\VehicleImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        $query = Vehicle::with(['vehicleType', 'device'])
            ->orderBy('equipment_code');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('equipment_code', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%")
                    ->orWhere('operator_driver', 'like', "%{$search}%")
                    ->orWhereHas('vehicleType', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($typeId = $request->input('vehicle_type_id')) {
            $query->where('vehicle_type_id', $typeId);
        }

        if ($gpsStatus = $request->input('gps_status')) {
            $query->where('gps_status', $gpsStatus);
        }

        if ($statusLabel = $request->input('status_label')) {
            $query->where('status_label', $statusLabel);
        }

        if ($location = $request->input('location')) {
            $query->where('location', $location);
        }

        if ($projectCode = $request->input('project_code')) {
            $query->where('project_code', $projectCode);
        }

        $vehicles = $query->paginate(20)->withQueryString();

        $vehicleTypes = VehicleType::orderBy('name')->get();
        $locations = Vehicle::whereNotNull('location')->distinct()->orderBy('location')->pluck('location');
        $projectCodes = Vehicle::whereNotNull('project_code')->distinct()->orderBy('project_code')->pluck('project_code');
        $statusLabels = Vehicle::whereNotNull('status_label')->distinct()->orderBy('status_label')->pluck('status_label');

        return view('vehicles.index', compact(
            'vehicles', 'vehicleTypes', 'locations', 'projectCodes', 'statusLabels'
        ));
    }

    public function create(): View
    {
        $vehicleTypes = VehicleType::orderBy('name')->get();

        return view('vehicles.create', compact('vehicleTypes'));
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');

        $vehicle = Vehicle::create([
            ...$data,
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store("vehicles/{$vehicle->id}", 'public');
            $vehicle->update(['image' => $path]);
        }

        return redirect()
            ->route('vehicles.index')
            ->with('success', "Vehicle {$vehicle->equipment_code} added successfully.");
    }

    public function show(Vehicle $vehicle): View
    {
        $vehicle->load([
            'vehicleType',
            'device',
            'fuelConsumptionTests' => fn ($q) => $q->orderByDesc('test_date')->orderByDesc('id')->take(10),
        ]);

        return view('vehicles.show', compact('vehicle'));
    }

    public function edit(Vehicle $vehicle): View
    {
        $vehicle->load('vehicleType');
        $vehicleTypes = VehicleType::orderBy('name')->get();

        return view('vehicles.edit', compact('vehicle', 'vehicleTypes'));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $vehicle->update($data);

        if ($request->hasFile('image')) {
            if ($vehicle->image) {
                Storage::disk('public')->delete($vehicle->image);
            }

            $path = $request->file('image')->store("vehicles/{$vehicle->id}", 'public');
            $vehicle->update(['image' => $path]);
        }

        return redirect()
            ->route('vehicles.index')
            ->with('success', "Vehicle {$vehicle->equipment_code} updated successfully.");
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        if ($vehicle->image) {
            Storage::disk('public')->delete($vehicle->image);
        }

        $code = $vehicle->equipment_code;
        $vehicle->delete();

        return redirect()
            ->route('vehicles.index')
            ->with('success', "Vehicle {$code} deleted successfully.");
    }

    public function uploadImage(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:5120']]);

        if ($vehicle->image) {
            Storage::disk('public')->delete($vehicle->image);
        }

        $path = $request->file('image')->store("vehicles/{$vehicle->id}", 'public');
        $vehicle->update(['image' => $path]);

        return back()->with('success', 'Image updated successfully.');
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new VehicleTemplateExport, 'Vehicle-Master-List-Template.xlsx');
    }

    public function importData(Request $request, VehicleImportService $importService): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'mode' => ['nullable', 'string', 'in:update,append,replace'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt'], true)) {
            return redirect()->route('vehicles.index')
                ->with('error', 'Invalid file type. Please upload an Excel (.xlsx, .xls) or CSV (.csv) file.');
        }

        $mode = $request->input('mode', 'update');
        $result = $importService->import($file, $mode, auth()->id());

        if ($result['success']) {
            return redirect()->route('vehicles.index')
                ->with('success', $result['message']);
        }

        return redirect()->route('vehicles.index')
            ->with('error', $result['message']);
    }
}
