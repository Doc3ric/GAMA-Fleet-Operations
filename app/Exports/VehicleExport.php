<?php

namespace App\Exports;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VehicleExport implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Builder|Collection<int, Vehicle>|null  $source
     */
    public function __construct(protected mixed $source = null) {}

    public function collection(): Collection
    {
        $vehicles = match (true) {
            $this->source instanceof Builder => $this->source->with(['vehicleType', 'fuelConsumptionTests'])->get(),
            $this->source instanceof Collection => $this->source,
            default => Vehicle::with(['vehicleType', 'fuelConsumptionTests'])->orderBy('equipment_code')->get(),
        };

        return $vehicles->map(fn (Vehicle $v) => [
            'equipment_code' => $v->equipment_code,
            'model' => $v->model ?? '',
            'operator_driver' => $v->operator_driver ?? '',
            'plate_number' => $v->plate_number ?? '',
            'user' => $v->user ?? '',
            'project_code' => $v->project_code ?? '',
            'average_consumption' => $v->average_consumption ? (float) $v->average_consumption : null,
            'fuel_limit' => $v->fuel_limit ? (float) $v->fuel_limit : null,
            'vehicle_type' => $v->vehicleType?->name ?? '',
            'location' => $v->location ?? '',
            'status' => $v->status_display ?? '',
            'fuel_rate' => $v->fuel_display ?? '',
            'gps_status' => $v->gps_status_label ?? $v->gps_status ?? '',
            'date_acquired' => $v->date_acquired?->format('Y-m-d') ?? '',
            'helper' => $v->helper ?? '',
            'notes' => $v->notes ?? '',
        ]);
    }

    /**
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'EQTP CODE',
            'MODEL',
            'DRIVER NAME',
            'PLATE NUMBER',
            'USER',
            'PROJECT CODE',
            'AVERAGE CONSUMPTION',
            'FUEL LIMIT',
            'VEHICLE TYPE',
            'LOCATION',
            'STATUS',
            'FUEL RATE',
            'GPS STATUS',
            'DATE ACQUIRED',
            'HELPER',
            'NOTES',
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
            'A' => 18, // EQTP CODE
            'B' => 20, // MODEL
            'C' => 24, // DRIVER NAME
            'D' => 18, // PLATE NUMBER
            'E' => 20, // USER
            'F' => 20, // PROJECT CODE
            'G' => 24, // AVERAGE CONSUMPTION
            'H' => 20, // FUEL LIMIT
            'I' => 20, // VEHICLE TYPE
            'J' => 22, // LOCATION
            'K' => 20, // STATUS
            'L' => 20, // FUEL RATE
            'M' => 16, // GPS STATUS
            'N' => 18, // DATE ACQUIRED
            'O' => 22, // HELPER
            'P' => 30, // NOTES
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'G' => '#,##0.00',
            'H' => '#,##0.00',
        ];
    }

    public function title(): string
    {
        return 'Vehicle Master List';
    }
}
