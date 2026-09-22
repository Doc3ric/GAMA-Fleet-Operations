<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LongIdlingTemplateExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function collection(): Collection
    {
        return collect([
            [
                'device_name' => 'CG - KAP 8003',
                'imei' => '865968052144112',
                'model' => 'JM01',
                'state' => 'Idling',
                'start_time' => '18:41:28',
                'end_time' => '20:27:16',
                'coordinates' => '8.472287, 124.654321',
                'address' => 'CDO Eastern Interior Road, Lapasan, Cagayan de Oro, Philippines',
                'stay_time' => '01:45:48',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'Device Name',
            'IMEI',
            'Model',
            'State',
            'Start time',
            'End Time',
            'Coordinates',
            'Address',
            'Stay time',
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
            'A' => 25, // Device Name
            'B' => 22, // IMEI
            'C' => 16, // Model
            'D' => 14, // State
            'E' => 16, // Start time
            'F' => 16, // End Time
            'G' => 24, // Coordinates
            'H' => 45, // Address
            'I' => 16, // Stay time
        ];
    }

    public function title(): string
    {
        return 'Template';
    }
}
