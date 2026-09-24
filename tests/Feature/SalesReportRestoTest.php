<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Resto;
use App\Models\Table;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class SalesReportRestoTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::drop('order_items');
        Schema::drop('orders');
        foreach (['2026_09_12_065707_create_orders_table.php', '2026_09_13_124020_create_order_items_table.php', '2026_09_16_024730_add_customer_name_to_orders_table.php', '2026_09_23_030903_add_resto_snapshot_to_order_items_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        config(['app.timezone' => 'UTC']);
        $this->travelTo(CarbonImmutable::parse('2026-09-23 03:00:00', 'UTC'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_mixed_order_revenue_chart_top_products_payments_and_history_use_only_selected_resto_items(): void
    {
        $a = Product::factory()->create(['resto_id' => Resto::factory()->create(['resto_name' => 'Resto A'])->id, 'product_name' => 'Nasi Goreng', 'price' => '10000.00']);
        $b = Product::factory()->create(['resto_id' => Resto::factory()->create(['resto_name' => 'Resto B'])->id, 'product_name' => 'Nasi Goreng', 'price' => '5000.00']);
        $order = $this->sale([[$a, 2], [$b, 1]]);
        $all = $this->get(route('admin.reports.sales'))->assertOk()->assertSee('Revenue per Resto');
        $this->assertSame(25000.0, (float) $all->viewData('summary')->revenue);
        $this->assertSame(1, (int) $all->viewData('summary')->transactions);
        $this->assertCount(2, $all->viewData('topProducts'));
        $this->assertSame([20000.0, 5000.0], $all->viewData('restoRevenue')->pluck('revenue')->map(fn ($amount): float => (float) $amount)->all());
        $all->assertSee('Resto A: Rp20.000,00')->assertSee('Resto B: Rp5.000,00');
        foreach ([[$a, 20000, 2], [$b, 5000, 1]] as [$product, $revenue, $qty]) {
            $response = $this->get(route('admin.reports.sales', ['resto' => $product->resto_id]))->assertOk()->assertSee('Revenue Resto');
            $this->assertSame((float) $revenue, (float) $response->viewData('summary')->revenue);
            $this->assertSame((float) $revenue, (float) $response->viewData('summary')->average);
            $this->assertSame(1, (int) $response->viewData('summary')->transactions);
            $this->assertSame($qty, (int) $response->viewData('itemCount'));
            $this->assertCount(1, $response->viewData('topProducts'));
            $this->assertSame($product->id, $response->viewData('topProducts')->first()->product_id);
            $this->assertSame((float) $revenue, (float) $response->viewData('payments')->get('cash')->revenue);
            $this->assertSame(1, (int) $response->viewData('payments')->get('cash')->transactions);
            $this->assertSame((float) $revenue, (float) $response->viewData('orders')->first()->resto_total);
            $this->assertSame($order->id, $response->viewData('orders')->first()->id);
            $this->assertCount(1, $response->viewData('restoRevenue'));
        }
    }

    public function test_distinct_orders_average_and_quantity_do_not_double_count_multiple_items(): void
    {
        $resto = Resto::factory()->create();
        $a = Product::factory()->create(['resto_id' => $resto->id, 'price' => '10000.00']);
        $b = Product::factory()->create(['resto_id' => $resto->id, 'price' => '5000.00']);
        $this->sale([[$a, 2], [$b, 1]]);
        $this->sale([[$b, 3]]);
        $response = $this->get(route('admin.reports.sales', ['resto' => $resto->id]))->assertOk();
        $this->assertSame(40000.0, (float) $response->viewData('summary')->revenue);
        $this->assertSame(20000.0, (float) $response->viewData('summary')->average);
        $this->assertSame(2, (int) $response->viewData('summary')->transactions);
        $this->assertSame(6, (int) $response->viewData('itemCount'));
        $this->assertSame(2, (int) $response->viewData('payments')->get('cash')->transactions);
    }

    public function test_unpaid_cancelled_dates_and_search_are_respected_with_resto_filter(): void
    {
        $product = Product::factory()->create(['resto_id' => Resto::factory()->create()->id]);
        $start = $this->sale([[$product, 1]], ['payment_time' => '2026-09-22 17:00:00']);
        $end = $this->sale([[$product, 1]], ['payment_time' => '2026-09-23 16:59:59']);
        foreach ([['payment_time' => '2026-09-22 16:59:59'], ['payment_time' => '2026-09-23 17:00:00'], ['payment_status' => 'unpaid'], ['payment_time' => null], ['order_status' => 'cancelled'], ['order_status' => 'canceled'], ['customer_name' => 'Other']] as $attributes) {
            $this->sale([[$product, 1]], $attributes);
        }
        $filters = ['resto' => $product->resto_id, 'period' => 'custom', 'start' => '2026-09-23', 'end' => '2026-09-23', 'search' => 'evan'];
        $response = $this->get(route('admin.reports.sales', $filters))->assertOk();
        $this->assertEqualsCanonicalizing([$start->id, $end->id], $response->viewData('orders')->pluck('id')->all());
        $this->assertSame(30000.0, (float) $response->viewData('summary')->revenue);
        $this->get(route('admin.reports.sales', array_replace($filters, ['search' => 'no-match'])))->assertOk()
            ->assertViewHas('summary', fn ($summary): bool => (float) $summary->revenue === 0.0 && (float) $summary->average === 0.0);
    }

    public function test_old_sales_stay_with_snapshot_after_product_moves_and_resto_is_renamed(): void
    {
        $resto = Resto::factory()->create(['resto_name' => 'Nama Lama']);
        $other = Resto::factory()->create(['resto_name' => 'Resto Baru']);
        $product = Product::factory()->create(['resto_id' => $resto->id]);
        $this->sale([[$product, 1]]);
        $product->update(['resto_id' => $other->id]);
        $resto->update(['resto_name' => 'Nama Baru']);
        $response = $this->get(route('admin.reports.sales', ['resto' => $resto->id]))->assertOk();
        $this->assertSame(15000.0, (float) $response->viewData('summary')->revenue);
        $this->assertSame('Nama Lama', $response->viewData('restoRevenue')->first()->resto_name);
        $this->get(route('admin.reports.sales', ['resto' => $other->id]))->assertViewHas('summary', fn ($summary): bool => (int) $summary->transactions === 0);
        $this->sale([[$product, 1]]);
        $this->assertCount(2, $this->get(route('admin.reports.sales'))->viewData('topProducts'));
    }

    public function test_all_resto_preserves_order_total_kpi_and_reports_unassigned_item_revenue(): void
    {
        $product = Product::factory()->create();
        $this->sale([[$product, 1]], ['total' => '17000.00']);
        $response = $this->get(route('admin.reports.sales'))->assertOk()->assertSee('Belum ditentukan');
        $this->assertSame(17000.0, (float) $response->viewData('summary')->revenue);
        $this->assertSame(15000.0, (float) $response->viewData('restoRevenue')->first()->revenue);
    }

    public function test_excel_contains_only_filtered_snapshot_items_and_safe_typed_cells(): void
    {
        $resto = Resto::factory()->create(['resto_name' => '=SUM(1,2)']);
        $product = Product::factory()->create(['resto_id' => $resto->id, 'product_name' => '=1+1', 'price' => '10000.00']);
        $other = Product::factory()->create(['resto_id' => Resto::factory()->create()->id, 'price' => '5000.00']);
        $this->sale([[$product, 2], [$other, 1]], ['customer_name' => '=Evan', 'payment_time' => '2026-09-22 17:00:00']);
        $this->sale([[$product, 1]], ['payment_time' => '2026-09-21 17:00:00']);
        $this->sale([[$product, 1]], ['customer_name' => 'Other']);
        $this->sale([[$product, 1]], ['customer_name' => '=Evan', 'payment_status' => 'unpaid']);
        $product->update(['resto_id' => $other->resto_id, 'product_name' => 'Changed']);
        $filters = ['resto' => $resto->id, 'period' => 'custom', 'start' => '2026-09-23', 'end' => '2026-09-23', 'search' => '=Evan'];
        $response = $this->get(route('admin.reports.sales.export', $filters))->assertOk()->assertDownload('sales-report-2026-09-23-2026-09-23.xlsx');
        $file = tempnam(sys_get_temp_dir(), 'warmindo-xlsx-');
        try {
            file_put_contents($file, $response->streamedContent());
            $workbook = IOFactory::load($file);
            try {
                $sheet = $workbook->getSheetByName('Sales Report');
                $this->assertNotNull($sheet);
                $this->assertSame(17, $sheet->getHighestDataRow());
                $this->assertSame(20000.0, (float) $sheet->getCell('B11')->getValue());
                $this->assertSame(1, (int) $sheet->getCell('B12')->getValue());
                $this->assertSame(2, (int) $sheet->getCell('B13')->getValue());
                $this->assertSame(20000.0, (float) $sheet->getCell('B14')->getValue());
                foreach (['B17' => '=Evan', 'C17' => '05', 'D17' => '=SUM(1,2)', 'E17' => '=1+1'] as $cell => $value) {
                    $this->assertSame($value, $sheet->getCell($cell)->getValue());
                    $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($cell)->getDataType());
                }
                $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('A17')->getDataType());
                $this->assertSame(20000.0, (float) $sheet->getCell('H17')->getValue());
                $this->assertSame(10000.0, (float) $sheet->getCell('G17')->getValue());
                $this->assertSame('A17', $sheet->getFreezePane());
                $this->assertSame('A16:I17', $sheet->getAutoFilter()->getRange());
            } finally {
                $workbook->disconnectWorksheets();
            }
        } finally {
            unlink($file);
        }
    }

    public function test_pagination_keeps_filters_and_export_ignores_page_limit(): void
    {
        $product = Product::factory()->create(['resto_id' => Resto::factory()->create()->id]);
        for ($index = 0; $index < 16; $index++) {
            $this->sale([[$product, 1]]);
        }
        $filters = ['resto' => $product->resto_id, 'search' => '05', 'period' => 'today'];
        $response = $this->get(route('admin.reports.sales', $filters))->assertOk();
        $this->assertCount(15, $response->viewData('orders'));
        $this->assertStringContainsString('resto='.$product->resto_id, $response->viewData('orders')->nextPageUrl());
        $this->assertSame('2026-09-23', $response->viewData('exportFilters')['start']);
        $this->assertSame('custom', $response->viewData('exportFilters')['period']);
        $this->assertArrayNotHasKey('page', $response->viewData('exportFilters'));
        $export = $this->get(route('admin.reports.sales.export', $filters + ['page' => 2]))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'warmindo-xlsx-');
        try {
            file_put_contents($file, $export->streamedContent());
            $workbook = IOFactory::load($file);
            $this->assertSame(32, $workbook->getActiveSheet()->getHighestDataRow());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($file);
        }
    }

    public function test_report_and_export_authorization_and_invalid_filters(): void
    {
        foreach (['admin', 'superAdmin', 'kasir'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['admin.reports.sales', 'admin.reports.sales.export'] as $route) {
                $response = $this->get(route($route));
                if ($role === 'kasir') {
                    $response->assertForbidden();
                } else {
                    $response->assertOk();
                }
            }
        }
        $this->actingAs(User::factory()->admin()->create());
        foreach (['admin.reports.sales', 'admin.reports.sales.export'] as $route) {
            foreach ([['resto' => 99999], ['resto' => 'abc'], ['resto' => ['bad']], ['period' => 'custom', 'start' => '2026-09-24', 'end' => '2026-09-23']] as $filters) {
                $this->getJson(route($route, $filters))->assertUnprocessable();
            }
        }
    }

    /** @param array<int, array{0: Product, 1: int}> $lines
     * @param  array<string, mixed>  $overrides
     */
    private function sale(array $lines, array $overrides = []): Order
    {
        $table = Table::factory()->create(['table_no' => 5]);
        $review = $this->post(route('customer.checkout.review', $table->qr_token), ['items' => array_map(fn (array $line): array => ['product_id' => $line[0]->id, 'quantity' => $line[1]], $lines)])->assertRedirect();
        $token = basename($review->headers->get('Location'));
        $this->post(route('customer.checkout.store', $table->qr_token), ['checkout_token' => $token, 'customer_name' => 'Evan', 'payment_type' => 'cash'])->assertRedirect();
        $order = Order::findOrFail($token);
        $order->forceFill(array_merge(['payment_status' => 'paid', 'payment_time' => '2026-09-23 03:00:00'], $overrides))->save();

        return $order->fresh();
    }
}
