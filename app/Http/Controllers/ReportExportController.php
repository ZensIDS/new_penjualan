<?php

namespace App\Http\Controllers;

use App\Services\Export\ExcelStyler;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Semua export Excel laporan dikumpulkan di sini supaya:
 * - ReportController tetap fokus untuk render halaman/view saja.
 * - Logika "resolve range tanggal" & styling Excel tidak ditulis ulang per laporan.
 */
class ReportExportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function stock()
    {
        $data = $this->reportService->stockReport();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Stok');

        $colSpan = 7;
        $row = ExcelStyler::title($sheet, 'Laporan Stok', 'Dicetak: ' . now()->translatedFormat('d M Y, H:i'), $colSpan);

        $totalQty = $data->sum('total_qty');
        $totalValue = $data->sum('stock_value');

        $row = ExcelStyler::summaryBlock($sheet, $row, [
            ['label' => 'Jumlah Produk Terdaftar', 'value' => $data->count(), 'format' => ExcelStyler::FMT_NUMBER],
            ['label' => 'Total Unit Stok', 'value' => $totalQty, 'format' => ExcelStyler::FMT_NUMBER],
            ['label' => 'Total Nilai Stok', 'value' => $totalValue, 'format' => ExcelStyler::FMT_RP, 'highlight' => true],
        ], $colSpan);

        $note = 'Setiap produk dijabarkan per batch stok (FIFO) — tanggal masuk, harga beli, qty masuk, dan sisa qty-nya masing-masing.';
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", $note);
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => ExcelStyler::COLOR_INK_SOFT]],
        ]);
        $row += 2;

        $row = ExcelStyler::header($sheet, $row, ['Produk / Batch', 'Kategori', 'Tgl Batch Masuk', 'Harga Beli', 'Qty Masuk', 'Qty Saat Ini', 'Nilai']);

        if ($data->isEmpty()) {
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", 'Tidak ada data produk.');
            $row++;
        } else {
            foreach ($data as $product) {
                // Baris ringkasan produk (bold, background abu-abu muda).
                $sheet->setCellValue("A{$row}", $product['name']);
                $sheet->setCellValue("B{$row}", $product['category'] ?? '-');
                $sheet->setCellValue("C{$row}", count($product['batches']) . ' batch');
                $sheet->setCellValue("D{$row}", '');
                $sheet->setCellValue("E{$row}", '');
                $sheet->setCellValue("F{$row}", $product['total_qty']);
                $sheet->setCellValue("G{$row}", $product['stock_value']);

                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => ExcelStyler::COLOR_INK]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExcelStyler::COLOR_GRAY_LIGHT]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => ExcelStyler::COLOR_BORDER]]],
                ]);
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(ExcelStyler::FMT_NUMBER);
                $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode(ExcelStyler::FMT_RP);
                $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $row++;

                // Baris per batch (indented, italic, lebih kecil) — rincian FIFO produk ini.
                if (count($product['batches']) === 0) {
                    $sheet->mergeCells("A{$row}:G{$row}");
                    $sheet->setCellValue("A{$row}", 'Tidak ada batch stok tersisa untuk produk ini.');
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => ExcelStyler::COLOR_INK_SOFT]],
                        'alignment' => ['indent' => 2],
                    ]);
                    $row++;
                } else {
                    foreach ($product['batches'] as $batch) {
                        $sheet->setCellValue("A{$row}", '   Batch');
                        $sheet->setCellValue("B{$row}", '');
                        $sheet->setCellValue("C{$row}", Carbon::parse($batch['batch_date'])->format('d-m-Y'));
                        $sheet->setCellValue("D{$row}", $batch['buy_price']);
                        $sheet->setCellValue("E{$row}", $batch['qty_in']);
                        $sheet->setCellValue("F{$row}", $batch['qty_remaining']);
                        $sheet->setCellValue("G{$row}", $batch['qty_remaining'] * $batch['buy_price']);

                        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font' => ['italic' => true, 'size' => 9.5, 'color' => ['rgb' => ExcelStyler::COLOR_INK_SOFT]],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => ExcelStyler::COLOR_BORDER]]],
                        ]);
                        $sheet->getStyle("A{$row}")->getAlignment()->setIndent(2);
                        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(ExcelStyler::FMT_RP);
                        $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(ExcelStyler::FMT_NUMBER);
                        $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(ExcelStyler::FMT_NUMBER);
                        $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode(ExcelStyler::FMT_RP);
                        $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("G{$row}")->getFont()->setItalic(true);
                        $row++;
                    }
                }
            }

            ExcelStyler::totalsRow($sheet, $row, ['Total (semua produk)', '', '', '', '', $totalQty, $totalValue], currencyCols: [7], numberCols: [6]);
            $row++;
        }

        ExcelStyler::setColumnWidths($sheet, [26, 16, 16, 14, 12, 12, 18]);

        return ExcelStyler::download($spreadsheet, 'laporan-stok-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function profitLoss(Request $request)
    {
        [$start, $end] = $this->resolveRange($request);
        $data = $this->reportService->profitLossReport($start, $end);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laba Rugi');

        $colSpan = 2;
        $period = 'Periode: ' . Carbon::parse($start)->translatedFormat('d M Y') . ' - ' . Carbon::parse($end)->translatedFormat('d M Y');
        $row = ExcelStyler::title($sheet, 'Laporan Laba Rugi', $period, $colSpan);

        $row = ExcelStyler::summaryBlock($sheet, $row, [
            ['label' => 'Pendapatan Penjualan (diterima)', 'value' => $data['revenue_paid'], 'format' => ExcelStyler::FMT_RP],
            ['label' => 'Pendapatan Tertahan (belum dibayar)', 'value' => $data['revenue_pending'], 'format' => ExcelStyler::FMT_RP],
            ['label' => 'HPP (FIFO)', 'value' => $data['hpp'], 'format' => ExcelStyler::FMT_RP],
            ['label' => 'Laba Kotor', 'value' => $data['gross_profit'], 'format' => ExcelStyler::FMT_RP],
            ['label' => 'Biaya Operasional', 'value' => $data['operational_expense'], 'format' => ExcelStyler::FMT_RP],
            ['label' => 'Laba Bersih', 'value' => $data['net_profit'], 'format' => ExcelStyler::FMT_RP, 'highlight' => true],
        ], $colSpan);

        $row = ExcelStyler::sectionTitle($sheet, $row, 'Biaya Operasional per Kategori', $colSpan);
        $row = ExcelStyler::header($sheet, $row, ['Kategori', 'Jumlah']);

        $expenseRows = $data['expense_by_category']->map(fn($e) => [$e->name, (float) $e->total]);
        $tableStart = $row;
        $row = ExcelStyler::rows($sheet, $row, $expenseRows, currencyCols: [2]);

        if ($expenseRows->isEmpty()) {
            $sheet->mergeCells("A{$tableStart}:B{$tableStart}");
            $sheet->setCellValue("A{$tableStart}", 'Tidak ada biaya operasional pada periode ini.');
        } else {
            ExcelStyler::totalsRow($sheet, $row, ['Total', $data['operational_expense']], currencyCols: [2]);
        }

        ExcelStyler::setColumnWidths($sheet, [42, 22]);

        return ExcelStyler::download($spreadsheet, "laporan-laba-rugi-{$start}_sd_{$end}.xlsx");
    }

    public function cashFlow(Request $request)
    {
        [$start, $end] = $this->resolveRange($request);
        $data = $this->reportService->cashFlowReport($start, $end);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Arus Kas');

        $colSpan = 4;
        $period = 'Periode: ' . Carbon::parse($start)->translatedFormat('d M Y') . ' - ' . Carbon::parse($end)->translatedFormat('d M Y');
        $row = ExcelStyler::title($sheet, 'Laporan Arus Kas', $period, $colSpan);

        $row = ExcelStyler::summaryBlock($sheet, $row, [
            ['label' => 'Kas Masuk', 'value' => $data['total_in'], 'format' => ExcelStyler::FMT_RP],
            ['label' => 'Kas Keluar', 'value' => $data['total_out'], 'format' => ExcelStyler::FMT_RP],
            ['label' => 'Kas Bersih', 'value' => $data['net_cash'], 'format' => ExcelStyler::FMT_RP, 'highlight' => true],
        ], $colSpan);

        $row = ExcelStyler::header($sheet, $row, ['Tanggal', 'Keterangan', 'Arah', 'Jumlah']);

        $details = $data['details']->sortByDesc('transaction_date')->map(fn($r) => [
            $r->transaction_date->format('d-m-Y'),
            $r->description,
            $r->direction === 'in' ? 'Masuk' : 'Keluar',
            $r->direction === 'in' ? $r->amount : -$r->amount,
        ]);

        $tableStart = $row;
        $row = ExcelStyler::rows($sheet, $row, $details, currencyCols: [4], centerCols: [3]);

        if ($details->isEmpty()) {
            $sheet->mergeCells("A{$tableStart}:D{$tableStart}");
            $sheet->setCellValue("A{$tableStart}", 'Tidak ada transaksi kas pada periode ini.');
        } else {
            ExcelStyler::totalsRow($sheet, $row, ['Total', '', '', $data['net_cash']], currencyCols: [4]);
        }

        ExcelStyler::setColumnWidths($sheet, [14, 44, 12, 18]);

        return ExcelStyler::download($spreadsheet, "laporan-arus-kas-{$start}_sd_{$end}.xlsx");
    }

    public function payable()
    {
        $data = $this->reportService->accountPayableReport();

        [$spreadsheet, $sheet] = $this->buildDebtTypeSheet(
            title: 'Laporan Hutang (Account Payable)',
            countLabel: 'Jumlah PO Belum Lunas',
            totalLabel: 'Total Sisa Hutang',
            headers: ['No. PO', 'Supplier', 'Tgl PO', 'Total PO', 'Dibayar', 'Sisa', 'Status'],
            numberKey: 'po_number',
            partyKey: 'supplier',
            dateKey: 'po_date',
            data: $data,
        );

        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('Riwayat Pembayaran');
        $this->buildPaymentHistorySheet($detailSheet, $data, 'No. PO', 'po_number', 'Supplier', 'supplier');

        return ExcelStyler::download($spreadsheet, 'laporan-hutang-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function receivable()
    {
        $data = $this->reportService->accountReceivableReport();

        [$spreadsheet, $sheet] = $this->buildDebtTypeSheet(
            title: 'Laporan Piutang (Account Receivable)',
            countLabel: 'Jumlah SO Belum Lunas',
            totalLabel: 'Total Sisa Piutang',
            headers: ['No. SO', 'Customer', 'Tgl SO', 'Total SO', 'Diterima', 'Sisa', 'Status'],
            numberKey: 'so_number',
            partyKey: 'customer',
            dateKey: 'so_date',
            data: $data,
        );

        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('Riwayat Pembayaran');
        $this->buildPaymentHistorySheet($detailSheet, $data, 'No. SO', 'so_number', 'Customer', 'customer');

        return ExcelStyler::download($spreadsheet, 'laporan-piutang-' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Bangun sheet utama untuk laporan AP/AR — bentuknya sama persis, cuma beda label & key.
     */
    private function buildDebtTypeSheet(
        string $title,
        string $countLabel,
        string $totalLabel,
        array $headers,
        string $numberKey,
        string $partyKey,
        string $dateKey,
        $data,
    ): array {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ringkasan');

        $colSpan = count($headers);
        $row = ExcelStyler::title($sheet, $title, 'Dicetak: ' . now()->translatedFormat('d M Y, H:i'), $colSpan);

        $totalOutstanding = $data->sum('remaining_balance');
        $totalAmount = $data->sum('total_amount');
        $totalPaid = $data->sum('paid_amount');

        $row = ExcelStyler::summaryBlock($sheet, $row, [
            ['label' => $countLabel, 'value' => $data->count(), 'format' => ExcelStyler::FMT_NUMBER],
            ['label' => $totalLabel, 'value' => $totalOutstanding, 'format' => ExcelStyler::FMT_RP, 'highlight' => true],
        ], $colSpan);

        $row = ExcelStyler::header($sheet, $row, $headers);

        $statusLabel = fn($s) => $s === 'partial' ? 'Sebagian' : 'Belum Bayar';

        $tableStart = $row;
        $row = ExcelStyler::rows($sheet, $row, $data->map(fn($item) => [
            $item[$numberKey],
            $item[$partyKey],
            Carbon::parse($item[$dateKey])->format('d-m-Y'),
            $item['total_amount'],
            $item['paid_amount'],
            $item['remaining_balance'],
            $statusLabel($item['payment_status']),
        ]), currencyCols: [4, 5, 6], centerCols: [7]);

        if ($data->isEmpty()) {
            $sheet->mergeCells('A' . $tableStart . ':' . ExcelStyler::colLetter($colSpan) . $tableStart);
            $sheet->setCellValue("A{$tableStart}", 'Tidak ada data tertunggak. Semua sudah lunas.');
        } else {
            ExcelStyler::totalsRow($sheet, $row, ['Total', '', '', $totalAmount, $totalPaid, $totalOutstanding, ''], currencyCols: [4, 5, 6]);
        }

        ExcelStyler::setColumnWidths($sheet, [18, 28, 14, 18, 18, 18, 14]);

        return [$spreadsheet, $sheet];
    }

    /**
     * Sheet kedua: histori pembayaran per dokumen (PO/SO), diratakan jadi satu tabel.
     */
    private function buildPaymentHistorySheet($sheet, $data, string $numberLabel, string $numberKey, string $partyLabel, string $partyKey): void
    {
        $headers = [$numberLabel, $partyLabel, 'Tgl Bayar', 'Metode', 'Jumlah', 'Catatan'];
        $row = ExcelStyler::title($sheet, 'Riwayat Pembayaran', 'Dicetak: ' . now()->translatedFormat('d M Y, H:i'), count($headers));
        $row = ExcelStyler::header($sheet, $row, $headers);

        $historyRows = [];
        foreach ($data as $item) {
            foreach ($item['payment_history'] as $payment) {
                $historyRows[] = [
                    $item[$numberKey],
                    $item[$partyKey],
                    Carbon::parse($payment['payment_date'])->format('d-m-Y'),
                    $payment['method'],
                    $payment['amount'],
                    $payment['note'] ?? '-',
                ];
            }
        }

        if (empty($historyRows)) {
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", 'Belum ada riwayat pembayaran.');
        } else {
            ExcelStyler::rows($sheet, $row, $historyRows, currencyCols: [5]);
        }

        ExcelStyler::setColumnWidths($sheet, [18, 28, 14, 16, 18, 30]);
    }

    /**
     * Sama seperti ReportController::resolveRange() — dipisah di sini supaya
     * export controller ini berdiri sendiri tanpa perlu mewarisi ReportController.
     */
    private function resolveRange(Request $request): array
    {
        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfMonth();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start->toDateString(), $end->toDateString()];
    }
}
