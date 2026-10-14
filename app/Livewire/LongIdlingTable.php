<?php

namespace App\Livewire;

use App\Models\LongIdlingRecord;
use App\Models\Report;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;

class LongIdlingTable extends Component
{
    public ?int $reportId = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public string $dateMode = 'single'; // 'single' or 'range'

    public array $availableDates = [];

    public array $rows = [];

    public ?int $deleteConfirmId = null;

    public ?int $deleteConfirmIndex = null; // for unsaved rows

    public string $search = '';

    public string $sortBy = 'default';

    public string $filterDuration = 'all';

    public string $filterDevice = 'all';

    public array $selectedIds = [];

    public bool $selectAll = false;

    public ?string $savedMessage = null;

    public bool $showManualVehicleModal = false;

    public ?int $manualVehicleId = null;

    public string $manualVehicleSearch = '';

    public string $manualStartTime = '';

    public string $manualEndTime = '';

    public string $manualStayTime = '';

    public string $manualAddress = '';

    public string $manualRemarks = '';

    public string $manualState = 'Idling';

    public bool $manualFocus = true;

    public ?int $manualRowIndex = null;

    public ?string $manualReportDate = null;

    public ?string $manualRangeStart = null;

    public ?string $manualRangeEnd = null;

    public function getHasDirtyProperty(): bool
    {
        foreach ($this->rows as $row) {
            if (! empty($row['dirty'])) {
                return true;
            }
        }

        return false;
    }

    public function mount(
        ?int $reportId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        string $dateMode = 'single',
        ?string $filterDevice = null
    ): void {
        $this->reportId = $reportId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->dateMode = $dateMode;

        if ($filterDevice && $filterDevice !== 'all') {
            $this->filterDevice = $filterDevice;
        } elseif (request()->filled('device') && request()->query('device') !== 'all') {
            $this->filterDevice = (string) request()->query('device');
        }

        $this->loadAvailableDates();

        if ($this->reportId && $this->dateMode === 'single') {
            $report = Report::find($this->reportId);
            if ($report) {
                $this->startDate = $report->report_date->format('Y-m-d');
                $this->endDate = $report->report_date->format('Y-m-d');
            }
        }

        if (empty($this->startDate) || empty($this->endDate)) {
            if (! empty($this->availableDates)) {
                $this->endDate = $this->availableDates[0];
                $this->startDate = count($this->availableDates) > 1 ? end($this->availableDates) : $this->availableDates[0];
            } else {
                $this->startDate = now()->subDays(7)->format('Y-m-d');
                $this->endDate = now()->format('Y-m-d');
            }
        }

        $this->loadRows();
    }

