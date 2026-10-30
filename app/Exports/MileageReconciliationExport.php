<?php

namespace App\Exports;

use App\Models\MileageReconciliation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MileageReconciliationExport implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Builder<MileageReconciliation>|Collection<int, MileageReconciliation>|null  $source
     */
    public function __construct(protected mixed $source = null) {}

    public function collection(): Collection
    {
        $records = match (true) {
            $this->source instanceof Builder => $this->source->get(),
            $this->source instanceof Collection => $this->source,
            default => MileageReconciliation::with(['dispatch', 'vehicle', 'reviewer'])->latest('trip_date')->get(),
        };

        return $records->map(function (MileageReconciliation $rec) {
            $vehicle = $rec->vehicle;
            $eqpt = trim((string) ($vehicle?->equipment_code ?? ''));
            $plate = trim((string) ($vehicle?->plate_number ?? ''));
            $vehicleLabel = ($eqpt && $plate) ? "{$eqpt} - {$plate}" : ($eqpt ?: ($plate ?: '—'));

            return [
                'reconciliation_number' => $rec->reconciliation_number,
                'trip_number' => $rec->dispatch?->trip_number ?? '—',
                'trip_date' => $rec->trip_date?->format('Y-m-d') ?? '—',
                'vehicle' => $vehicleLabel,
                'gps_equipped' => $rec->is_gps_vehicle ? 'YES' : 'NO',
                'starting_point' => $rec->dispatch?->starting_point ?? '—',
                'destination' => $rec->dispatch?->destination ?? '—',
                'planned_km' => number_format($rec->planned_distance_km, 2).' KM',
                'start_odo' => $rec->starting_odometer !== null ? number_format($rec->starting_odometer, 2) : '—',
                'end_odo' => $rec->ending_odometer !== null ? number_format($rec->ending_odometer, 2) : '—',
                'actual_odo_km' => number_format($rec->actual_odometer_km, 2).' KM',
                'gps_km' => $rec->gps_reported_km !== null ? number_format($rec->gps_reported_km, 2).' KM' : '—',
                'odo_gps_diff' => $rec->odometer_gps_difference_km !== null ? number_format($rec->odometer_gps_difference_km, 2).' KM' : '—',
                'variance_km' => ($rec->variance_km > 0 ? '+' : '').number_format($rec->variance_km, 2).' KM',
                'variance_pct' => $rec->variance_percentage !== null ? ($rec->variance_percentage > 0 ? '+' : '').number_format($rec->variance_percentage, 1).'%' : '—',
                'has_exceptions' => $rec->has_exceptions ? 'FLAGGED' : 'NORMAL',
                'status' => $rec->status,
                'reviewer' => $rec->reviewer?->name ?? '—',
            ];
        });
    }

    /**
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'REC NUMBER',
            'TRIP NUMBER',
            'DATE',
            'VEHICLE',
            'GPS',
            'STARTING POINT',
            'DESTINATION',
            'PLANNED KM',
            'START ODO',
            'END ODO',
            'ACTUAL KM',
            'GPS KM',
            'ODO-GPS DIFF',
            'VARIANCE KM',
            'VARIANCE %',
            'EXCEPTIONS',
            'STATUS',
            'REVIEWER',
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
            'B' => 18,
            'C' => 14,
            'D' => 22,
            'E' => 8,
            'F' => 20,
            'G' => 20,
            'H' => 14,
            'I' => 14,
            'J' => 14,
            'K' => 14,
            'L' => 14,
            'M' => 16,
            'N' => 16,
            'O' => 14,
            'P' => 14,
            'Q' => 14,
            'R' => 20,
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
        return 'Mileage Reconciliations';
    }
}
