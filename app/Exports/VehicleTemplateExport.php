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
                'equipment_code' => 'BH 5',
                'vehicle_type' => 'BACKHOE',
                'model' => 'CAT 320',
                'plate_number' => 'ABC-1234',
                'date_acquired' => '2024-01-15',
                'fuel' => '16-20 / LIT/HR',
                'status' => '0.8 / RUNNING',
                'location' => 'Project Site A',
                'project_code' => 'PRJ-2026-001',
                'operator_driver' => 'Juan Dela Cruz',
                'helper' => 'Pedro Santos',
                'gps_status' => 'YES',
            ],
            [
                'equipment_code' => 'DT 01',
                'vehicle_type' => 'DUMP TRUCK',
                'model' => 'HINO 700',
                'plate_number' => 'XYZ-5678',
                'date_acquired' => '2023-08-20',
                'fuel' => '25-30 / LIT/HR',
                'status' => '1.0 / RUNNING',
                'location' => 'Main Yard',
                'project_code' => 'PRJ-2026-002',
                'operator_driver' => 'Mario Gomez',
                'helper' => 'Jose Ramos',
                'gps_status' => 'FOR CHECKUP',
            ],
            [
                'equipment_code' => 'EX 01',
                'vehicle_type' => 'EXCAVATOR',
                'model' => 'KOMATSU PC200',
                'plate_number' => 'EFG-9012',
                'date_acquired' => '2022-05-10',
                'fuel' => '18 / LIT/HR',
                'status' => 'STANDBY',
                'location' => 'Project Site B',
                'project_code' => 'PRJ-2026-001',
                'operator_driver' => 'Roberto Diaz',
                'helper' => '',
                'gps_status' => 'NO',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'EQUIPMENT CODE',
            'VEHICLE TYPE',
            'MODEL',
            'PLATE NUMBER',
            'DATE ACQUIRED',
            'FUEL',
            'STATUS',
            'LOCATION',
            'PROJECT CODE',
            'OPERATOR/DRIVER',
            'HELPER',
            'GPS STATUS',
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
            'A' => 20, // EQUIPMENT CODE
            'B' => 20, // VEHICLE TYPE
            'C' => 20, // MODEL
            'D' => 18, // PLATE NUMBER
            'E' => 18, // DATE ACQUIRED
            'F' => 20, // FUEL
            'G' => 20, // STATUS
            'H' => 24, // LOCATION
            'I' => 20, // PROJECT CODE
            'J' => 24, // OPERATOR/DRIVER
            'K' => 20, // HELPER
            'L' => 18, // GPS STATUS
        ];
    }

    public function title(): string
    {
        return 'Vehicles Template';
    }
}
