<?php

namespace App\Exports;

use App\Models\FleetDispatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DispatchPunctualityExport implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Builder<FleetDispatch>|Collection<int, FleetDispatch>|null  $source
     */
    public function __construct(protected mixed $source = null) {}

    public function collection(): Collection
    {
        $records = match (true) {
            $this->source instanceof Builder => $this->source->get(),
            $this->source instanceof Collection => $this->source,
            default => FleetDispatch::with('vehicle')->latest('trip_date')->get(),
        };

        return $records->map(function (FleetDispatch $dispatch) {
            $vehicle = $dispatch->vehicle;
            $eqpt = trim((string) ($vehicle?->equipment_code ?? ''));
            $plate = trim((string) ($vehicle?->plate_number ?? ''));
            $vehicleLabel = ($eqpt && $plate) ? "{$eqpt} - {$plate}" : ($eqpt ?: ($plate ?: '—'));

            return [
                'trip_number' => $dispatch->trip_number,
                'trip_date' => $dispatch->trip_date?->format('Y-m-d') ?? '—',
                'vehicle' => $vehicleLabel,
                'driver' => $dispatch->driver_name ?? '—',
                'starting_point' => $dispatch->starting_point,
                'destination' => $dispatch->destination,
                'planned_departure' => $dispatch->planned_departure_time?->format('Y-m-d H:i') ?? '—',
                'actual_departure' => $dispatch->actual_departure_time?->format('Y-m-d H:i') ?? '—',
                'delay_minutes' => $dispatch->delay_minutes.' min',
                'status' => $dispatch->dispatch_status,
                'source' => $dispatch->departure_source ? str_replace('_', ' ', strtoupper($dispatch->departure_source)) : '—',
                'planned_distance' => number_format($dispatch->planned_distance_km, 2).' KM',
            ];
        });
    }

    /**
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'TRIP NUMBER',
            'DATE',
            'VEHICLE',
            'DRIVER',
            'STARTING POINT',
            'DESTINATION',
            'PLANNED DEPARTURE',
            'ACTUAL DEPARTURE',
            'DELAY',
            'DISPATCH STATUS',
            'DEPARTURE SOURCE',
            'PLANNED KM',
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
            'A' => 18,
            'B' => 14,
            'C' => 22,
            'D' => 20,
            'E' => 20,
            'F' => 20,
            'G' => 18,
            'H' => 18,
            'I' => 12,
            'J' => 16,
            'K' => 18,
            'L' => 14,
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
        return 'Dispatch Punctuality';
    }
}
