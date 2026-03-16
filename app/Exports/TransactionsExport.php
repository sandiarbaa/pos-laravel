<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

class TransactionsExport implements FromArray, WithTitle, WithEvents
{
    protected Collection $transactions;

    public function __construct(Collection $transactions)
    {
        $this->transactions = $transactions;
    }

    public function title(): string
    {
        return 'Laporan Transaksi';
    }

    // FromArray: isi semua baris sekaligus — Maatwebsite tulis dari row 1
    public function array(): array
    {
        $rows = [];

        // Row 1: Judul
        $rows[] = ['Laporan Transaksi GVI POS', '', '', '', '', '', '', '', ''];
        // Row 2: Subtitle
        $rows[] = ['Dicetak: ' . now()->format('d/m/Y H:i'), '', '', '', '', '', '', '', ''];
        // Row 3: Kosong
        $rows[] = ['', '', '', '', '', '', '', '', ''];

        // Row 4-6: Summary
        $totalPaid      = $this->transactions->where('status', 'paid')->count();
        $totalRevenue   = $this->transactions->where('status', 'paid')->sum('total');
        $totalCancelled = $this->transactions->where('status', 'cancelled')->count();

        $rows[] = ['Total Transaksi Berhasil', '', '', '', '', '', $totalPaid,      '', ''];
        $rows[] = ['Total Pendapatan (Rp)',    '', '', '', '', '', $totalRevenue,   '', ''];
        $rows[] = ['Total Dibatalkan',         '', '', '', '', '', $totalCancelled, '', ''];

        // Row 7: Kosong
        $rows[] = ['', '', '', '', '', '', '', '', ''];

        // Row 8: Header tabel
        $rows[] = ['No', 'Invoice', 'Tanggal', 'Kasir', 'Status', 'Metode', 'Total (Rp)', 'Alasan Batal', 'Item'];

        // Row 9+: Data
        foreach ($this->transactions->values() as $i => $t) {
            $items  = $t->items->map(fn($item) => "{$item->product_name} x{$item->quantity}")->join(', ');
            $status = $t->status === 'paid' ? 'Berhasil' : 'Dibatalkan';
            $rows[] = [
                $i + 1,
                $t->invoice_number,
                $t->created_at->format('d/m/Y H:i'),
                $t->user?->name ?? '-',
                $status,
                strtoupper($t->payment_method),
                $t->total,
                $t->cancel_reason ?? '-',
                $items,
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $total = $this->transactions->count();
                $lastDataRow = 8 + $total;

                // ── Column widths
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension('C')->setWidth(18);
                $sheet->getColumnDimension('D')->setWidth(18);
                $sheet->getColumnDimension('E')->setWidth(14);
                $sheet->getColumnDimension('F')->setWidth(12);
                $sheet->getColumnDimension('G')->setWidth(18);
                $sheet->getColumnDimension('H')->setWidth(24);
                $sheet->getColumnDimension('I')->setWidth(40);

                // ── Row 1: Judul
                $sheet->mergeCells('A1:I1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF1D4ED8']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);

                // ── Row 2: Subtitle
                $sheet->mergeCells('A2:I2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font'      => ['size' => 9, 'color' => ['argb' => 'FF888888']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(14);

                // ── Row 3: Kosong
                $sheet->getRowDimension(3)->setRowHeight(6);

                // ── Row 4-6: Summary styling
                $summaryColors = [4 => 'FF16A34A', 5 => 'FF1D4ED8', 6 => 'FFDC2626'];
                foreach ([4, 5, 6] as $row) {
                    $sheet->mergeCells("A{$row}:F{$row}");
                    $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 10],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    ]);
                    $sheet->getStyle("G{$row}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => $summaryColors[$row]]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    ]);
                    if ($row === 5) {
                        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0');
                    }
                    $sheet->getRowDimension($row)->setRowHeight(20);
                }

                // ── Row 7: Kosong
                $sheet->getRowDimension(7)->setRowHeight(6);

                // ── Row 8: Header tabel
                $sheet->getStyle('A8:I8')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1D4ED8']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF1A44B8']]],
                ]);
                $sheet->getRowDimension(8)->setRowHeight(22);

                // ── Row 9+: Data
                for ($i = 0; $i < $total; $i++) {
                    $row     = 9 + $i;
                    $bgColor = $i % 2 === 0 ? 'FFFFFFFF' : 'FFF8FAFC';
                    $t       = $this->transactions->values()->get($i);

                    // Base style
                    $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                        'font'      => ['size' => 10],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    ]);

                    // No: center
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    // Metode: center
                    $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    // Status: center + warna
                    $statusColor = $t->status === 'paid' ? 'FF16A34A' : 'FFDC2626';
                    $sheet->getStyle("E{$row}")->applyFromArray([
                        'font'      => ['bold' => true, 'color' => ['argb' => $statusColor]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    // Total: right + format
                    $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0');

                    $sheet->getRowDimension($row)->setRowHeight(18);
                }
            },
        ];
    }
}
