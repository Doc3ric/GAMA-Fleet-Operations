<?php

namespace App\Exports;

use App\Models\AdvancedItinerary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FuelPoExport implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Builder<AdvancedItinerary>|Collection<int, AdvancedItinerary>|null  $source
     */
    public function __construct(protected mixed $source = null) {}

    public function collection(): Collection
    {
        $records = match (true) {
            $this->source instanceof Builder => $this->source->get(),
            $this->source instanceof Collection => $this->source,
            default => AdvancedItinerary::with([
                'vehicle',
                'legs.destination',
                'creator',
                'poChecker',
            ])->orderByDesc('itinerary_date')->orderByDesc('id')->get(),
        };

        return $records->map(function (AdvancedItinerary $itinerary) {
            $vehicle = $itinerary->vehicle;
            $avgConsumption = $vehicle?->average_consumption;
            $fuelLiters = $itinerary->fuel_liters;

            $checklistSymbol = $itinerary->po_checked ? '☑' : '☐';

            return [
                'equipment_code' => $vehicle?->equipment_code ?? '—',
                'model' => $vehicle?->model ?? '—',
                'driver' => $itinerary->driver_name,
                'plate_number' => $vehicle?->plate_number ?? '—',
                'user' => $vehicle?->user ?? '—',
                'project_code' => $vehicle?->project_code ?? '—',
                'destination' => $itinerary->destination_name,
                'distance' => number_format($itinerary->total_distance, 2).' KM',
                'avg_consumption' => $avgConsumption !== null ? number_format($avgConsumption, 2).' KM/L' : '—',
                'liter_for_po' => $fuelLiters !== null ? ((float) $fuelLiters == round($fuelLiters) ? number_format($fuelLiters, 0) : number_format($fuelLiters, 2)).' L' : '—',
                'checklist' => $checklistSymbol,
            ];
        });
    }

    /**
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'EQPT CODE',
            'MODEL',
            'DRIVER',
            'PLATE',
            'USER',
            'PROJECT',
            'DESTINATION',
            'DISTANCE',
            'AVG CONSUMPTION',
            'LITER FOR PO',
            'CHECKLIST',
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
            'A' => 16, // EQPT CODE
            'B' => 18, // MODEL
            'C' => 24, // DRIVER
            'D' => 16, // PLATE
            'E' => 18, // USER
            'F' => 18, // PROJECT
            'G' => 30, // DESTINATION
            'H' => 16, // DISTANCE
            'I' => 20, // AVG CONSUMPTION
            'J' => 16, // LITER FOR PO
            'K' => 14, // CHECKLIST
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Fuel PO Checklist';
    }
}
