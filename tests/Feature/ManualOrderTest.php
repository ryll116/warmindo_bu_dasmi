<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Resto;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class ManualOrderTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::drop('order_items');
        Schema::drop('orders');
        foreach (['2026_09_12_065707_create_orders_table.php', '2026_09_13_124020_create_order_items_table.php', '2026_09_16_024730_add_customer_name_to_orders_table.php', '2026_09_23_030903_add_resto_snapshot_to_order_items_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        $this->actingAs(User::factory()->create());
    }

    public function test_operational_roles_can_open_and_create_orders_and_catalog_only_contains_available_choices(): void
    {
        $table = Table::factory()->create();
        $inactive = Table::factory()->create(['is_available' => false]);
        $a = Product::factory()->create(['product_name' => 'Nasi Goreng', 'resto_id' => Resto::factory()->create(['resto_name' => 'Resto A'])->id]);
        $b = Product::factory()->create(['product_name' => 'Nasi Goreng', 'resto_id' => Resto::factory()->create(['resto_name' => 'Resto B'])->id]);
        Product::factory()->create(['is_available' => false]);
        Product::factory()->create(['category_id' => Category::factory()->create(['status' => 'inactive'])->id]);
        foreach (['kasir', 'admin', 'superAdmin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $view = $this->get(route('admin.orders.create'))->assertOk()->assertSee('Resto A')->assertSee('Resto B')->assertDontSee('<img', false);
            $this->assertSame([$table->id], $view->viewData('tables')->pluck('id')->all());
            $this->assertEqualsCanonicalizing([$a->id, $b->id], $view->viewData('products')->pluck('id')->all());
            $this->post(route('admin.orders.store'), ['checkout_token' => $view->viewData('token'), 'table_id' => $table->id, 'customer_name' => 'Evan', 'items' => [['product_id' => $a->id, 'quantity' => 1]]])->assertRedirect(route('admin.orders.index'));
        }
        $this->assertDatabaseCount('orders', 3);
    }

    public function test_guests_and_unknown_roles_cannot_access_manual_order_routes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'other']));
        $this->get(route('admin.orders.create'))->assertForbidden();
        $this->postJson(route('admin.orders.store'), [])->assertForbidden();
        auth()->forgetGuards();
        $this->get(route('admin.orders.create'))->assertRedirect(route('login'));
        $this->postJson(route('admin.orders.store'), [])->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_table_name_items_quantities_and_tokens_cannot_create_orders(): void
    {
        $payload = $this->payload();
        $inactive = Table::factory()->create(['is_available' => false]);
        $unavailable = Product::factory()->create(['is_available' => false]);
        $inactiveCategory = Product::factory()->create(['category_id' => Category::factory()->create(['status' => 'inactive'])->id]);
        foreach ([
            ['table_id' => null], ['table_id' => 999999], ['table_id' => $inactive->id],
            ['customer_name' => '   '], ['customer_name' => str_repeat('a', 101)], ['customer_name' => []],
            ['items' => []], ['items' => 'bad'], ['items' => array_fill(0, 101, $payload['items'][0])],
            ['items' => [['product_id' => 999999, 'quantity' => 1]]],
            ['items' => [['product_id' => $unavailable->id, 'quantity' => 1]]],
            ['items' => [['product_id' => $inactiveCategory->id, 'quantity' => 1]]],
            ['items' => [$payload['items'][0], $payload['items'][0]]],
            ['checkout_token' => null], ['checkout_token' => 'bad'], ['checkout_token' => (string) Str::uuid()],
        ] as $invalid) {
            $this->postJson(route('admin.orders.store'), array_replace($payload, $invalid))->assertUnprocessable();
        }
        foreach ([0, -1, 100, 1.5, 'bad', null] as $quantity) {
            $invalid = $payload;
            $invalid['items'][0]['quantity'] = $quantity;
            $this->postJson(route('admin.orders.store'), $invalid)->assertUnprocessable();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_current_database_prices_and_resto_snapshots_override_browser_values_and_replay_is_safe(): void
    {
        $payload = $this->payload();
        $product = Product::findOrFail($payload['items'][0]['product_id']);
        $resto = Resto::factory()->create(['resto_name' => 'Resto A']);
        $product->update(['product_name' => 'Nasi Goreng', 'price' => '20000.25', 'resto_id' => $resto->id]);
        $second = Product::factory()->create(['product_name' => 'Nasi Goreng', 'price' => '22000.50', 'resto_id' => Resto::factory()->create(['resto_name' => 'Resto B'])->id]);
        $payload['items'] = [
            ['product_id' => $product->id, 'quantity' => 2, 'price' => 1, 'subtotal' => 1, 'resto_id' => $second->resto_id, 'resto_name' => 'Forged'],
            ['product_id' => $second->id, 'quantity' => 1],
        ];
        $payload += ['total' => 1, 'payment_status' => 'paid', 'payment_type' => 'qris_manual', 'payment_time' => '2000-01-01', 'order_status' => 'completed', 'resto_id' => 999, 'resto_name' => 'Forged'];
        $this->post(route('admin.orders.store'), $payload)->assertRedirect(route('admin.orders.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('orders', ['id' => $payload['checkout_token'], 'customer_name' => 'Evan', 'table_id' => $payload['table_id'], 'total' => '62001.00', 'order_status' => 'pending', 'payment_status' => 'unpaid', 'payment_type' => null, 'payment_time' => null]);
        $this->assertDatabaseHas('order_items', ['order_id' => $payload['checkout_token'], 'product_id' => $product->id, 'price' => '20000.25', 'subtotal' => '40000.50', 'resto_id' => $resto->id, 'resto_name' => 'Resto A']);
        $this->assertDatabaseHas('order_items', ['order_id' => $payload['checkout_token'], 'product_id' => $second->id, 'resto_id' => $second->resto_id, 'resto_name' => 'Resto B']);
        $product->update(['price' => '1.00']);
        $this->post(route('admin.orders.store'), $payload)->assertRedirect(route('admin.orders.index'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertSame('62001.00', Order::findOrFail($payload['checkout_token'])->total);
        $payload['table_id'] = Table::factory()->create()->id;
        $this->postJson(route('admin.orders.store'), $payload)->assertUnprocessable();
        $this->actingAs(User::factory()->create());
        $this->postJson(route('admin.orders.store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_manual_order_uses_dashboard_editing_payment_receipt_and_resto_report_lifecycle(): void
    {
        $this->freezeTime();
        $payload = $this->payload();
        $product = Product::findOrFail($payload['items'][0]['product_id']);
        $resto = Resto::factory()->create(['resto_name' => 'Resto A']);
        $product->update(['resto_id' => $resto->id, 'price' => '10000.25']);
        $this->post(route('admin.orders.store'), $payload)->assertSessionHas('success');
        $order = Order::findOrFail($payload['checkout_token']);
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('Evan')->assertSee(route('admin.orders.show', $order));
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Tambah Menu');
        $item = $order->items()->firstOrFail();
        $this->patch(route('admin.orders.items.update', [$order, $item]), ['quantity' => 2])->assertSessionHas('success');
        $other = Product::factory()->create(['price' => '5000.00', 'resto_id' => Resto::factory()->create(['resto_name' => 'Resto B'])->id]);
        $this->post(route('admin.orders.items.store', $order), ['product_id' => $other->id, 'quantity' => 1])->assertSessionHas('success');
        $added = $order->items()->where('product_id', $other->id)->firstOrFail();
        $this->delete(route('admin.orders.items.destroy', [$order, $added]))->assertSessionHas('success');
        $this->post(route('admin.orders.items.store', $order), ['product_id' => $other->id, 'quantity' => 1])->assertSessionHas('success');
        $this->assertSame('25000.50', $order->fresh()->total);
        $this->patchJson(route('admin.orders.status', $order), ['order_status' => 'confirmed'])->assertOk();
        $this->patchJson(route('admin.orders.items.update', [$order, $item]), ['quantity' => 3])->assertUnprocessable();
        $this->patchJson(route('admin.orders.payment', $order), ['payment_type' => 'cash'])->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'payment_time' => now()->format('Y-m-d H:i:s')]);
        $this->patchJson(route('admin.orders.payment', $order), ['payment_type' => 'cash'])->assertUnprocessable();
        foreach (['processing', 'completed'] as $status) {
            $this->patchJson(route('admin.orders.status', $order), ['order_status' => $status])->assertOk();
        }
        $this->get(route('admin.orders.receipt', $order))->assertOk()->assertSee('LUNAS')->assertSee('Rp25.000,50')->assertSee('Print Struk');
        $this->actingAs(User::factory()->admin()->create());
        $report = $this->get(route('admin.reports.sales'))->assertOk();
        $this->assertSame(25000.50, (float) $report->viewData('summary')->revenue);
        $this->assertSame($order->id, $report->viewData('orders')->first()->id);
        $this->assertEqualsCanonicalizing([20000.50, 5000.00], $report->viewData('restoRevenue')->pluck('revenue')->map(fn ($amount): float => (float) $amount)->all());
        $filtered = $this->get(route('admin.reports.sales', ['resto' => $resto->id]))->assertOk();
        $this->assertSame(20000.50, (float) $filtered->viewData('summary')->revenue);
        $this->get(route('admin.reports.sales.export', ['resto' => $resto->id]))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_item_failure_rolls_back_whole_order_and_keeps_form_for_retry(): void
    {
        $payload = $this->payload();
        $payload['items'][] = ['product_id' => Product::factory()->create()->id, 'quantity' => 1];
        $saved = 0;
        OrderItem::creating(function () use (&$saved): void {
            if (++$saved === 2) {
                throw new \RuntimeException('Private database failure');
            }
        });
        try {
            $this->from(route('admin.orders.create'))->post(route('admin.orders.store'), $payload)->assertRedirect(route('admin.orders.create'))->assertSessionHasErrors('order')->assertSessionHasInput('checkout_token', $payload['checkout_token']);
        } finally {
            OrderItem::flushEventListeners();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->get(route('admin.orders.create'))->assertOk()->assertViewHas('token', $payload['checkout_token'])->assertDontSee('Private database failure');
        $this->post(route('admin.orders.store'), $payload)->assertSessionHas('success');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
    }

    /** @return array{checkout_token: string, table_id: int, customer_name: string, items: array<int, array{product_id: int, quantity: int}>} */
    private function payload(): array
    {
        $table = Table::factory()->create();
        $product = Product::factory()->create();
        $token = $this->get(route('admin.orders.create'))->assertOk()->viewData('token');

        return ['checkout_token' => $token, 'table_id' => $table->id, 'customer_name' => '  Evan  ', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];
    }
}
