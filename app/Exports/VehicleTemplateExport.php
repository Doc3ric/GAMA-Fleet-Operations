<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VehicleTemplateExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function collection(): Collection
    {
        return collect([
            [
                'equipment_code' => 'SV 12',
                'model' => 'D-MAX',
                'driver_name' => 'JOSEPH HENEDO',
                'plate_number' => 'KAF 6079',
                'user' => 'PURCHASING',
                'project_code' => 'UTILITY VAN',
                'average_consumption' => '3.00',
            ],
            [
                'equipment_code' => 'DT 01',
                'model' => 'HINO 700',
                'driver_name' => 'MARIO GOMEZ',
                'plate_number' => 'XYZ-5678',
                'user' => 'OPERATIONS',
                'project_code' => 'HAULING',
                'average_consumption' => '2.50',
            ],
            [
                'equipment_code' => 'BH 5',
                'model' => 'CAT 320',
                'driver_name' => 'JUAN DELA CRUZ',
                'plate_number' => 'ABC-1234',
                'user' => 'ENGINEERING',
                'project_code' => 'EXCAVATION',
                'average_consumption' => '1.80',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'EGTP CODE',
            'MODEL',
            'DRIVER NAME',
            'PLATE NUMBER',
            'USER',
            'PROJECT CODE',
            'AVERAGE CONSUMPTION',
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
            'A' => 18, // EGTP CODE
            'B' => 18, // MODEL
            'C' => 24, // DRIVER NAME
            'D' => 18, // PLATE NUMBER
            'E' => 18, // USER
            'F' => 20, // PROJECT CODE
            'G' => 24, // AVERAGE CONSUMPTION
        ];
    }

    public function title(): string
    {
        return 'Registered Vehicles Template';
    }
}
