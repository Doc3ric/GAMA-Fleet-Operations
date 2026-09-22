<?php

namespace App\Exports;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LocationExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function __construct(public ?Builder $query = null) {}

    public function collection(): Collection
    {
        $locations = $this->query
            ? $this->query->with('aliases')->get()
            : Location::with('aliases')->orderBy('official_name')->get();

        return $locations->map(function (Location $loc) {
            $aliasesStr = $loc->aliases->pluck('alias')->implode('; ');

            return [
                'code' => $loc->code,
                'official_name' => $loc->official_name,
                'type' => $loc->type,
                'aliases' => $aliasesStr,
                'latitude' => $loc->latitude,
                'longitude' => $loc->longitude,
                'address' => $loc->address,
                'barangay' => $loc->barangay ?? '',
                'municipality' => $loc->municipality ?? '',
                'province' => $loc->province ?? '',
                'status' => $loc->status,
                'notes' => $loc->notes ?? '',
            ];
        });
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
            'A' => 18, // Code
            'B' => 28, // Name
            'C' => 16, // Type
            'D' => 36, // Aliases
            'E' => 16, // Latitude
            'F' => 16, // Longitude
            'G' => 36, // Address
            'H' => 18, // Barangay
            'I' => 20, // Municipality
            'J' => 18, // Province
            'K' => 14, // Status
            'L' => 30, // Notes
        ];
    }

    public function title(): string
    {
        return 'Location Directory';
    }
}