    public function loadAvailableDates(): void
    {
        $this->availableDates = Report::where('created_by', auth()->id())
            ->where('report_type', 'long_idling')
            ->orderByDesc('report_date')
            ->pluck('report_date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();
    }

    public function loadRows(): void
    {
        $userId = auth()->id();

        if ($this->dateMode === 'range' || empty($this->reportId)) {
            $start = min($this->startDate ?? now()->toDateString(), $this->endDate ?? now()->toDateString());
            $end = max($this->startDate ?? now()->toDateString(), $this->endDate ?? now()->toDateString());

            $records = LongIdlingRecord::select('long_idling_records.*')
                ->join('reports', 'long_idling_records.report_id', '=', 'reports.id')
                ->where('reports.created_by', $userId)
                ->where('reports.report_type', 'long_idling')
                ->whereDate('reports.report_date', '>=', $start)
                ->whereDate('reports.report_date', '<=', $end)
                ->with('report')
                ->orderByDesc('reports.report_date')
                ->orderBy('long_idling_records.sort_order')
                ->orderBy('long_idling_records.id')
                ->get();
        } else {
            $records = LongIdlingRecord::where('report_id', $this->reportId)
                ->with('report')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        $this->rows = $records->map(fn ($r) => [
            'id' => $r->id,
            'report_id' => $r->report_id,
            'report_date' => $r->report?->report_date?->format('Y-m-d') ?? '',
            'formatted_date' => $r->report?->report_date?->format('M j, Y') ?? '',
            'device_name' => $r->device_name ?? '',
            'driver_name' => $r->driver_name ?? '',
            'imei' => $r->imei ?? '',
            'model' => $r->model ?? '',
            'state' => $r->state ?? '',
            'start_time' => $r->start_time ? substr($r->start_time, 0, 8) : '',
            'end_time' => $r->end_time ? substr($r->end_time, 0, 8) : '',
            'stay_time' => $r->stay_time ?? '',
            'latitude' => $r->latitude ?? '',
            'longitude' => $r->longitude ?? '',
            'coordinates' => ($r->latitude !== null && $r->longitude !== null) ? "{$r->latitude}, {$r->longitude}" : '',
            'address' => $r->address ?? '',
            'remarks' => $r->remarks ?? '',
            'image' => $r->image,
            'image_url' => $r->image_url,
            'is_new' => false,
            'dirty' => false,
            '_key' => 'rec-'.$r->id,
        ])
            ->toArray();
    }

    public function addRow(): void
    {
        $this->search = '';
        $this->savedMessage = null;

        $targetDate = $this->endDate ?? now()->toDateString();
        $targetReportId = $this->reportId;

        $this->rows[] = [
            'id' => null,
            'report_id' => $targetReportId,
            'report_date' => $targetDate,
            'formatted_date' => date('M j, Y', strtotime($targetDate)),
            'device_name' => '',
            'driver_name' => '',
            'imei' => '',
            'model' => '',
            'state' => '',
            'start_time' => '',
            'end_time' => '',
            'stay_time' => '',
            'latitude' => '',
            'longitude' => '',
            'coordinates' => '',
            'address' => '',
            'remarks' => '',
            'image' => null,
            'image_url' => null,
            'is_new' => true,
            'dirty' => true,
            '_key' => 'new-'.uniqid(),
        ];
    }

    public function updatedRows($value, $key): void
    {
        $parts = explode('.', $key, 2);

        if (count($parts) < 2) {
            return;
        }

        [$index, $field] = $parts;
        $index = (int) $index;

        $this->rows[$index]['dirty'] = true;
        $this->savedMessage = null;

        if (in_array($field, ['start_time', 'end_time'])) {
            $this->recalcStayTime($index);
        }

        if ($field === 'coordinates' && is_string($value)) {
            $coords = trim($value);
            if (str_contains($coords, ',')) {
                $parts = explode(',', $coords, 2);
                $this->rows[$index]['latitude'] = trim($parts[0]);
                $this->rows[$index]['longitude'] = trim($parts[1]);
            }
        }
    }

    public function updatedSearch(): void
    {
        $this->selectAll = false;
        $this->notifyFiltersUpdated();
    }

    public function updatedFilterDevice(): void
    {
        $this->selectAll = false;
        $this->notifyFiltersUpdated();
    }

    public function updatedFilterDuration(): void
    {
        $this->selectAll = false;
        $this->notifyFiltersUpdated();
    }

    public function updatedStartDate(): void
    {
        if ($this->startDate && $this->endDate && $this->startDate > $this->endDate) {
            $this->endDate = $this->startDate;
        }
        $this->dateMode = 'range';
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->loadRows();
        $this->notifyFiltersUpdated();
    }

    public function updatedEndDate(): void
    {
        if ($this->startDate && $this->endDate && $this->startDate > $this->endDate) {
            $this->startDate = $this->endDate;
        }
        $this->dateMode = 'range';
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->loadRows();
        $this->notifyFiltersUpdated();
    }

    public function setDateRange(string $start, string $end): void
    {
        $this->startDate = min($start, $end);
        $this->endDate = max($start, $end);
        $this->dateMode = 'range';
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->loadRows();
        $this->notifyFiltersUpdated();
    }

    public function setDateMode(string $mode): void
    {
        $this->dateMode = $mode;
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->loadRows();
        $this->notifyFiltersUpdated();
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $filtered = $this->getFilteredRows();
            $this->selectedIds = array_values(array_filter(
                array_map(fn ($r) => (int) ($r['id'] ?? 0), $filtered),
                fn ($id) => $id > 0
            ));
        } else {
            $this->selectedIds = [];
        }

        $this->notifyFiltersUpdated();
    }

    public function updatedSelectedIds(): void
    {
        $this->selectedIds = array_values(array_filter(array_map('intval', (array) $this->selectedIds)));
        $filtered = $this->getFilteredRows();
        $filteredIds = array_values(array_filter(
            array_map(fn ($r) => (int) ($r['id'] ?? 0), $filtered),
            fn ($id) => $id > 0
        ));

        if (! empty($filteredIds) && count(array_intersect($filteredIds, $this->selectedIds)) === count($filteredIds)) {
            $this->selectAll = true;
        } else {
            $this->selectAll = false;
        }

        $this->notifyFiltersUpdated();
    }

    public function selectAllFiltered(): void
    {
        $filtered = $this->getFilteredRows();
        $this->selectedIds = array_values(array_filter(
            array_map(fn ($r) => (int) ($r['id'] ?? 0), $filtered),
            fn ($id) => $id > 0
        ));
        $this->selectAll = true;
        $this->notifyFiltersUpdated();
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->notifyFiltersUpdated();
    }

    public function notifyFiltersUpdated(): void
    {
        $this->dispatch('filters-updated', search: $this->search, device: $this->filterDevice, selectedIds: $this->selectedIds);
        $this->dispatch('search-updated', search: $this->search);
    }

    public function getFleetVehiclesProperty()
    {
        $query = Vehicle::with('device')->orderBy('equipment_code');

        if (! empty($this->manualVehicleSearch)) {
            $search = trim($this->manualVehicleSearch);
            $query->where(function ($q) use ($search) {
                $q->where('equipment_code', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('operator_driver', 'like', "%{$search}%")
                    ->orWhereHas('device', function ($dq) use ($search) {
                        $dq->where('device_name', 'like', "%{$search}%")
                            ->orWhere('imei', 'like', "%{$search}%");
                    });
            });
        }

        return $query->get();
    }

    public function getSelectedVehicleModelProperty(): ?Vehicle
    {
        if (! $this->manualVehicleId) {
            return null;
        }

        return Vehicle::with('device')->find($this->manualVehicleId);
    }

    public function openManualVehicleModal(?int $rowIndex = null): void
    {
        $this->manualRowIndex = $rowIndex;
        $this->manualVehicleSearch = '';
        $this->showManualVehicleModal = true;

        if ($rowIndex !== null && isset($this->rows[$rowIndex])) {
            $row = $this->rows[$rowIndex];
            $this->manualStartTime = $row['start_time'] ?? '';
            $this->manualEndTime = $row['end_time'] ?? '';
            $this->manualStayTime = $row['stay_time'] ?? '';
            $this->manualAddress = $row['address'] ?? '';
            $this->manualRemarks = $row['remarks'] ?? '';
            $this->manualState = $row['state'] ?: 'Idling';
            $this->manualReportDate = $row['report_date'] ?? ($this->endDate ?? now()->toDateString());

            $deviceName = $row['device_name'] ?? '';
            $matched = Vehicle::whereHas('device', fn ($q) => $q->where('device_name', $deviceName))
                ->orWhere('equipment_code', $deviceName)
                ->orWhere('plate_number', $deviceName)
                ->first();
            $this->manualVehicleId = $matched?->id;
        } else {
            $this->manualVehicleId = null;
            $this->manualStartTime = '';
            $this->manualEndTime = '';
            $this->manualStayTime = '';
            $this->manualAddress = '';
            $this->manualRemarks = '';
            $this->manualState = 'Idling';
            $this->manualFocus = true;
            $this->manualReportDate = $this->endDate ?? now()->toDateString();
        }

        $this->manualRangeStart = $this->startDate ?? now()->subDays(3)->toDateString();
        $this->manualRangeEnd = $this->endDate ?? now()->toDateString();
    }

    public function exploreVehicleMultiDate(): void
    {
        if (! $this->manualVehicleId) {
            return;
        }

        $vehicle = Vehicle::with('device')->find($this->manualVehicleId);
        if (! $vehicle) {
            return;
        }

        $deviceName = $vehicle->device?->device_name ?: ($vehicle->equipment_code ?: $vehicle->plate_number);
        $start = min($this->manualRangeStart ?? now()->subDays(3)->toDateString(), $this->manualRangeEnd ?? now()->toDateString());
        $end = max($this->manualRangeStart ?? now()->subDays(3)->toDateString(), $this->manualRangeEnd ?? now()->toDateString());

        if ($this->dateMode === 'range') {
            $this->startDate = $start;
            $this->endDate = $end;
            $this->filterDevice = $deviceName;
            $this->search = '';
            $this->closeManualVehicleModal();
            $this->loadRows();
            $this->notifyFiltersUpdated();
            $this->savedMessage = "Multi-Date Explorer loaded for {$vehicle->equipment_code} ({$start} to {$end}).";
        } else {
            $this->redirect(route('reports.range', [
                'start_date' => $start,
                'end_date' => $end,
                'device' => $deviceName,
            ]));
        }
    }

    public function closeManualVehicleModal(): void
    {
        $this->showManualVehicleModal = false;
        $this->manualRowIndex = null;
        $this->manualVehicleId = null;
        $this->manualVehicleSearch = '';
    }

    public function updatedManualVehicleId($value): void
    {
        if (! empty($value)) {
            $vehicle = Vehicle::with('device')->find($value);
            if ($vehicle) {
                if (empty($this->manualAddress) && ! empty($vehicle->location)) {
                    $this->manualAddress = $vehicle->location;
                }
                if (empty($this->manualState)) {
                    $this->manualState = 'Idling';
                }
            }
        }
    }

    public function updatedManualStartTime(): void
    {
        $this->recalcManualStayTime();
    }

    public function updatedManualEndTime(): void
    {
        $this->recalcManualStayTime();
    }

    protected function recalcManualStayTime(): void
    {
        if (! empty($this->manualStartTime) && ! empty($this->manualEndTime)) {
            $this->manualStayTime = LongIdlingRecord::calculateStayTime($this->manualStartTime, $this->manualEndTime) ?? '';
        }
    }

    public function focusOnVehicle(string $deviceName): void
    {
        $this->filterDevice = $deviceName;
        $this->search = '';
        $this->selectAll = false;
        $this->notifyFiltersUpdated();
    }

    public function clearFocus(): void
    {
        $this->filterDevice = 'all';
        $this->selectAll = false;
        $this->notifyFiltersUpdated();
    }

    public function addManualVehicle(): void
    {
        if (! $this->manualVehicleId) {
            return;
        }

        $vehicle = Vehicle::with('device')->find($this->manualVehicleId);
        if (! $vehicle) {
            return;
        }

        $deviceName = $vehicle->device?->device_name ?: ($vehicle->equipment_code ?: $vehicle->plate_number);
        $driverName = $vehicle->operator_driver ?: '';
        $imei = $vehicle->device?->imei ?: '';
        $model = $vehicle->device?->model ?: ($vehicle->model ?: '');
        $address = ! empty($this->manualAddress) ? $this->manualAddress : ($vehicle->location ?: '');

        $existingIndex = null;
        if ($this->manualRowIndex === null) {
            foreach ($this->rows as $idx => $r) {
                if (($r['device_name'] ?? '') === $deviceName) {
                    $existingIndex = $idx;
                    break;
                }
            }
        }

        if ($this->manualRowIndex !== null && isset($this->rows[$this->manualRowIndex])) {
            $idx = $this->manualRowIndex;
            $this->rows[$idx]['device_name'] = $deviceName;
            $this->rows[$idx]['driver_name'] = $driverName;
            $this->rows[$idx]['imei'] = $imei;
            $this->rows[$idx]['model'] = $model;
            if (! empty($this->manualStartTime)) {
                $this->rows[$idx]['start_time'] = $this->manualStartTime;
            }
            if (! empty($this->manualEndTime)) {
                $this->rows[$idx]['end_time'] = $this->manualEndTime;
            }
            if (! empty($this->manualStayTime)) {
                $this->rows[$idx]['stay_time'] = $this->manualStayTime;
            }
            if (! empty($address)) {
                $this->rows[$idx]['address'] = $address;
            }
            if (! empty($this->manualRemarks)) {
                $this->rows[$idx]['remarks'] = $this->manualRemarks;
            }
            $this->rows[$idx]['state'] = $this->manualState ?: 'Idling';
            $this->rows[$idx]['dirty'] = true;
            $message = "Row updated with {$vehicle->equipment_code}.";
        } elseif ($existingIndex !== null) {
            $idx = $existingIndex;
            if (! empty($this->manualStartTime)) {
                $this->rows[$idx]['start_time'] = $this->manualStartTime;
            }
            if (! empty($this->manualEndTime)) {
                $this->rows[$idx]['end_time'] = $this->manualEndTime;
            }
            if (! empty($this->manualStayTime)) {
                $this->rows[$idx]['stay_time'] = $this->manualStayTime;
            }
            if (! empty($address)) {
                $this->rows[$idx]['address'] = $address;
            }
            if (! empty($this->manualRemarks)) {
                $this->rows[$idx]['remarks'] = $this->manualRemarks;
            }
            $this->rows[$idx]['dirty'] = true;
            $message = "Vehicle {$vehicle->equipment_code} already exists in report &mdash; focused on this vehicle.";
        } else {
            $targetDate = ! empty($this->manualReportDate) ? $this->manualReportDate : ($this->endDate ?? now()->toDateString());
            $targetReportId = $this->reportId;

            $newRow = [
                'id' => null,
                'report_id' => $targetReportId,
                'report_date' => $targetDate,
                'formatted_date' => date('M j, Y', strtotime($targetDate)),
                'device_name' => $deviceName,
                'driver_name' => $driverName,
                'imei' => $imei,
                'model' => $model,
                'state' => $this->manualState ?: 'Idling',
                'start_time' => $this->manualStartTime ?: '',
                'end_time' => $this->manualEndTime ?: '',
                'stay_time' => $this->manualStayTime ?: '',
                'latitude' => '',
                'longitude' => '',
                'coordinates' => '',
                'address' => $address,
                'remarks' => $this->manualRemarks ?: '',
                'image' => null,
                'image_url' => null,
                'is_new' => true,
                'dirty' => true,
                '_key' => 'new-'.uniqid(),
            ];

            $targetReport = null;
            if (! empty($this->reportId) && $this->dateMode === 'single') {
                $targetReport = Report::find($this->reportId);
            }

            if (! $targetReport) {
                $targetReport = Report::firstOrCreate(
                    [
                        'report_type' => 'long_idling',
                        'report_date' => $targetDate,
                        'created_by' => auth()->id(),
                    ],
                    [
                        'status' => 'draft',
                    ]
                );
            }

            if ($targetReport) {
                $created = $targetReport->longIdlingRecords()->create([
                    'device_name' => $deviceName,
                    'driver_name' => $driverName,
                    'imei' => $imei,
                    'model' => $model,
                    'state' => $this->manualState ?: 'Idling',
                    'start_time' => $this->manualStartTime ?: null,
                    'end_time' => $this->manualEndTime ?: null,
                    'stay_time' => $this->manualStayTime ?: null,
                    'address' => $address,
                    'remarks' => $this->manualRemarks ?: null,
                    'sort_order' => count($this->rows),
                ]);
                $newRow['id'] = $created->id;
                $newRow['report_id'] = $targetReport->id;
                $newRow['report_date'] = $targetReport->report_date->format('Y-m-d');
                $newRow['formatted_date'] = $targetReport->report_date->format('M j, Y');
                $newRow['is_new'] = false;
                $newRow['dirty'] = false;
            }

            $this->rows[] = $newRow;
            $message = "Vehicle {$vehicle->equipment_code} added to report.";
        }

        if ($this->manualFocus) {
            $this->filterDevice = $deviceName;
            $this->search = '';
            $message .= ' Report is focused on this vehicle.';
        }

        $this->closeManualVehicleModal();
        $this->notifyFiltersUpdated();
        $this->savedMessage = $message;
    }

    public function getDeviceListProperty(): array
    {
        $devices = [];
        foreach ($this->rows as $row) {
            $name = trim($row['device_name'] ?? '');
            if ($name !== '') {
                $devices[$name] = ($devices[$name] ?? 0) + 1;
            }
        }
        if ($this->filterDevice !== 'all' && ! empty($this->filterDevice) && ! isset($devices[$this->filterDevice])) {
            $devices[$this->filterDevice] = 0;
        }
        ksort($devices, SORT_NATURAL | SORT_FLAG_CASE);

        return $devices;
    }

    public function getHasMultipleDatesProperty(): bool
    {
        $dates = array_unique(array_filter(array_column($this->rows, 'report_date')));

        return count($dates) > 1 || $this->dateMode === 'range';
    }

    public function getSelectedDatesSummaryProperty(): string
    {
        if (empty($this->selectedIds)) {
            return '';
        }

        $dates = [];
        foreach ($this->rows as $r) {
            if (in_array((int) ($r['id'] ?? 0), $this->selectedIds, true) && ! empty($r['formatted_date'])) {
                $dates[$r['formatted_date']] = true;
            }
        }

        $uniqueCount = count($dates);
        if ($uniqueCount <= 1) {
            return '';
        }

        return "across {$uniqueCount} dates (".implode(', ', array_keys($dates)).')';
    }

    public function getPdfUrlProperty(): string
    {
        $params = [];

        if (! empty($this->selectedIds)) {
            $params['ids'] = implode(',', $this->selectedIds);
        } else {
            if ($this->filterDevice !== 'all' && ! empty($this->filterDevice)) {
                $params['device'] = $this->filterDevice;
            }
            if (! empty($this->search)) {
                $params['search'] = $this->search;
            }
        }

        if ($this->sortBy !== 'default') {
            $params['sort'] = $this->sortBy;
        }

        if ($this->dateMode === 'range' || empty($this->reportId)) {
            if (empty($this->selectedIds)) {
                $params['start_date'] = $this->startDate;
                $params['end_date'] = $this->endDate;
            }

            return route('reports.generateRangePdf', $params);
        }

        $params['report'] = $this->reportId;

        return route('reports.generatePdf', $params);
    }

    private function recalcStayTime(int $index): void
    {
        $start = $this->rows[$index]['start_time'] ?? '';
        $end = $this->rows[$index]['end_time'] ?? '';
        $this->rows[$index]['stay_time'] = LongIdlingRecord::calculateStayTime($start, $end) ?? '';
    }

    public function saveAll(): void
    {
        $savedCount = 0;

        foreach ($this->rows as $index => &$row) {
            if (! ($row['dirty'] ?? false)) {
                continue;
            }

            if (empty($row['device_name'])) {
                continue;
            }

            $coords = trim($row['coordinates'] ?? '');
            $lat = null;
            $lng = null;
            if (! empty($coords)) {
                if (str_contains($coords, ',')) {
                    $parts = explode(',', $coords, 2);
                    $p0 = trim($parts[0]);
                    $p1 = trim($parts[1]);
                    $lat = is_numeric($p0) ? (float) $p0 : null;
                    $lng = is_numeric($p1) ? (float) $p1 : null;
                } elseif (preg_match('/^([-+]?\d*\.?\d+)\s+([-+]?\d*\.?\d+)$/', $coords, $m)) {
                    $lat = (float) $m[1];
                    $lng = (float) $m[2];
                } elseif (is_numeric($coords)) {
                    $lat = (float) $coords;
                }
            } elseif (! empty($row['latitude']) || ! empty($row['longitude'])) {
                $lat = is_numeric($row['latitude']) ? (float) $row['latitude'] : null;
                $lng = is_numeric($row['longitude']) ? (float) $row['longitude'] : null;
            }

            $row['latitude'] = $lat;
            $row['longitude'] = $lng;

            $data = [
                'device_name' => $row['device_name'],
                'driver_name' => $row['driver_name'] ?? null,
                'imei' => $row['imei'],
                'model' => $row['model'],
                'state' => $row['state'] ?: null,
                'start_time' => $row['start_time'] ?: null,
                'end_time' => $row['end_time'] ?: null,
                'stay_time' => $row['stay_time'] ?: null,
                'latitude' => $lat,
                'longitude' => $lng,
                'address' => $row['address'],
                'remarks' => $row['remarks'],
                'image' => $row['image'] ?? null,
                'sort_order' => $index,
            ];

            if (! empty($row['id'])) {
                LongIdlingRecord::where('id', $row['id'])->update($data);
            } else {
                $targetReport = null;
                if (! empty($row['report_id'])) {
                    $targetReport = Report::find($row['report_id']);
                } elseif (! empty($this->reportId)) {
                    $targetReport = Report::find($this->reportId);
                }

                if (! $targetReport) {
                    $targetDate = ! empty($row['report_date']) ? $row['report_date'] : ($this->endDate ?? now()->toDateString());
                    $targetReport = Report::firstOrCreate(
                        [
                            'report_type' => 'long_idling',
                            'report_date' => $targetDate,
                            'created_by' => auth()->id(),
                        ],
                        [
                            'status' => 'draft',
                        ]
                    );
                }

                $record = $targetReport->longIdlingRecords()->create($data);
                $row['id'] = $record->id;
                $row['report_id'] = $targetReport->id;
                $row['report_date'] = $targetReport->report_date->format('Y-m-d');
                $row['formatted_date'] = $targetReport->report_date->format('M j, Y');
                $row['is_new'] = false;
            }

            $row['dirty'] = false;
            $savedCount++;
        }

        unset($row);

        $this->savedMessage = $savedCount > 0
            ? "{$savedCount} record(s) saved successfully."
            : 'All records are up to date.';
        session()->flash('success', $this->savedMessage);
    }

    public function updateRowImage(int $index, string $imageUrl, string $imagePath): void
    {
        if (isset($this->rows[$index])) {
            $this->rows[$index]['image'] = $imagePath;
            $this->rows[$index]['image_url'] = $imageUrl;
            $this->rows[$index]['dirty'] = true;
        }
    }

    public function removeRowImage(int $index): void
    {
        if (isset($this->rows[$index])) {
            if (! empty($this->rows[$index]['image'])) {
                Storage::disk('public')->delete($this->rows[$index]['image']);
            }
            $this->rows[$index]['image'] = null;
            $this->rows[$index]['image_url'] = null;
            $this->rows[$index]['dirty'] = true;

            if (! empty($this->rows[$index]['id'])) {
                LongIdlingRecord::where('id', $this->rows[$index]['id'])->update(['image' => null]);
            }
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteConfirmId = $id;
        $this->deleteConfirmIndex = null;
    }

    public function confirmDeleteNew(int $index): void
    {
        $this->deleteConfirmIndex = $index;
        $this->deleteConfirmId = null;
    }

    public function cancelDelete(): void
    {
        $this->deleteConfirmId = null;
        $this->deleteConfirmIndex = null;
    }

    public function deleteRow(): void
    {
        if ($this->deleteConfirmId) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$this->deleteConfirmId]));
            $record = LongIdlingRecord::find($this->deleteConfirmId);
            if ($record) {
                if ($record->image) {
                    Storage::disk('public')->delete($record->image);
                }
                $record->delete();
            }
            $this->rows = array_values(
                array_filter($this->rows, fn ($r) => $r['id'] !== $this->deleteConfirmId)
            );
        } elseif ($this->deleteConfirmIndex !== null) {
            array_splice($this->rows, $this->deleteConfirmIndex, 1);
        }

