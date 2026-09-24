<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SalesReportRequest;
use App\Models\Order;
use App\Models\Resto;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    public function __invoke(SalesReportRequest $request): View
    {
        $data = $this->data($request->validated());
        $items = $data['items'];
        $paidOrders = $data['paidOrders'];
        $restoId = $data['restoId'];
        $topProducts = (clone $items)->select('product_id', 'product_name', 'resto_id', 'resto_name')
            ->selectRaw($restoId === null ? 'SUM(qty) AS quantity, SUM(price * qty) AS revenue' : 'SUM(qty) AS quantity, SUM(subtotal) AS revenue')
            ->groupBy('product_id', 'product_name', 'resto_id', 'resto_name')
            ->orderByDesc('quantity')->orderByDesc('revenue')->orderBy('product_name')->limit(10)->get();
        $restoRevenue = (clone $items)->select('resto_id')->selectRaw('MAX(resto_name) AS resto_name, SUM(subtotal) AS revenue')
            ->groupBy('resto_id')->orderByDesc('revenue')->orderBy('resto_id')->get();
        $payments = $restoId === null
            ? (clone $paidOrders)->select('payment_type')->selectRaw('COUNT(*) AS transactions, SUM(total) AS revenue')->groupBy('payment_type')->get()->keyBy('payment_type')
            : (clone $items)->join('orders', 'orders.id', '=', 'order_items.order_id')->select('orders.payment_type')
                ->selectRaw('COUNT(DISTINCT orders.id) AS transactions, SUM(order_items.subtotal) AS revenue')->groupBy('orders.payment_type')->get()->keyBy('payment_type');
        $orders = (clone $paidOrders)->with('table')
            ->withSum(['items as item_quantity' => fn (Builder $query): Builder => $query->when($restoId !== null, fn (Builder $query): Builder => $query->where('resto_id', $restoId))], 'qty');
        if ($restoId !== null) {
            $orders->withSum(['items as resto_total' => fn (Builder $query): Builder => $query->where('resto_id', $restoId)], 'subtotal');
        }
        $orders = $orders->orderByDesc('payment_time')->orderByDesc('id')->paginate(15)->withQueryString();
        $exportFilters = array_filter([
            'period' => 'custom', 'start' => $data['start']->format('Y-m-d'), 'end' => $data['end']->format('Y-m-d'),
            'search' => $data['search'], 'resto' => $restoId,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return view('admin.reports.sales', array_merge($data, compact('topProducts', 'restoRevenue', 'payments', 'orders', 'exportFilters')));
    }

    public function export(SalesReportRequest $request): StreamedResponse
    {
        $data = $this->data($request->validated());
        $filename = 'sales-report-'.$data['start']->format('Y-m-d').'-'.$data['end']->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($data): void {
            $workbook = new Spreadsheet;
            try {
                $sheet = $workbook->getActiveSheet()->setTitle('Sales Report');
                $sheet->setCellValue('A1', 'Warmindo');
                $sheet->setCellValue('A2', 'Laporan Penjualan');
                $sheet->setCellValue('A4', 'Periode');
                $sheet->setCellValue('B4', $data['start']->format('d/m/Y').' - '.$data['end']->format('d/m/Y').' WIB');
                $sheet->setCellValue('A5', 'Resto');
                $restoName = $data['restoId'] === null ? 'Semua Resto' : $data['restos']->firstWhere('id', $data['restoId'])->resto_name;
                $sheet->setCellValueExplicit('B5', $restoName, DataType::TYPE_STRING);
                $sheet->setCellValue('A6', 'Generated At');
                $sheet->setCellValue('B6', now($data['timezone'])->format('d/m/Y H:i:s').' WIB');
                $sheet->setCellValue('A7', 'Pencarian');
                $sheet->setCellValueExplicit('B7', $data['search'], DataType::TYPE_STRING);
                $sheet->setCellValue('A8', 'Data sebelum snapshot menggunakan penyedia saat backfill development; histori lama tidak dapat dipastikan.');
                $sheet->setCellValue('A9', $data['restoId'] === null ? 'KPI omzet memakai total order; detail dan revenue resto memakai subtotal item.' : 'Revenue: subtotal item resto; rata-rata: revenue / transaksi distinct yang memuat resto.');
                foreach (['Total Revenue' => $data['summary']->revenue, 'Transactions' => $data['summary']->transactions, 'Items Sold' => $data['itemCount'], 'Average Transaction' => $data['summary']->average] as $label => $value) {
                    $row = 11 + array_search($label, ['Total Revenue', 'Transactions', 'Items Sold', 'Average Transaction']);
                    $sheet->setCellValue('A'.$row, $label);
                    $sheet->setCellValue('B'.$row, (float) $value);
                }
                $currency = '"Rp" #,##0.00';
                $sheet->getStyle('B11')->getNumberFormat()->setFormatCode($currency);
                $sheet->getStyle('B14')->getNumberFormat()->setFormatCode($currency);
                $sheet->fromArray(['Tanggal (WIB)', 'Customer', 'Meja', 'Resto', 'Produk', 'Qty', 'Harga', 'Subtotal', 'Metode Pembayaran'], null, 'A16');
                $details = (clone $data['items'])->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->leftJoin('tables', 'tables.id', '=', 'orders.table_id')
                    ->select('order_items.*', 'orders.payment_time', 'orders.customer_name', 'orders.payment_type', 'tables.table_no')
                    ->orderBy('orders.payment_time')->orderBy('orders.id')->orderBy('order_items.id');
                $row = 17;
                foreach ($details->cursor() as $item) {
                    $date = CarbonImmutable::parse($item->payment_time, config('app.timezone'))->setTimezone($data['timezone']);
                    $sheet->setCellValue('A'.$row, Date::PHPToExcel($date));
                    foreach (['B' => $item->customer_name ?: 'Nama belum tersedia', 'C' => str_pad((string) $item->table_no, 2, '0', STR_PAD_LEFT), 'D' => $item->resto_name ?? 'Belum ditentukan', 'E' => $item->product_name, 'I' => Order::PAYMENT_TYPES[$item->payment_type] ?? $item->payment_type ?? 'Belum tercatat'] as $column => $value) {
                        $sheet->setCellValueExplicit($column.$row, $value, DataType::TYPE_STRING);
                    }
                    $sheet->setCellValue('F'.$row, (int) $item->qty);
                    $sheet->setCellValue('G'.$row, (float) $item->price);
                    $sheet->setCellValue('H'.$row, (float) $item->subtotal);
                    $row++;
                }
                $lastRow = max(17, $row - 1);
                $sheet->getStyle('A17:A'.$lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
                $sheet->getStyle('G17:H'.$lastRow)->getNumberFormat()->setFormatCode($currency);
                $sheet->getStyle('A1:I2')->getFont()->setBold(true);
                $sheet->getStyle('A16:I16')->getFont()->setBold(true);
                foreach (['A' => 23, 'B' => 30, 'C' => 10, 'D' => 28, 'E' => 35, 'F' => 10, 'G' => 20, 'H' => 20, 'I' => 23] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $sheet->mergeCells('A8:I8')->mergeCells('A9:I9');
                $sheet->getStyle('A8:A9')->getAlignment()->setWrapText(true);
                $sheet->freezePane('A17');
                $sheet->setAutoFilter('A16:I'.max(16, $row - 1));
                (new Xlsx($workbook))->save('php://output');
            } finally {
                $workbook->disconnectWorksheets();
            }
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }

    /**
     * @param  array{period?: ?string, start?: ?string, end?: ?string, search?: ?string, resto?: int|string|null, page?: int|string|null}  $filters
     * @return array{period: string, start: CarbonImmutable, end: CarbonImmutable, search: string, timezone: string, restoId: ?int, restos: Collection, summary: object, itemCount: mixed, paidOrders: Builder, items: QueryBuilder}
     */
    private function data(array $filters): array
    {
        $period = $filters['period'] ?? 'today';
        $timezone = 'Asia/Jakarta';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        [$start, $end] = match ($period) {
            'yesterday' => [$today->subDay(), $today->subDay()],
            'last7' => [$today->subDays(6), $today],
            'month' => [$today->startOfMonth(), $today->endOfMonth()->startOfDay()],
            'custom' => [CarbonImmutable::parse($filters['start'], $timezone)->startOfDay(), CarbonImmutable::parse($filters['end'], $timezone)->startOfDay()],
            default => [$today, $today],
        };
        $search = trim($filters['search'] ?? '');
        $paidOrders = Order::where('payment_status', 'paid')->whereNotIn('order_status', ['cancelled', 'canceled'])
            ->where('payment_time', '>=', $start->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'))
            ->where('payment_time', '<', $end->addDay()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
        if ($search !== '') {
            $paidOrders->where(function (Builder $query) use ($search): void {
                $query->whereLike('customer_name', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $query->orWhereHas('table', fn (Builder $query): Builder => $query->where('table_no', (int) $search));
                }
            });
        }
        $restoId = isset($filters['resto']) ? (int) $filters['resto'] : null;
        $restos = Resto::orderBy('resto_name')->orderBy('id')->get(['id', 'resto_name']);
        if ($restoId !== null) {
            $paidOrders->whereHas('items', fn (Builder $query): Builder => $query->where('resto_id', $restoId));
        }
        $items = DB::table('order_items')->whereIn('order_id', (clone $paidOrders)->select('id'))
            ->when($restoId !== null, fn (QueryBuilder $query): QueryBuilder => $query->where('order_items.resto_id', $restoId));
        $summary = $restoId === null
            ? (clone $paidOrders)->selectRaw('COALESCE(SUM(total), 0) AS revenue, COUNT(*) AS transactions, COALESCE(AVG(total), 0) AS average')->first()
            : (clone $items)->selectRaw('COALESCE(SUM(subtotal), 0) AS revenue, COUNT(DISTINCT order_id) AS transactions, COALESCE(1.0 * SUM(subtotal) / NULLIF(COUNT(DISTINCT order_id), 0), 0) AS average')->first();
        $itemCount = (clone $items)->sum('qty');

        return compact('period', 'start', 'end', 'search', 'timezone', 'restoId', 'restos', 'summary', 'itemCount', 'paidOrders', 'items');
    }
}
