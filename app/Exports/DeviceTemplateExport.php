<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DeviceTemplateExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function collection(): Collection
    {
        return collect([
            [
                'device_name' => 'GAJ-5943',
                'imei' => '865135060611447',
                'model' => 'X3',
                'activated_date' => '2025-04-25',
                'sales_time' => '2025-04-25',
                'sim' => '9982474024',
                'expiration_date' => '2026-10-04(Expires in 20 days)',
                'group' => 'Default Group',
                'iccid' => '89630324227008132231',
                'imsi' => '515039232540055',
                'mileage' => '19228.42',
            ],
            [
                'device_name' => 'KAR 7806',
                'imei' => '865135061409585',
                'model' => 'X3',
                'activated_date' => '2026-05-20',
                'sales_time' => '2026-05-20',
                'sim' => '9761426565',
                'expiration_date' => '2027-05-02',
                'group' => 'Default Group',
                'iccid' => '89630324227008132232',
                'imsi' => '515039232540056',
                'mileage' => '1429.62',
            ],
            [
                'device_name' => 'SV 18 - KAU 4688',
                'imei' => '865135061269385',
                'model' => 'X3',
                'activated_date' => '2025-12-04',
                'sales_time' => '2025-12-04',
                'sim' => '9327975048',
                'expiration_date' => '2026-12-05',
                'group' => 'LIVE OPERATION UNIT',
                'iccid' => '89630324227008132233',
                'imsi' => '515039232540057',
                'mileage' => '9648.35',
            ],
            [
                'device_name' => 'BT-06',
                'imei' => '864122050061396',
                'model' => 'VG01U',
                'activated_date' => '2021-10-13',
                'sales_time' => '2021-10-20',
                'sim' => '9660510062',
                'expiration_date' => '2026-10-04(Expires in 20 days)',
                'group' => 'Default Group',
                'iccid' => '89630324227008132234',
                'imsi' => '515039232540058',
                'mileage' => '73295.09',
            ],
            [
                'device_name' => 'test',
                'imei' => '865135063557668',
                'model' => 'X3',
                'activated_date' => '2022-06-23',
                'sales_time' => '2022-06-23',
                'sim' => '9660510502',
                'expiration_date' => 'Expired',
                'group' => 'Default Group',
                'iccid' => '89630324227008132235',
                'imsi' => '515039232540059',
                'mileage' => '14615.76',
            ],
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
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E40AF']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22, // Device Name
            'B' => 22, // IMEI
            'C' => 14, // Model
            'D' => 18, // Activated Date
            'E' => 18, // Sales Time
            'F' => 16, // SIM
            'G' => 30, // User Expiration Date
            'H' => 24, // Group
            'I' => 26, // ICCID
            'J' => 22, // IMSI
            'K' => 16, // Mileage
        ];
    }

    public function title(): string
    {
        return 'GPS Devices Template';
    }
}
