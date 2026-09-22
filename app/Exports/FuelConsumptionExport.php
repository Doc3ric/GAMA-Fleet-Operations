<?php

namespace App\Exports;

use App\Models\FuelConsumptionTest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FuelConsumptionExport implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Builder<FuelConsumptionTest>|Collection<int, FuelConsumptionTest>|null  $source
     */
    public function __construct(protected mixed $source = null) {}

    public function collection(): Collection
    {
        $records = match (true) {
            $this->source instanceof Builder => $this->source->get(),
            $this->source instanceof Collection => $this->source,
            default => FuelConsumptionTest::with(['vehicle', 'driver'])->orderByDesc('test_date')->orderByDesc('id')->get(),
        };

        return $records->map(function (FuelConsumptionTest $test) {
            return [
                'date' => $test->test_date?->format('Y-m-d') ?? 'N/A',
                'equipment_code' => $test->equipment_code_display,
                'plate_number' => $test->plate_number_display ?? 'N/A',
                'model' => $test->model_display ?? 'N/A',
                'driver' => $test->driver_display_name,
                'start_odometer' => (float) $test->start_odometer,
                'end_odometer' => (float) $test->end_odometer,
                'distance_travelled' => (float) $test->distance_travelled,
                'fuel_consumed_liters' => (float) $test->fuel_consumed_liters,
                'average_fuel_consumption' => (float) $test->average_fuel_consumption,
                'test_route' => $test->test_route ?? '—',
                'remarks' => $test->remarks ?? '—',
                'attested_by' => $test->attested_by ?? '—',
                'requested_by' => $test->requested_by ?? '—',
            ];
        });
    }

    /**
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'Date',
            'Equipment Code',
            'Plate Number',
            'Model',
            'Driver / Operator',
            'Start Odometer (km)',
            'End Odometer (km)',
            'Distance Travelled (km)',
            'Fuel Consumed (2nd Full Tank) (L)',
            'Average Fuel Consumption (KM/L)',
            'Test Route',
            'Remarks',
            'Attested By',
            'Requested By',
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
            'A' => 14, // Date
            'B' => 18, // Equipment Code
            'C' => 16, // Plate Number
            'D' => 20, // Model
            'E' => 24, // Driver / Operator
            'F' => 20, // Start Odometer
            'G' => 20, // End Odometer
            'H' => 22, // Distance Travelled
            'I' => 26, // Fuel Consumed (L)
            'J' => 26, // Average Fuel Consumption (KM/L)
            'K' => 30, // Test Route
            'L' => 30, // Remarks
            'M' => 22, // Attested By
            'N' => 22, // Requested By
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'I' => '#,##0.000',
            'J' => '#,##0.00',
        ];
    }

    public function title(): string
    {
        return 'Average Fuel Consumption';
    }
}
