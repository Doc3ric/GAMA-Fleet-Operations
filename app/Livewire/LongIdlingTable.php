<?php

namespace App\Livewire;

use App\Models\LongIdlingRecord;
use App\Models\Report;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;

class LongIdlingTable extends Component
{
    public int $reportId;

    public array $rows = [];

    public ?int $deleteConfirmId = null;

    public ?int $deleteConfirmIndex = null; // for unsaved rows

    public string $search = '';

    public string $sortBy = 'default';

    public string $filterDuration = 'all';

    public ?string $savedMessage = null;

    public function getHasDirtyProperty(): bool
    {
        foreach ($this->rows as $row) {
            if (! empty($row['dirty'])) {
                return true;
            }
        }

        return false;
    }

    public function mount(int $reportId): void
    {
        $this->reportId = $reportId;
        $this->loadRows();
    }

    public function loadRows(): void
    {
        $this->rows = LongIdlingRecord::where('report_id', $this->reportId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'device_name' => $r->device_name ?? '',
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

        $this->rows[] = [
            'id' => null,
            'device_name' => '',
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
        [$index, $field] = explode('.', $key, 2);
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

    private function recalcStayTime(int $index): void
    {
        $start = $this->rows[$index]['start_time'] ?? '';
        $end = $this->rows[$index]['end_time'] ?? '';
        $this->rows[$index]['stay_time'] = LongIdlingRecord::calculateStayTime($start, $end) ?? '';
    }

    public function saveAll(): void
    {
        $report = Report::findOrFail($this->reportId);
        $savedCount = 0;

        foreach ($this->rows as $index => &$row) {
            if (! $row['dirty']) {
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

            if ($row['id']) {
                LongIdlingRecord::where('id', $row['id'])->update($data);
            } else {
                $record = $report->longIdlingRecords()->create($data);
                $row['id'] = $record->id;
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
    }

    public function setSort(string $sort): void
    {
        $this->sortBy = $sort;
    }

    public function setFilterDuration(string $duration): void
    {
        $this->filterDuration = $duration;
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
                    str_contains(strtolower($row['imei'] ?? ''), $q) ||
                    str_contains(strtolower($row['address'] ?? ''), $q) ||
                    str_contains(strtolower($row['model'] ?? ''), $q);

                if (! $matches) {
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
        }

        return $result;
    }

    public function render(): View
    {
        return view('livewire.long-idling-table', [
            'filteredRows' => $this->getFilteredRows(),
        ]);
    }
}