        $this->deleteConfirmId = null;
        $this->deleteConfirmIndex = null;
        $this->notifyFiltersUpdated();
    }

    public function setSort(string $sort): void
    {
        $this->sortBy = $sort;
    }

    public function setFilterDuration(string $duration): void
    {
        $this->filterDuration = $duration;
        $this->selectAll = false;
        $this->notifyFiltersUpdated();
    }

    public function setFilterDevice(string $device): void
    {
        $this->filterDevice = $device;
        $this->selectAll = false;
        $this->notifyFiltersUpdated();
    }

    public function toggleSort(string $field): void
    {
        if ($field === 'stay_time') {
            $this->sortBy = match ($this->sortBy) {
                'stay_time_desc' => 'stay_time_asc',
                'stay_time_asc' => 'default',
                default => 'stay_time_desc',
            };
        } elseif ($field === 'device_name') {
            $this->sortBy = match ($this->sortBy) {
                'device_asc' => 'device_desc',
                'device_desc' => 'default',
                default => 'device_asc',
            };
        } elseif ($field === 'report_date' || $field === 'date') {
            $this->sortBy = match ($this->sortBy) {
                'date_desc' => 'date_asc',
                'date_asc' => 'default',
                default => 'date_desc',
            };
        }
    }

    public function parseStayTimeToSeconds(?string $stayTime): int
    {
        if (! $stayTime) {
            return 0;
        }

        $stayTime = trim($stayTime);
        $parts = explode(':', $stayTime);
        if (count($parts) === 3) {
            return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + (int) $parts[2];
        } elseif (count($parts) === 2) {
            return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60);
        }

        return 0;
    }

    public function getFilteredRows(): array
    {
        $result = [];
        foreach ($this->rows as $index => $row) {
            $row['_orig_index'] = $index;

            // Search filter
            if ($this->search) {
                $q = strtolower($this->search);
                $matches = str_contains(strtolower($row['device_name'] ?? ''), $q) ||
                    str_contains(strtolower($row['driver_name'] ?? ''), $q) ||
                    str_contains(strtolower($row['imei'] ?? ''), $q) ||
                    str_contains(strtolower($row['address'] ?? ''), $q) ||
                    str_contains(strtolower($row['model'] ?? ''), $q) ||
                    str_contains(strtolower($row['formatted_date'] ?? ''), $q) ||
                    str_contains(strtolower($row['report_date'] ?? ''), $q);

                if (! $matches) {
                    continue;
                }
            }

            // Device filter
            if ($this->filterDevice !== 'all' && $this->filterDevice !== '') {
                if (($row['device_name'] ?? '') !== $this->filterDevice) {
                    continue;
                }
            }

            // Duration filter
            if ($this->filterDuration !== 'all') {
                $seconds = $this->parseStayTimeToSeconds($row['stay_time'] ?? '');
                if ($this->filterDuration === '1h' && $seconds < 3600) {
                    continue;
                }
                if ($this->filterDuration === '2h' && $seconds < 7200) {
                    continue;
                }
                if ($this->filterDuration === '3h' && $seconds < 10800) {
                    continue;
                }
            }

            $result[] = $row;
        }

        // Apply Sorting
        if ($this->sortBy === 'stay_time_desc') {
            usort($result, function ($a, $b) {
                $secA = $this->parseStayTimeToSeconds($a['stay_time'] ?? '');
                $secB = $this->parseStayTimeToSeconds($b['stay_time'] ?? '');

                if ($secA === $secB) {
                    return ($a['_orig_index'] ?? 0) <=> ($b['_orig_index'] ?? 0);
                }

                return $secB <=> $secA; // Descending (Most stay time first)
            });
        } elseif ($this->sortBy === 'stay_time_asc') {
            usort($result, function ($a, $b) {
                $secA = $this->parseStayTimeToSeconds($a['stay_time'] ?? '');
                $secB = $this->parseStayTimeToSeconds($b['stay_time'] ?? '');

                // Put empty/0 stay times at the end
                if ($secA === 0 && $secB > 0) {
                    return 1;
                }
                if ($secB === 0 && $secA > 0) {
                    return -1;
                }

                if ($secA === $secB) {
                    return ($a['_orig_index'] ?? 0) <=> ($b['_orig_index'] ?? 0);
                }

                return $secA <=> $secB; // Ascending (Less stay time first)
            });
        } elseif ($this->sortBy === 'device_asc') {
            usort($result, function ($a, $b) {
                $cmp = strcasecmp($a['device_name'] ?? '', $b['device_name'] ?? '');
                if ($cmp === 0) {
                    return ($a['_orig_index'] ?? 0) <=> ($b['_orig_index'] ?? 0);
                }

                return $cmp; // Alphabetical A-Z
            });
        } elseif ($this->sortBy === 'device_desc') {
            usort($result, function ($a, $b) {
                $cmp = strcasecmp($b['device_name'] ?? '', $a['device_name'] ?? '');
                if ($cmp === 0) {
                    return ($a['_orig_index'] ?? 0) <=> ($b['_orig_index'] ?? 0);
                }

                return $cmp; // Alphabetical Z-A
            });
        } elseif ($this->sortBy === 'date_desc') {
            usort($result, function ($a, $b) {
                $cmp = strcmp($b['report_date'] ?? '', $a['report_date'] ?? '');
                if ($cmp === 0) {
                    return ($a['_orig_index'] ?? 0) <=> ($b['_orig_index'] ?? 0);
                }

                return $cmp;
            });
        } elseif ($this->sortBy === 'date_asc') {
            usort($result, function ($a, $b) {
                $cmp = strcmp($a['report_date'] ?? '', $b['report_date'] ?? '');
                if ($cmp === 0) {
                    return ($a['_orig_index'] ?? 0) <=> ($b['_orig_index'] ?? 0);
                }

                return $cmp;
            });
        }

        return $result;
    }

    public function render(): View
    {
        return view('livewire.long-idling-table', [
            'filteredRows' => $this->getFilteredRows(),
            'hasMultipleDates' => $this->hasMultipleDates,
            'selectedDatesSummary' => $this->selectedDatesSummary,
        ]);
    }
}
