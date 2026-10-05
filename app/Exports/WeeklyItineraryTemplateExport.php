<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class WeeklyItineraryTemplateExport implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    public function array(): array
    {
        return [
            // Row 1: Title
            ['', '', '', '', 'WEEKLY ITINERARY REPORT', '', '', '', ''],
            // Row 2: Blank
            ['', '', '', '', '', '', '', '', ''],
            // Row 3: Driver Name
            ['NAME:', 'BRANDON LEE', '', '', '', '', '', '', ''],
            // Row 4: Week
            ['FOR THE WEEK:', 'SEPTEMBER 15, 2026', '', '', '', '', '', '', ''],
            // Row 5: Plate / EQT
            ['PLATE NUMBER:', 'IV 5 - LAO 8559', '', '', '', '', '', '', ''],
            // Row 6: Blank
            ['', '', '', '', '', '', '', '', ''],
            // Row 7: Table Headers
            ['DATE', 'ORIGIN', 'START ODO', 'DESTINATION', 'END ODO', 'DISTANCE', 'TIME IN', 'TIME OUT', 'PURPOSE'],
            // Row 8: Sample row
            ['09-09-2026', 'VHEL', 4330, 'DP BALOY', 4339, 9, '9:20 PM', '9:30 PM', 'PICKUP DRIED FISH'],
            // Rows 9 to 17: Blank rows for entry
            ['', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            // Row 18: Blank
            ['', '', '', '', '', '', '', '', ''],
            // Row 19: Summary block
            ['TOTAL DISTANCE', '=SUM(F8:F17)', '', '', '', '', '', '', ''],
            // Row 20
            ['AVE. CON. OF LITER/KM', 3.7, '', '', '', '', '', '', ''],
            // Row 21
            ['LITER FOR PO', '=IF(B20>0, ROUNDUP(B19/B20, 0), 0)', '', '', '', '', '', '', ''],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 20,
            'C' => 14,
            'D' => 24,
            'E' => 14,
            'F' => 14,
            'G' => 14,
            'H' => 14,
            'J' => 30,
            'I' => 30,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Title Styling (Row 1)
                $sheet->mergeCells('A1:I1');
                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E293B']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                // 2. Metadata Labels (Rows 3, 4, 5)
                foreach ([3, 4, 5] as $rowNum) {
                    $sheet->getStyle("A{$rowNum}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '334155']],
                    ]);
                    $sheet->getStyle("B{$rowNum}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '0F172A']],
                        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]],
                    ]);
                }

                // 3. Table Column Headers (Row 7)
                $sheet->getStyle('A7:I7')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']], // Cobalt Blue
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(7)->setRowHeight(24);

                // 4. Data rows styling & borders (Rows 8 to 17)
                $sheet->getStyle('A8:I17')->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Center align numeric & time columns
                $sheet->getStyle('A8:A17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C8:C17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('E8:E17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('F8:F17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('G8:H17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // 5. Summary block styling (Rows 19 to 21)
                $sheet->getStyle('A19:B21')->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']],
                    ],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                ]);
                $sheet->getStyle('A19:A21')->getFont()->setBold(true);
                $sheet->getStyle('B19:B21')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '1E40AF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);
            },
        ];
    }

    public function title(): string
    {
        return 'ITINERARY';
    }
}
