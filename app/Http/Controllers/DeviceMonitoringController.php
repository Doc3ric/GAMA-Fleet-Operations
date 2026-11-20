<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceMonitoringAction;
use App\Models\DeviceMonitoringCheck;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeviceMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:all,pending,completed'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $timezone = (string) config('fleet_reconciliation.tracksolid.source_timezone', 'Asia/Manila');
        $selectedDay = Carbon::parse($validated['date'] ?? Carbon::now($timezone)->toDateString(), $timezone)->startOfDay();
        $date = $selectedDay->toDateString();
        $dayStartUtc = $selectedDay->copy()->utc();
        $dayEndUtc = $selectedDay->copy()->endOfDay()->utc();
        $userId = (int) $request->user()->id;

        $devices = Device::withTrashed()
            ->with('vehicle')
            ->where('created_by', $userId)
            ->where('created_at', '<=', $dayEndUtc)
            ->where(function ($query) use ($dayStartUtc): void {
                $query->whereNull('deleted_at')->orWhere('deleted_at', '>=', $dayStartUtc);
            })
            ->orderBy('device_name')
            ->get();

        $checks = DeviceMonitoringCheck::with('checker')
            ->where('owner_id', $userId)
            ->where('monitored_on', $date)
            ->get()
            ->keyBy('device_id');

        $monthStart = $selectedDay->copy()->startOfMonth();
        $monthEnd = $selectedDay->copy()->endOfMonth();
        $checkCounts = DeviceMonitoringCheck::where('owner_id', $userId)
            ->where('is_completed', true)
            ->whereBetween('monitored_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw('monitored_on, COUNT(*) as total')
            ->groupBy('monitored_on')
            ->pluck('total', 'monitored_on');

        $calendarDays = [];
        $calendarDay = $monthStart->copy()->startOfWeek();
        $calendarEnd = $monthEnd->copy()->endOfWeek();

        while ($calendarDay->lessThanOrEqualTo($calendarEnd)) {
            $calendarDate = $calendarDay->toDateString();
            $calendarDays[] = [
                'date' => $calendarDate,
                'day' => $calendarDay->day,
                'in_month' => $calendarDay->month === $selectedDay->month,
                'checked_count' => (int) ($checkCounts[$calendarDate] ?? 0),
            ];
            $calendarDay->addDay();
        }

        $completedCount = $devices->filter(fn (Device $device): bool => $checks->get($device->id)?->is_completed === true)->count();
        $statusFilter = $validated['status'] ?? 'all';
        $search = trim($validated['search'] ?? '');
        $displayDevices = $devices->filter(function (Device $device) use ($checks, $statusFilter, $search): bool {
            $isCompleted = $checks->get($device->id)?->is_completed === true;

            if ($statusFilter === 'completed' && ! $isCompleted) {
                return false;
            }

            if ($statusFilter === 'pending' && $isCompleted) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $searchableText = implode(' ', [
                $device->device_name,
                $device->imei,
                $device->vehicle?->equipment_code,
                $device->vehicle?->plate_number,
            ]);

            return stripos($searchableText, $search) !== false;
        })->values();

        return view('devices.monitoring', [
            'devices' => $devices,
            'displayDevices' => $displayDevices,
            'checks' => $checks,
            'selectedDate' => $date,
            'selectedDay' => $selectedDay,
            'today' => Carbon::now($timezone)->toDateString(),
            'timezone' => $timezone,
            'totalCount' => $devices->count(),
            'completedCount' => $completedCount,
            'pendingCount' => $devices->count() - $completedCount,
            'completionPercent' => $devices->isEmpty() ? 0 : (int) round($completedCount / $devices->count() * 100),
            'statusFilter' => $statusFilter,
            'search' => $search,
            'calendarDays' => $calendarDays,
            'previousMonth' => $monthStart->copy()->subMonth()->toDateString(),
            'nextMonth' => $monthStart->copy()->addMonth()->toDateString(),
        ]);
    }

    public function store(Request $request, Device $device): RedirectResponse
    {
        abort_unless($device->created_by === $request->user()->id, 404);

        $validated = $request->validate([
            'return_to' => ['nullable', 'in:devices,monitoring'],
        ]);

        $timezone = (string) config('fleet_reconciliation.tracksolid.source_timezone', 'Asia/Manila');
        $date = Carbon::now($timezone)->toDateString();
        $device->load('vehicle');

        DB::transaction(function () use ($device, $date, $request): void {
            $check = DeviceMonitoringCheck::firstOrCreate(
                ['device_id' => $device->id, 'monitored_on' => $date],
                [
                    'owner_id' => $request->user()->id,
                    'checked_by' => $request->user()->id,
                    'checked_at' => now(),
                    'device_name' => $device->device_name,
                    'imei' => $device->imei,
                    'vehicle_label' => $device->vehicle?->equipment_code,
                    'is_completed' => true,
                ]
            );

            if (! $check->wasRecentlyCreated && $check->is_completed) {
                return;
            }

            if (! $check->wasRecentlyCreated) {
                $check->update([
                    'checked_by' => $request->user()->id,
                    'checked_at' => now(),
                    'is_completed' => true,
                    'reopened_at' => null,
                    'reopened_by' => null,
                ]);
            }

            $check->actions()->create([
                'performed_by' => $request->user()->id,
                'action' => DeviceMonitoringAction::ACTION_COMPLETED,
                'performed_at' => now(),
            ]);
        });

        $destination = ($validated['return_to'] ?? 'monitoring') === 'devices'
            ? route('devices.index')
            : route('devices.monitoring.index', ['date' => $date]);

        return redirect($destination)->with('success', "{$device->device_name} marked as monitored for {$date}.");
    }

    public function reopen(Request $request, Device $device): RedirectResponse
    {
        abort_unless($device->created_by === $request->user()->id, 404);

        $validated = $request->validate([
            'return_to' => ['nullable', 'in:devices,monitoring'],
        ]);

        $timezone = (string) config('fleet_reconciliation.tracksolid.source_timezone', 'Asia/Manila');
        $date = Carbon::now($timezone)->toDateString();

        DB::transaction(function () use ($device, $date, $request): void {
            $check = DeviceMonitoringCheck::where('device_id', $device->id)
                ->where('owner_id', $request->user()->id)
                ->where('monitored_on', $date)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $check->is_completed) {
                return;
            }

            $check->update([
                'is_completed' => false,
                'reopened_at' => now(),
                'reopened_by' => $request->user()->id,
            ]);

            $check->actions()->create([
                'performed_by' => $request->user()->id,
                'action' => DeviceMonitoringAction::ACTION_REOPENED,
                'performed_at' => now(),
            ]);
        });

        $destination = ($validated['return_to'] ?? 'monitoring') === 'devices'
            ? route('devices.index')
            : route('devices.monitoring.index', ['date' => $date]);

        return redirect($destination)->with('success', "{$device->device_name} moved back to pending for {$date}.");
    }
}
