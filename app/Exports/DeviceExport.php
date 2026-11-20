<?php

namespace App\Exports;

use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DeviceExport implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Builder<Device>|null  $query
     */
    public function __construct(
        public ?Builder $query = null,
        public ?string $title = null,
    ) {}

    public function collection(): Collection
    {
        $query = $this->query
            ? clone $this->query
            : Device::where('created_by', auth()->id())->orderBy('device_name');

        $devices = $query->with('vehicle')->get();

        return $devices->map(function (Device $d) {
            $days = $d->days_until_expiration;

            $statusText = match (true) {
                $days === null => $d->raw_expiration ?: 'No Expiration Date',
                $days < 0 => $days === -1 && ! $d->expiration_date ? 'Expired' : 'Expired '.abs($days).' days ago',
                $days === 0 => 'Expires Today',
                $days <= 30 => "Expires in {$days} days (Renewal Required)",
                default => "Active ({$days} days left)",
            };

            return [
                'device_name' => $d->device_name,
                'vehicle_code' => $d->vehicle?->equipment_code ?? 'Unpaired',
                'plate_number' => $d->vehicle?->plate_number ?? '-',
                'driver' => $d->vehicle?->operator_driver ?? '-',
                'imei' => (string) ($d->imei ?? ''),
                'model' => $d->model ?? '',
                'sim' => (string) ($d->sim ?? ''),
                'expiration_date' => $d->expiration_date?->format('Y-m-d') ?? ($d->raw_expiration ?: 'N/A'),
                'status' => $statusText,
                'days_left' => $days !== null ? $days : '',
                'group' => $d->group_name ?? '',
                'iccid' => (string) ($d->iccid ?? ''),
                'imsi' => (string) ($d->imsi ?? ''),
                'mileage' => $d->mileage !== null ? (float) $d->mileage : null,
                'activated_date' => $d->activated_date?->format('Y-m-d') ?? '',
                'sales_time' => $d->sales_time?->format('Y-m-d') ?? '',
            ];
        });
    }

    /**
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'Device Name',
            'Linked Vehicle',
            'Plate Number',
            'Driver / Operator',
            'IMEI',
            'Model',
            'SIM Number',
            'Expiration Date',
            'Subscription Status',
            'Days Remaining',
            'Group',
            'ICCID',
            'IMSI',
            'Mileage (km)',
            'Activated Date',
            'Sales Time',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_TEXT, // IMEI as text
            'G' => NumberFormat::FORMAT_TEXT, // SIM as text
            'J' => NumberFormat::FORMAT_NUMBER, // Days remaining
            'L' => NumberFormat::FORMAT_TEXT, // ICCID as text
            'M' => NumberFormat::FORMAT_TEXT, // IMSI as text
            'N' => '#,##0.00', // Mileage
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F172A']],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 24, // Device Name
            'B' => 18, // Linked Vehicle
            'C' => 16, // Plate Number
            'D' => 22, // Driver / Operator
            'E' => 22, // IMEI
            'F' => 14, // Model
            'G' => 18, // SIM Number
            'H' => 18, // Expiration Date
            'I' => 32, // Subscription Status
            'J' => 16, // Days Remaining
            'K' => 22, // Group
            'L' => 24, // ICCID
            'M' => 20, // IMSI
            'N' => 16, // Mileage
            'O' => 16, // Activated Date
            'P' => 16, // Sales Time
        ];
    }

    public function title(): string
    {
        return substr($this->title ?? 'GPS Devices List', 0, 31);
    }
}
