<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SalesReportRequest;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalesReportController extends Controller
{
    public function __invoke(SalesReportRequest $request): View
    {
        $filters = $request->validated();
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
        $summary = (clone $paidOrders)->selectRaw('COALESCE(SUM(total), 0) AS revenue, COUNT(*) AS transactions, COALESCE(AVG(total), 0) AS average')->first();
        $items = DB::table('order_items')->whereIn('order_id', (clone $paidOrders)->select('id'));
        $itemCount = (clone $items)->sum('qty');
        $topProducts = (clone $items)->select('product_id', 'product_name')
            ->selectRaw('SUM(qty) AS quantity, SUM(price * qty) AS revenue')
            ->groupBy('product_id', 'product_name')->orderByDesc('quantity')->orderByDesc('revenue')->orderBy('product_name')->limit(10)->get();
        $payments = (clone $paidOrders)->select('payment_type')->selectRaw('COUNT(*) AS transactions, SUM(total) AS revenue')
            ->groupBy('payment_type')->get()->keyBy('payment_type');
        $orders = (clone $paidOrders)->with('table')->withSum('items as item_quantity', 'qty')
            ->orderByDesc('payment_time')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.reports.sales', compact('period', 'start', 'end', 'search', 'timezone', 'summary', 'itemCount', 'topProducts', 'payments', 'orders'));
    }
}
