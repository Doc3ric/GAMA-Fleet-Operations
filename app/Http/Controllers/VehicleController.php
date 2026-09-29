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
use Illuminate\Support\Str;
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
                    ->orWhere('operator_driver', 'like', "%{$search}%")
                    ->orWhere('user', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%");
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
        $archivedCount = Vehicle::onlyTrashed()->count();

        $vehicleTypes = VehicleType::orderBy('name')->get();
        $locations = Vehicle::whereNotNull('location')->distinct()->orderBy('location')->pluck('location');
        $projectCodes = Vehicle::whereNotNull('project_code')->distinct()->orderBy('project_code')->pluck('project_code');
        $statusLabels = Vehicle::whereNotNull('status_label')->distinct()->orderBy('status_label')->pluck('status_label');

        return view('vehicles.index', compact(
            'vehicles', 'vehicleTypes', 'locations', 'projectCodes', 'statusLabels', 'archivedCount'
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
        $code = $vehicle->equipment_code;
        $vehicle->delete(); // Soft deletes (moved to Archive Bin)

        return redirect()
            ->route('vehicles.index')
            ->with('success', "Vehicle {$code} moved to Archive Bin.");
    }

    public function archive(Request $request): View
    {
        $query = Vehicle::onlyTrashed()->orderByDesc('deleted_at');

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

        $vehicles = $query->paginate(20)->withQueryString();
        $archivedCount = Vehicle::onlyTrashed()->count();

        return view('vehicles.archive', compact('vehicles', 'archivedCount'));
    }

    public function restore(int|string $id): RedirectResponse
    {
        $vehicle = Vehicle::onlyTrashed()->findOrFail($id);
        $vehicle->restore();

        return redirect()
            ->back()
            ->with('success', "Vehicle {$vehicle->equipment_code} restored from Archive Bin successfully.");
    }

    public function forceDelete(int|string $id): RedirectResponse
    {
        $vehicle = Vehicle::onlyTrashed()->findOrFail($id);
        $code = $vehicle->equipment_code;

        if ($vehicle->image) {
            Storage::disk('public')->delete($vehicle->image);
        }

        $vehicle->forceDelete();

        return redirect()
            ->back()
            ->with('success', "Vehicle {$code} permanently deleted.");
    }

    public function restoreAll(): RedirectResponse
    {
        $count = Vehicle::onlyTrashed()->count();
        Vehicle::onlyTrashed()->restore();

        return redirect()
            ->route('vehicles.index')
            ->with('success', "Restored {$count} ".Str::plural('vehicle', $count).' from Archive Bin.');
    }

    public function emptyBin(): RedirectResponse
    {
        $trashed = Vehicle::onlyTrashed()->get();
        foreach ($trashed as $vehicle) {
            if ($vehicle->image) {
                Storage::disk('public')->delete($vehicle->image);
            }
            $vehicle->forceDelete();
        }

        return redirect()
            ->route('vehicles.archive')
            ->with('success', 'Archive bin emptied successfully.');
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
