<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CashierTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_name', 100)->nullable();
            $table->string('order_status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_type')->nullable();
            $table->dateTime('payment_time')->nullable();
            $table->decimal('total', 20, 2)->default(0);
            $table->timestamps();
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignUuid('order_id')->constrained();
            $table->string('product_name');
            $table->decimal('price', 12, 2);
            $table->integer('qty');
            $table->decimal('subtotal', 20, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_customer_order_can_be_managed_and_paid_without_changing_snapshot_or_total(): void
    {
        $order = $this->customerOrder();
        $snapshot = $order->items()->firstOrFail();
        Product::findOrFail($snapshot->product_id)->update(['product_name' => 'Changed', 'price' => '1.00']);
        $this->get(route('admin.orders.index'))->assertOk()->assertSee($order->id)->assertSee('2 item');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Coto Makassar')->assertSee('Rp30.000')->assertSee('Tidak pedas')->assertViewHas('order', fn (Order $shown): bool => $shown->items->first()->product_name === 'Coto Makassar');
        $this->patchJson(route('admin.orders.status', $order), ['order_status' => 'completed'])->assertUnprocessable();
        foreach (['confirmed', 'processing'] as $status) {
            $this->patch(route('admin.orders.status', $order), ['order_status' => $status, 'total' => 1])->assertRedirect();
            $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status' => $status, 'total' => 30000]);
        }
        $this->patchJson(route('admin.orders.status', $order), ['order_status' => 'confirmed'])->assertUnprocessable();
        $this->freezeTime();
        $this->patch(route('admin.orders.payment', $order), ['payment_type' => 'cash', 'total' => 1, 'payment_time' => '2000-01-01'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'payment_type' => 'cash', 'payment_time' => now()->format('Y-m-d H:i:s'), 'total' => 30000]);
        $this->patchJson(route('admin.orders.payment', $order), ['payment_type' => 'qris_manual'])->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_type' => 'cash']);
        $this->patch(route('admin.orders.status', $order), ['order_status' => 'completed'])->assertRedirect();
        $this->patchJson(route('admin.orders.status', $order), ['order_status' => 'completed'])->assertUnprocessable();
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('PAID')->assertDontSee('data-bs-target="#payment-confirmation"', false)->assertDontSee('Selesaikan Pesanan');
        $this->get(route('admin.orders.index'))->assertDontSee($order->id);
        $this->get(route('admin.orders.index', ['tab' => 'completed']))->assertSee($order->id);
        $this->assertSame($snapshot->getAttributes(), $snapshot->fresh()->getAttributes());
    }

    public function test_filters_search_and_partial_refresh_are_combined(): void
    {
        $order = $this->customerOrder();
        $query = ['search' => '05', 'order_status' => 'pending', 'payment_status' => 'unpaid'];
        $this->get(route('admin.orders.index', $query))->assertOk()->assertSee($order->id);
        $this->get(route('admin.orders.index', array_merge($query, ['payment_status' => 'paid'])))->assertDontSee($order->id);
        $this->get(route('admin.orders.index', ['search' => substr($order->id, 0, 8)]))->assertSee($order->id);
        $this->get(route('admin.orders.index', $query), ['X-Orders-Partial' => '1'])->assertOk()->assertSee($order->id)->assertDontSee('<html', false);
        $this->patch(route('admin.orders.payment', $order), ['payment_type' => 'qris_manual'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_type' => 'qris_manual', 'payment_status' => 'paid']);
    }

    public function test_invalid_filters_and_payment_methods_are_rejected(): void
    {
        $order = $this->customerOrder();
        foreach ([['search' => ['bad']], ['tab' => 'bad'], ['page' => -1], ['order_status' => 'bad'], ['payment_status' => 'bad']] as $filters) {
            $this->getJson(route('admin.orders.index', $filters))->assertUnprocessable();
        }
        foreach ([[], ['payment_type' => 'gateway'], ['payment_type' => []]] as $payload) {
            $this->patchJson(route('admin.orders.payment', $order), $payload)->assertUnprocessable();
        }
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid', 'payment_type' => 'cash', 'payment_time' => null]);
    }

    public function test_polling_only_notifies_new_orders_independently_of_filters_and_pagination(): void
    {
        $old = $this->customerOrder();
        $initial = $this->get(route('admin.orders.index'))->assertOk();
        $monitor = $initial->viewData('monitor');
        $headers = ['X-Orders-Partial' => '1', 'X-Order-Monitor' => $monitor];
        $this->getJson(route('admin.orders.index'), $headers)->assertOk()->assertJsonCount(0, 'notifications');
        $old->order_status = 'completed';
        $old->save();
        $first = $this->customerOrder();
        $second = $this->customerOrder();

        $response = $this->getJson(route('admin.orders.index', ['search' => 'no-match', 'tab' => 'completed', 'page' => 2]), $headers)
            ->assertOk()->assertJsonCount(2, 'notifications');
        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_column($response->json('notifications'), 'id'));
        $response->assertJsonFragment(['table' => '05', 'quantity' => 2, 'total' => '30000.00', 'url' => route('admin.orders.show', $first)]);
        $this->getJson(route('admin.orders.index'), $headers)->assertJsonCount(2, 'notifications');
        $fresh = $this->get(route('admin.orders.index'))->assertOk()->viewData('monitor');
        $this->getJson(route('admin.orders.index'), ['X-Orders-Partial' => '1', 'X-Order-Monitor' => $fresh])->assertJsonCount(0, 'notifications');
    }

    public function test_pending_items_use_snapshots_recalculate_merge_and_prevent_empty_order(): void
    {
        $order = $this->customerOrder();
        $item = $order->items()->firstOrFail();
        $this->assertSame('Evan', $order->customer_name);
        Product::findOrFail($item->product_id)->update(['price' => '1.00']);
        $this->patch(route('admin.orders.items.update', [$order, $item]), ['quantity' => 3, 'price' => 1, 'subtotal' => 1, 'total' => 1])->assertRedirect();
        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'qty' => 3, 'price' => 15000, 'subtotal' => 45000]);
        $this->assertSame('45000.00', $order->fresh()->total);
        $this->post(route('admin.orders.items.store', $order), ['product_id' => $item->product_id, 'quantity' => 1])->assertRedirect();
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('60000.00', $order->fresh()->total);
        $new = Product::factory()->create(['price' => '10000.25']);
        $this->post(route('admin.orders.items.store', $order), ['product_id' => $new->id, 'quantity' => 2, 'price' => 1])->assertRedirect();
        $this->assertSame('80000.50', $order->fresh()->total);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $new->id, 'product_name' => $new->product_name, 'price' => '10000.25', 'qty' => 2, 'subtotal' => '20000.50']);
        $this->delete(route('admin.orders.items.destroy', [$order, $item]))->assertRedirect();
        $this->assertSame('20000.50', $order->fresh()->total);
        $last = $order->items()->firstOrFail();
        $this->deleteJson(route('admin.orders.items.destroy', [$order, $last]))->assertUnprocessable();
        $this->assertSame(1, $order->items()->count());
    }

    public function test_edits_are_rejected_after_confirmation_and_payment_and_for_other_orders(): void
    {
        $order = $this->customerOrder();
        $item = $order->items()->firstOrFail();
        $other = $this->customerOrder();
        $this->patchJson(route('admin.orders.items.update', [$other, $item]), ['quantity' => 5])->assertNotFound();
        $this->deleteJson(route('admin.orders.items.destroy', [$other, $item]))->assertNotFound();
        foreach (['confirmed', 'processing', 'completed'] as $status) {
            $order->order_status = $status;
            $order->save();
            $this->patchJson(route('admin.orders.items.update', [$order, $item]), ['quantity' => 5])->assertUnprocessable();
            $this->postJson(route('admin.orders.items.store', $order), ['product_id' => $item->product_id, 'quantity' => 1])->assertUnprocessable();
            $this->deleteJson(route('admin.orders.items.destroy', [$order, $item]))->assertUnprocessable();
            $this->get(route('admin.orders.show', $order))->assertDontSee('id="add-order-menu"', false);
        }
        $order->order_status = 'pending';
        $order->payment_status = 'paid';
        $order->save();
        $this->patchJson(route('admin.orders.items.update', [$order, $item]), ['quantity' => 5])->assertUnprocessable();
        $this->assertSame('30000.00', $order->fresh()->total);
        $this->assertSame(2, $item->fresh()->qty);
    }

    public function test_invalid_quantities_and_unavailable_products_do_not_change_order(): void
    {
        $order = $this->customerOrder();
        $item = $order->items()->firstOrFail();
        foreach ([0, -1, 1.5, 100, 'bad'] as $quantity) {
            $this->patchJson(route('admin.orders.items.update', [$order, $item]), ['quantity' => $quantity])->assertUnprocessable();
        }
        $product = Product::factory()->create(['is_available' => false]);
        $this->postJson(route('admin.orders.items.store', $order), ['product_id' => $product->id, 'quantity' => 1])->assertUnprocessable();
        $this->postJson(route('admin.orders.items.store', $order), ['product_id' => $item->product_id, 'quantity' => 99])->assertUnprocessable();
        $this->assertSame('30000.00', $order->fresh()->total);
        $this->assertSame(2, $item->fresh()->qty);
    }

    public function test_failed_total_update_rolls_back_item_quantity(): void
    {
        $order = $this->customerOrder();
        $item = $order->items()->firstOrFail();
        Order::saving(function (Order $saving): void {
            throw new \RuntimeException('Simulated total write failure');
        });
        $this->withoutExceptionHandling();
        try {
            $this->patch(route('admin.orders.items.update', [$order, $item]), ['quantity' => 3]);
            $this->fail('Expected the total update to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated total write failure', $exception->getMessage());
        } finally {
            Order::flushEventListeners();
        }
        $this->assertSame(2, $item->fresh()->qty);
        $this->assertSame('30000.00', $item->fresh()->subtotal);
        $this->assertSame('30000.00', $order->fresh()->total);
    }

    public function test_selected_customer_methods_are_visible_and_qris_requires_cashier_confirmation(): void
    {
        foreach (['cash' => 'Bayar di Kasir', 'qris_manual' => 'QRIS'] as $method => $label) {
            $order = $this->customerOrder($method);
            $this->get(route('admin.orders.index'), ['X-Orders-Partial' => '1'])->assertOk()->assertSee($label)->assertSee('UNPAID');
            $this->get(route('admin.orders.show', $order))->assertOk()->assertSee($label)->assertSee('UNPAID')->assertSee('Konfirmasi Pembayaran');
            $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_type' => $method, 'payment_status' => 'unpaid', 'payment_time' => null]);
            $this->patch(route('admin.orders.payment', $order), ['payment_type' => $method])->assertRedirect();
            $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_type' => $method, 'payment_status' => 'paid']);
            $this->assertNotNull($order->fresh()->payment_time);
        }
    }

    private function customerOrder(string $paymentType = 'cash'): Order
    {
        $table = Table::factory()->create(['table_no' => 5]);
        $product = Product::factory()->create(['product_name' => 'Coto Makassar', 'price' => '15000.00']);
        $review = $this->post(route('customer.checkout.review', $table->qr_token), ['items' => [['product_id' => $product->id, 'quantity' => 2]]])->assertRedirect();
        $token = basename($review->headers->get('Location'));
        $this->post(route('customer.checkout.store', $table->qr_token), ['payment_type' => $paymentType, 'customer_name' => '  Evan  ', 'checkout_token' => $token, 'notes' => 'Tidak pedas'])->assertRedirect();

        return Order::findOrFail($token);
    }
}
