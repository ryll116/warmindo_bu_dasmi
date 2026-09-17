<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SalesReportTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
        config(['app.timezone' => 'UTC']);
        $this->travelTo(CarbonImmutable::parse('2026-09-16 03:00:00', 'UTC'));
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_name')->nullable();
            $table->string('order_status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_type')->nullable();
            $table->dateTime('payment_time')->nullable();
            $table->decimal('total', 20, 2)->default(0);
            $table->timestamps();
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->uuid('order_id');
            $table->string('product_name');
            $table->decimal('price', 12, 2);
            $table->integer('qty');
            $table->decimal('subtotal', 20, 2);
        });
    }

    public function test_paid_only_summary_snapshot_ranking_and_wib_boundaries(): void
    {
        $product = Product::factory()->create(['product_name' => 'Nama sekarang', 'price' => '99999.00']);
        $this->sale($product, '2026-09-15 17:00:00', 2);
        $this->sale($product, '2026-09-16 16:59:59', 3, ['payment_type' => 'qris_manual']);
        $this->sale($product, '2026-09-16 03:00:00', 10, ['payment_status' => 'unpaid']);
        $this->sale($product, '2026-09-16 03:00:00', 10, ['order_status' => 'cancelled']);
        $this->sale($product, '2026-09-15 16:59:59', 10);
        $this->sale($product, '2026-09-16 17:00:00', 10);
        $this->sale($product, null, 10);

        $response = $this->get(route('admin.reports.sales'))->assertOk()->assertSee('Nama Snapshot')->assertDontSee('Nama sekarang');
        $summary = $response->viewData('summary');
        $this->assertSame(2, (int) $summary->transactions);
        $this->assertEqualsWithDelta(501.25, (float) $summary->revenue, 0.001);
        $this->assertEqualsWithDelta(250.625, (float) $summary->average, 0.001);
        $this->assertSame(5, (int) $response->viewData('itemCount'));
        $ranking = $response->viewData('topProducts');
        $this->assertCount(1, $ranking);
        $this->assertSame(5, (int) $ranking->first()->quantity);
        $this->assertEqualsWithDelta(501.25, (float) $ranking->first()->revenue, 0.001);
        $this->assertSame(1, (int) $response->viewData('payments')->get('cash')->transactions);
        $this->assertEqualsWithDelta(300.75, (float) $response->viewData('payments')->get('qris_manual')->revenue, 0.001);
        $this->assertSame(2, $response->viewData('orders')->total());
    }

    public function test_presets_and_custom_range_select_the_same_paid_dataset(): void
    {
        $product = Product::factory()->create();
        foreach (['2026-08-31 12:00:00', '2026-09-01 12:00:00', '2026-09-09 12:00:00', '2026-09-10 12:00:00', '2026-09-15 12:00:00', '2026-09-16 12:00:00'] as $time) {
            $this->sale($product, $time);
        }
        foreach (['today' => 1, 'yesterday' => 1, 'last7' => 3, 'month' => 5] as $period => $count) {
            $response = $this->get(route('admin.reports.sales', ['period' => $period]))->assertOk();
            $this->assertSame($count, (int) $response->viewData('summary')->transactions);
            $this->assertSame($count, (int) $response->viewData('itemCount'));
            $this->assertSame($count, $response->viewData('orders')->total());
        }
        $this->get(route('admin.reports.sales', ['period' => 'custom', 'start' => '2026-09-09', 'end' => '2026-09-10']))->assertOk()
            ->assertViewHas('orders', fn ($orders): bool => $orders->total() === 2);
    }

    public function test_search_pagination_and_query_count(): void
    {
        $product = Product::factory()->create();
        for ($index = 0; $index < 17; $index++) {
            $this->sale($product, '2026-09-16 03:00:00');
        }
        DB::enableQueryLog();
        $response = $this->get(route('admin.reports.sales', ['search' => 'evan', 'period' => 'today']))->assertOk();
        $this->assertLessThanOrEqual(10, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertSame(17, (int) $response->viewData('summary')->transactions);
        $this->assertCount(15, $response->viewData('orders'));
        $this->assertStringContainsString('search=evan', $response->viewData('orders')->nextPageUrl());
        $this->get(route('admin.reports.sales', ['search' => '05', 'page' => 2]))->assertOk()->assertViewHas('orders', fn ($orders): bool => $orders->count() === 2);
        $empty = $this->get(route('admin.reports.sales', ['search' => 'nobody']))->assertOk();
        $this->assertSame(0.0, (float) $empty->viewData('summary')->revenue);
        $this->assertSame(0.0, (float) $empty->viewData('summary')->average);
        $this->assertSame(0, (int) $empty->viewData('itemCount'));
    }

    public function test_ranking_is_limited_to_ten_products_ordered_by_quantity(): void
    {
        for ($quantity = 1; $quantity <= 11; $quantity++) {
            $this->sale(Product::factory()->create(), '2026-09-16 03:00:00', $quantity);
        }
        $response = $this->get(route('admin.reports.sales'))->assertOk();
        $ranking = $response->viewData('topProducts');
        $this->assertCount(10, $ranking);
        $this->assertSame([11, 10, 9, 8, 7, 6, 5, 4, 3, 2], $ranking->pluck('quantity')->map(fn ($quantity): int => (int) $quantity)->all());
        $this->assertSame(66, (int) $response->viewData('itemCount'));
    }

    public function test_invalid_filters_are_rejected(): void
    {
        foreach ([['period' => 'bad'], ['period' => 'custom'], ['period' => 'custom', 'start' => '2026-09-20', 'end' => '2026-09-01'], ['period' => 'custom', 'start' => '2026-02-30', 'end' => '2026-03-01'], ['search' => ['bad']], ['page' => 0]] as $filters) {
            $this->getJson(route('admin.reports.sales', $filters))->assertUnprocessable();
        }
    }

    /** @param array<string, mixed> $overrides */
    private function sale(Product $product, ?string $time, int $quantity = 1, array $overrides = []): void
    {
        $table = Table::factory()->create(['table_no' => 5]);
        $id = (string) Str::uuid();
        DB::table('orders')->insert(array_merge([
            'id' => $id, 'table_id' => $table->id, 'customer_name' => 'Evan',
            'order_status' => 'completed', 'payment_status' => 'paid', 'payment_type' => 'cash',
            'payment_time' => $time, 'total' => 100.25 * $quantity,
            'created_at' => '2026-08-01 00:00:00', 'updated_at' => '2026-08-01 00:00:00',
        ], $overrides));
        DB::table('order_items')->insert(['order_id' => $id, 'product_id' => $product->id, 'product_name' => 'Nama Snapshot', 'price' => '100.25', 'qty' => $quantity, 'subtotal' => 100.25 * $quantity]);

    }
}
