<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Resto;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class ProductDiscountTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::drop('order_items');
        Schema::drop('orders');
        foreach (['2026_09_12_065707_create_orders_table.php', '2026_09_13_124020_create_order_items_table.php', '2026_09_16_024730_add_customer_name_to_orders_table.php', '2026_09_23_030903_add_resto_snapshot_to_order_items_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    public function test_effective_price_preserves_base_price_and_rounds_per_unit_without_negative_prices(): void
    {
        foreach ([['30000.00', '0.00', '30000.00'], ['30000.00', '15.00', '25500.00'], ['30000.00', '100.00', '0.00'], ['0.05', '10.00', '0.05'], ['100.00', '12.34', '87.66'], ['9999999999.99', '0.01', '9998999999.99']] as [$base, $disc, $expected]) {
            $product = Product::factory()->create(['price' => $base, 'disc' => $disc]);
            $this->assertSame($expected, $product->effectivePrice());
            $this->assertSame($base, $product->fresh()->price);
        }
    }

    public function test_admin_can_manage_discount_and_cashier_cannot_write_it(): void
    {
        $product = Product::factory()->create(['resto_id' => Resto::factory()->create()->id]);
        $payload = $product->only(['product_code', 'product_name', 'category_id', 'resto_id', 'price', 'is_available']);
        foreach (['admin', 'superAdmin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('name="disc"', false);
            $this->put(route('admin.products.update', $product), $payload + ['disc' => '15.25'])->assertSessionHas('success');
            $this->assertSame('15.25', $product->fresh()->disc);
            $this->get(route('admin.products.index'))->assertOk()->assertSee('PROMO 15.25%');
            $this->put(route('admin.products.update', $product), $payload + ['disc' => 0])->assertSessionHas('success');
            $this->assertSame($product->price, $product->fresh()->effectivePrice());
        }
        $this->post(route('admin.products.store'), array_replace($payload, ['product_code' => 'PROMO-NEW', 'disc' => '10']))->assertSessionHas('success');
        $this->assertDatabaseHas('products', ['product_code' => 'PROMO-NEW', 'disc' => 10]);
        foreach ([-1, 100.01, '10.123', 'bad', [], null] as $disc) {
            $this->putJson(route('admin.products.update', $product), $payload + ['disc' => $disc])->assertUnprocessable()->assertJsonValidationErrors('disc');
        }
        foreach (['kasir', 'other'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->putJson(route('admin.products.update', $product), $payload + ['disc' => 99])->assertForbidden();
            $this->postJson(route('admin.products.store'), $payload + ['disc' => 99])->assertForbidden();
        }
        auth()->forgetGuards();
        $this->putJson(route('admin.products.update', $product), $payload + ['disc' => 99])->assertUnauthorized();
        $this->assertSame('0.00', $product->fresh()->disc);
    }

    public function test_menu_filters_catalog_and_manual_screen_share_discounted_prices_without_extra_queries(): void
    {
        $table = Table::factory()->create();
        $product = Product::factory()->create(['product_name' => 'Promo Coto', 'price' => '30000.00', 'disc' => 15]);
        foreach ([[], ['category' => $product->category_id], ['search' => 'Promo'], ['category' => $product->category_id, 'search' => 'Promo']] as $filters) {
            $response = $this->get(route('customer.menu', ['qr_token' => $table->qr_token] + $filters))->assertOk()->assertSee('<del>Rp 30.000</del>', false)->assertSee('Rp 25.500')->assertSee('PROMO 15%');
            $this->assertSame('25500.00', $response->viewData('catalog')->get($product->id)['price']);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('customer.menu', $table->qr_token))->assertOk();
        $count = count(DB::getQueryLog());
        Product::factory()->count(10)->create(['disc' => 10]);
        DB::flushQueryLog();
        $this->get(route('customer.menu', $table->qr_token))->assertOk();
        $this->assertSame($count, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.orders.create'))->assertOk()->assertSee('data-price="25500.00"', false)->assertSee('PROMO 15%')->assertSee('Rp30.000,00')->assertSee('Rp25.500,00');
        $product->update(['disc' => 0]);
        $this->get(route('customer.menu', ['qr_token' => $table->qr_token, 'search' => 'Promo Coto']))->assertOk()->assertDontSee('<del>', false)->assertSee('Rp 30.000');
    }

    public function test_qr_and_manual_orders_use_server_discount_and_preserve_paid_receipt_report_snapshots(): void
    {
        $this->freezeTime();
        foreach (['qr', 'manual'] as $source) {
            $resto = Resto::factory()->create();
            $product = Product::factory()->create(['price' => '30000.00', 'disc' => 10, 'resto_id' => $resto->id]);
            $table = Table::factory()->create();
            $items = [['product_id' => $product->id, 'quantity' => 2, 'price' => 1, 'effective_price' => 1, 'subtotal' => 1, 'disc' => 99, 'discount_id' => 999]];
            $forged = ['price' => 1, 'total' => 1, 'disc' => 99, 'discount_id' => 999, 'discount_amount' => 99999];
            if ($source === 'qr') {
                $review = $this->post(route('customer.checkout.review', $table->qr_token), ['items' => $items] + $forged)->assertRedirect();
                $token = basename($review->headers->get('Location'));
                $this->get($review->headers->get('Location'))->assertOk()->assertSee('Rp54.000');
                $product->update(['disc' => 15]);
                $this->post(route('customer.checkout.store', $table->qr_token), ['checkout_token' => $token, 'customer_name' => 'Evan', 'payment_type' => 'qris_manual'] + $forged)->assertRedirect();
            } else {
                $this->actingAs(User::factory()->create());
                $token = $this->get(route('admin.orders.create'))->assertOk()->viewData('token');
                $product->update(['disc' => 15]);
                $this->post(route('admin.orders.store'), ['checkout_token' => $token, 'table_id' => $table->id, 'customer_name' => 'Evan', 'items' => $items] + $forged)->assertSessionHas('success');
            }
            $order = Order::findOrFail($token);
            $this->assertSame('51000.00', $order->total);
            $this->assertSame('unpaid', $order->payment_status);
            $this->assertDatabaseHas('order_items', ['order_id' => $token, 'price' => '25500.00', 'qty' => 2, 'subtotal' => '51000.00', 'resto_id' => $resto->id]);
            $product->update(['disc' => 0, 'price' => '50000.00']);
            $this->actingAs(User::factory()->admin()->create());
            $this->patchJson(route('admin.orders.payment', $order), ['payment_type' => 'cash'])->assertOk();
            $this->get(route('admin.orders.receipt', $order))->assertOk()->assertSee('Rp25.500')->assertSee('Rp51.000')->assertDontSee('Rp50.000');
            $report = $this->get(route('admin.reports.sales', ['resto' => $resto->id]))->assertOk();
            $this->assertSame(51000.0, (float) $report->viewData('summary')->revenue);
            $this->assertSame(51000.0, (float) $report->viewData('restoRevenue')->first()->revenue);
            $this->assertSame('25500.00', $order->items()->firstOrFail()->price);
        }
    }

    public function test_cashier_new_line_uses_current_discount_and_existing_line_keeps_snapshot_when_merged_or_updated(): void
    {
        $this->actingAs(User::factory()->create());
        $table = Table::factory()->create();
        $product = Product::factory()->create(['price' => '30000.00', 'disc' => 10]);
        $token = $this->get(route('admin.orders.create'))->viewData('token');
        $this->post(route('admin.orders.store'), ['checkout_token' => $token, 'table_id' => $table->id, 'customer_name' => 'Evan', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertSessionHas('success');
        $order = Order::findOrFail($token);
        $item = $order->items()->firstOrFail();
        $product->update(['disc' => 50]);
        $this->patch(route('admin.orders.items.update', [$order, $item]), ['quantity' => 2])->assertSessionHas('success');
        $this->post(route('admin.orders.items.store', $order), ['product_id' => $product->id, 'quantity' => 1, 'disc' => 99])->assertSessionHas('success');
        $this->assertSame('27000.00', $item->fresh()->price);
        $this->assertSame(3, $item->fresh()->qty);
        $other = Product::factory()->create(['price' => '20000.00', 'disc' => 25]);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Rp15.000,00');
        $this->post(route('admin.orders.items.store', $order), ['product_id' => $other->id, 'quantity' => 2, 'price' => 1])->assertSessionHas('success');
        $this->assertDatabaseHas('order_items', ['order_id' => $token, 'product_id' => $other->id, 'price' => '15000.00', 'subtotal' => '30000.00']);
        $this->assertSame('111000.00', $order->fresh()->total);
    }
}
