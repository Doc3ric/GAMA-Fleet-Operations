<?php

namespace App\Exports;

use App\Models\DriverTrip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DriverItineraryExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Builder<DriverTrip>|Collection<int, DriverTrip>|null  $source
     */
    public function __construct(protected mixed $source = null) {}

    public function collection(): Collection
    {
        $trips = match (true) {
            $this->source instanceof Builder => $this->source->get(),
            $this->source instanceof Collection => $this->source,
            default => DriverTrip::with(['driver', 'vehicle'])->orderByDesc('trip_date')->orderByDesc('time_in')->get(),
        };

        return $trips->map(function (DriverTrip $trip) {
            $originText = $trip->origin_address
                ?: ($trip->origin_latitude && $trip->origin_longitude
                    ? 'Coordinates: '.number_format($trip->origin_latitude, 5).', '.number_format($trip->origin_longitude, 5)
                    : 'Coordinates Available');

            $destText = $trip->destination_address
                ?: ($trip->destination_latitude && $trip->destination_longitude
                    ? 'Coordinates: '.number_format($trip->destination_latitude, 5).', '.number_format($trip->destination_longitude, 5)
                    : ($trip->isInProgress() ? 'In Progress' : 'Coordinates Available'));

            $vehicleText = $trip->vehicle
                ? ($trip->vehicle->equipment_code.($trip->vehicle->plate_number ? " ({$trip->vehicle->plate_number})" : ''))
                : 'N/A';

            return [
                'date' => $trip->trip_date?->format('Y-m-d') ?? 'N/A',
                'driver' => $trip->driver?->name ?? 'N/A',
                'vehicle' => $vehicleText,
                'origin' => $originText,
                'destination' => $destText,
                'time_in' => $trip->time_in ?? 'N/A',
                'time_out' => $trip->time_out ?? ($trip->isInProgress() ? 'In Progress' : 'N/A'),
                'status' => $trip->status,
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
            'Driver',
            'Vehicle',
            'Origin',
            'Destination',
            'Time In',
            'Time Out',
            'Status',
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
            'B' => 24, // Driver
            'C' => 22, // Vehicle
            'D' => 38, // Origin
            'E' => 38, // Destination
            'F' => 14, // Time In
            'G' => 14, // Time Out
            'H' => 16, // Status
        ];
    }

    public function title(): string
    {
        return 'Driver Itinerary';
    }
}
