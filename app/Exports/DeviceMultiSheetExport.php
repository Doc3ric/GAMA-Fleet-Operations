<?php

namespace App\Exports;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DeviceMultiSheetExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function __construct(
        public int $userId,
        public ?string $search = null,
        public ?string $group = null,
        public ?string $model = null,
    ) {}

    /**
     * @return array<int, DeviceExport>
     */
    public function sheets(): array
    {
        // 1. Already Expired
        $expiredQuery = Device::where('created_by', $this->userId)->expired()->orderBy('expiration_date', 'desc');
        $this->applyFilters($expiredQuery);

        // 2. Expiring Soon (<= 30 days)
        $expiringQuery = Device::where('created_by', $this->userId)->expiringSoon(30)->orderBy('expiration_date', 'asc');
        $this->applyFilters($expiringQuery);

        // 3. All Devices
        $allQuery = Device::where('created_by', $this->userId);
        $this->applyFilters($allQuery);
        $threshold = Carbon::today()->addDays(30)->toDateString();
        $allQuery->orderByRaw('CASE WHEN expiration_date IS NOT NULL AND expiration_date <= ? THEN 0 ELSE 1 END', [$threshold])
            ->orderBy('expiration_date', 'asc')
            ->orderBy('device_name', 'asc');

        return [
            new DeviceExport($expiredQuery, 'Already Expired'),
            new DeviceExport($expiringQuery, 'Expiring in ≤ 30 Days'),
            new DeviceExport($allQuery, 'All Devices'),
        ];
    }

    /**
     * @param  Builder<Device>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        if ($this->search) {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('device_name', 'like', "%{$search}%")
                    ->orWhere('imei', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('sim', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%")
                    ->orWhere('iccid', 'like', "%{$search}%")
                    ->orWhere('imsi', 'like', "%{$search}%");
            });
        }

        if ($this->group) {
            $query->where('group_name', $this->group);
        }

        if ($this->model) {
            $query->where('model', $this->model);
        }
    }
}
