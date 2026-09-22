<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LocationTemplateExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function collection(): Collection
    {
        return collect([
            [
                'code' => 'G2',
                'official_name' => 'GAMA FARM 2',
                'type' => 'Farm',
                'aliases' => 'G2; GAMA 2; FARM 2; GAMA FARM2; G2 FARM',
                'latitude' => 14.599512,
                'longitude' => 120.984222,
                'address' => 'KM 45 MacArthur Highway, Sample Address',
                'barangay' => 'San Juan',
                'municipality' => 'Malolos City',
                'province' => 'Bulacan',
                'status' => 'Active',
                'notes' => 'Sample test entry. Multiple aliases separated by semicolon (;)',
            ],
            [
                'code' => 'CWH',
                'official_name' => 'CENTRAL WAREHOUSE',
                'type' => 'Warehouse',
                'aliases' => 'CWH; WAREHOUSE; MAIN BODEGA; BODEGA',
                'latitude' => 14.650711,
                'longitude' => 121.049444,
                'address' => 'Industrial Valley Complex',
                'barangay' => 'Bagong Silang',
                'municipality' => 'Quezon City',
                'province' => 'Metro Manila',
                'status' => 'Active',
                'notes' => 'Sample test entry for warehouse and depot',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'Location Code',
            'Official Location Name',
            'Type',
            'Aliases (separated by ;)',
            'Latitude',
            'Longitude',
            'Address',
            'Barangay',
            'Municipality / City',
            'Province',
            'Status',
            'Notes',
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
            'A' => 18,
            'B' => 28,
            'C' => 16,
            'D' => 36,
            'E' => 16,
            'F' => 16,
            'G' => 36,
            'H' => 18,
            'I' => 20,
            'J' => 18,
            'K' => 14,
            'L' => 30,
        ];
    }

    public function title(): string
    {
        return 'Location Import Template';
    }
}
