<?php

namespace App\Exports;

use App\Models\Report;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LongIdlingExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function __construct(protected Report $report, protected ?string $sort = null) {}

    public function collection(): Enumerable
    {
        $query = $this->report->longIdlingRecords();

        if ($this->sort === 'stay_time_desc') {
            $query->orderByDesc('stay_time');
        } elseif ($this->sort === 'stay_time_asc') {
            $query->orderBy('stay_time');
        } elseif ($this->sort === 'device_asc') {
            $query->orderBy('device_name');
        } elseif ($this->sort === 'device_desc') {
            $query->orderByDesc('device_name');
        } else {
            $query->orderBy('sort_order')->orderBy('id');
        }

        return $query->get()->map(fn ($r, $i) => [
            'no' => $i + 1,
            'device_name' => $r->device_name,
            'imei' => $r->imei,
            'model' => $r->model,
            'state' => $r->state,
            'start_time' => $r->start_time,
            'end_time' => $r->end_time,
            'stay_time' => $r->stay_time,
            'latitude' => $r->latitude,
            'longitude' => $r->longitude,
            'address' => $r->address,
            'remarks' => $r->remarks,
        ]);
    }

    public function headings(): array
    {
        return [
            '#',
            'Device Name',
            'IMEI',
            'Model',
            'State',
            'Start Time',
            'End Time',
            'Stay Time',
            'Latitude',
            'Longitude',
            'Address',
            'Remarks',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1e40af']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 25,
            'C' => 20,
            'D' => 16,
            'E' => 14,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 14,
            'J' => 14,
            'K' => 40,
            'L' => 30,
        ];
    }

    public function title(): string
    {
        return 'Long Idling '.$this->report->report_date->format('Y-m-d');
    }
}
