<?php

namespace App\Exports;

use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DeviceExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function __construct(public ?Builder $query = null) {}

    public function collection(): Collection
    {
        $devices = $this->query
            ? $this->query->get()
            : Device::where('created_by', auth()->id())->orderBy('device_name')->get();

        return $devices->map(fn (Device $d) => [
            'device_name' => $d->device_name,
            'imei' => $d->imei,
            'model' => $d->model,
            'activated_date' => $d->activated_date?->format('Y-m-d'),
            'sales_time' => $d->sales_time?->format('Y-m-d'),
            'sim' => $d->sim,
            'expiration_date' => $d->expiration_date
                ? $d->expiration_date->format('Y-m-d').($d->days_until_expiration !== null ? " ({$d->expiration_badge['label']})" : '')
                : ($d->raw_expiration ?: 'N/A'),
            'group' => $d->group_name,
            'iccid' => $d->iccid,
            'imsi' => $d->imsi,
            'mileage' => $d->mileage,
        ]);
    }

    public function headings(): array
    {
        return [
            'Device Name',
            'IMEI',
            'Model',
            'Activated Date',
            'Sales Time',
            'SIM',
            'User Expiration Date',
            'Group',
            'ICCID',
            'IMSI',
            'Mileage',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F172A']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22,
            'B' => 22,
            'C' => 14,
            'D' => 18,
            'E' => 18,
            'F' => 16,
            'G' => 32,
            'H' => 24,
            'I' => 26,
            'J' => 22,
            'K' => 16,
        ];
    }

    public function title(): string
    {
        return 'GPS Devices List';
    }
}
