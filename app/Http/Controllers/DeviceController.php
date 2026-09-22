<?php

namespace App\Http\Controllers;

use App\Exports\DeviceExport;
use App\Exports\DeviceTemplateExport;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use App\Services\DeviceImportService;
use App\Services\VehicleDeviceLinkService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $userId = auth()->id();

        // Metric Counts
        $totalCount = Device::where('created_by', $userId)->count();
        $expiringSoonCount = Device::where('created_by', $userId)->expiringSoon(30)->count();
        $expiredCount = Device::where('created_by', $userId)->expired()->count();
        $activeCount = Device::where('created_by', $userId)->active(30)->count();

        $query = Device::where('created_by', $userId)->with('vehicle');

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('device_name', 'like', "%{$search}%")
                    ->orWhere('imei', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('sim', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%")
                    ->orWhere('iccid', 'like', "%{$search}%")
                    ->orWhere('imsi', 'like', "%{$search}%");
            });
        }

        // Status Filter
        $status = $request->input('status');
        if ($status === 'expiring_soon') {
            $query->expiringSoon(30);
        } elseif ($status === 'expired') {
            $query->expired();
        } elseif ($status === 'active') {
            $query->active(30);
        }

        // Group Filter
        if ($group = $request->input('group')) {
            $query->where('group_name', $group);
        }

        // Model Filter
        if ($model = $request->input('model')) {
            $query->where('model', $model);
        }

        // Sorting: Priority to expiring/expired if no specific sort requested
        if ($status === 'expiring_soon') {
            $query->orderBy('expiration_date', 'asc');
        } elseif ($status === 'expired') {
            $query->orderBy('expiration_date', 'desc');
        } else {
            $threshold = Carbon::today()->addDays(30)->toDateString();
            $query->orderByRaw('CASE WHEN expiration_date IS NOT NULL AND expiration_date <= ? THEN 0 ELSE 1 END', [$threshold])
                ->orderBy('expiration_date', 'asc')
                ->orderBy('device_name', 'asc');
        }

        $devices = $query->paginate(25)->withQueryString();

        $groups = Device::where('created_by', $userId)->whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name');
        $models = Device::where('created_by', $userId)->whereNotNull('model')->distinct()->orderBy('model')->pluck('model');

        return view('devices.index', compact(
            'devices',
            'totalCount',
            'expiringSoonCount',
            'expiredCount',
            'activeCount',
            'groups',
            'models'
        ));
    }

    public function create(): View
    {
        $userId = auth()->id();
        $groups = Device::where('created_by', $userId)->whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name');
        $models = Device::where('created_by', $userId)->whereNotNull('model')->distinct()->orderBy('model')->pluck('model');

        return view('devices.create', compact('groups', 'models'));
    }

    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $device = Device::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('devices.index')
            ->with('success', "Device {$device->device_name} added successfully.");
    }

    public function show(Device $device): View
    {
        $this->authorizeDevice($device);
        $device->load('vehicle');

        return view('devices.show', compact('device'));
    }

    public function edit(Device $device): View
    {
        $this->authorizeDevice($device);

        $userId = auth()->id();
        $groups = Device::where('created_by', $userId)->whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name');
        $models = Device::where('created_by', $userId)->whereNotNull('model')->distinct()->orderBy('model')->pluck('model');

        return view('devices.edit', compact('device', 'groups', 'models'));
    }

    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $this->authorizeDevice($device);

        $device->update($request->validated());

        return redirect()
            ->route('devices.index')
            ->with('success', "Device {$device->device_name} updated successfully.");
    }

    public function destroy(Device $device): RedirectResponse
    {
        $this->authorizeDevice($device);

        $name = $device->device_name;
        $device->delete();

        return redirect()
            ->route('devices.index')
            ->with('success', "Device {$name} deleted successfully.");
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new DeviceTemplateExport, 'GPS-Devices-Import-Template.xlsx');
    }

    public function generatePdf(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $userId = auth()->id();
        $query = Device::where('created_by', $userId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('device_name', 'like', "%{$search}%")
                    ->orWhere('imei', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('sim', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%");
            });
        }

        $status = $request->input('status');
        $statusLabel = 'All Devices';
        if ($status === 'expiring_soon') {
            $query->expiringSoon(30);
            $statusLabel = 'Expiring in ≤ 30 Days (Renewal Budget Required)';
        } elseif ($status === 'expired') {
            $query->expired();
            $statusLabel = 'Expired Devices';
        } elseif ($status === 'active') {
            $query->active(30);
            $statusLabel = 'Active Devices (> 30 Days)';
        }

        if ($group = $request->input('group')) {
            $query->where('group_name', $group);
        }

        $devices = $query->orderBy('expiration_date', 'asc')->get();

        $totalCount = Device::where('created_by', $userId)->count();
        $expiringSoonCount = Device::where('created_by', $userId)->expiringSoon(30)->count();
        $expiredCount = Device::where('created_by', $userId)->expired()->count();
        $activeCount = Device::where('created_by', $userId)->active(30)->count();

        $pdf = Pdf::loadView('pdf.devices-report', [
            'devices' => $devices,
            'totalCount' => $totalCount,
            'expiringSoonCount' => $expiringSoonCount,
            'expiredCount' => $expiredCount,
            'activeCount' => $activeCount,
            'statusFilter' => $statusLabel,
            'company' => config('foms.company_name', 'GAMA Foods Corporation'),
            'tagline' => config('foms.company_tagline', 'Fleet Operations Management System'),
            'preparedBy' => auth()->user()?->name ?? 'Fleet Operator',
            'generatedAt' => now()->format('F d, Y h:i A'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true);

        $filename = 'GPS-Devices-Report-'.now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $userId = auth()->id();
        $query = Device::where('created_by', $userId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('device_name', 'like', "%{$search}%")
                    ->orWhere('imei', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('sim', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%");
            });
        }

        $status = $request->input('status');
        if ($status === 'expiring_soon') {
            $query->expiringSoon(30);
        } elseif ($status === 'expired') {
            $query->expired();
        } elseif ($status === 'active') {
            $query->active(30);
        }

        if ($group = $request->input('group')) {
            $query->where('group_name', $group);
        }

        $filename = 'GPS-Devices-Export-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new DeviceExport($query), $filename);
    }

    public function importData(Request $request, DeviceImportService $importService): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'mode' => ['nullable', 'string', 'in:update,append,replace'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt'], true)) {
            return redirect()->route('devices.index')
                ->with('error', 'Invalid file type. Please upload an Excel (.xlsx, .xls) or CSV (.csv) file.');
        }

        $mode = $request->input('mode', 'update');
        $result = $importService->import($file, $mode, auth()->id());

        if ($result['success']) {
            return redirect()->route('devices.index')
                ->with('success', $result['message']);
        }

        return redirect()->route('devices.index')
            ->with('error', $result['message']);
    }

    public function syncVehicles(VehicleDeviceLinkService $linkService): RedirectResponse
    {
        $result = $linkService->syncAll(auth()->id());

        $msg = "Vehicle & GPS sync complete: {$result['linked']} devices newly paired, {$result['updated']} vehicle GPS statuses updated.";

        return redirect()->route('devices.index')
            ->with('success', $msg);
    }

    private function authorizeDevice(Device $device): void
    {
        if ($device->created_by !== auth()->id()) {
            abort(403);
        }
    }
}
