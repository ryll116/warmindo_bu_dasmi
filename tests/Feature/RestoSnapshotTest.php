<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Resto;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class RestoSnapshotTest extends AdminDatabaseTestCase
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

    public function test_checkout_uses_current_database_resto_and_ignores_browser_resto(): void
    {
        $old = Resto::factory()->create(['resto_name' => 'Old']);
        $current = Resto::factory()->create(['resto_name' => 'Current']);
        $product = Product::factory()->create(['resto_id' => $old->id]);
        $table = Table::factory()->create();
        $review = $this->post(route('customer.checkout.review', $table->qr_token), ['items' => [['product_id' => $product->id, 'quantity' => 2, 'resto_id' => $old->id, 'resto_name' => 'Forged']]])->assertRedirect();
        $token = basename($review->headers->get('Location'));
        $product->update(['resto_id' => $current->id]);
        $this->post(route('customer.checkout.store', $table->qr_token), ['checkout_token' => $token, 'customer_name' => 'Evan', 'payment_type' => 'cash', 'resto_id' => $old->id, 'resto_name' => 'Forged'])->assertRedirect();
        $this->assertDatabaseHas('order_items', ['order_id' => $token, 'resto_id' => $current->id, 'resto_name' => 'Current', 'qty' => 2]);
        $product->update(['resto_id' => $old->id]);
        $current->update(['resto_name' => 'Renamed']);
        $this->post(route('customer.checkout.store', $table->qr_token), ['checkout_token' => $token, 'customer_name' => 'Evan', 'payment_type' => 'cash'])->assertRedirect();
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('order_items', ['order_id' => $token, 'resto_id' => $current->id, 'resto_name' => 'Current']);
    }

    public function test_cashier_quantity_updates_preserve_snapshot_and_new_provider_gets_separate_item(): void
    {
        $a = Resto::factory()->create(['resto_name' => 'Resto A']);
        $b = Resto::factory()->create(['resto_name' => 'Resto B']);
        $product = Product::factory()->create(['resto_id' => $a->id]);
        $order = $this->checkout($product);
        $item = $order->items()->firstOrFail();
        $product->update(['resto_id' => $b->id]);
        $this->actingAs(User::factory()->create());
        $this->patch(route('admin.orders.items.update', [$order, $item]), ['quantity' => 2, 'resto_id' => $b->id])->assertSessionHas('success');
        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'resto_id' => $a->id, 'resto_name' => 'Resto A', 'qty' => 2]);
        $this->post(route('admin.orders.items.store', $order), ['product_id' => $product->id, 'quantity' => 1, 'resto_id' => $a->id, 'resto_name' => 'Forged'])->assertSessionHas('success');
        $this->assertSame(2, $order->items()->count());
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $product->id, 'resto_id' => $b->id, 'resto_name' => 'Resto B', 'qty' => 1]);
        $this->post(route('admin.orders.items.store', $order), ['product_id' => $product->id, 'quantity' => 1])->assertSessionHas('success');
        $this->assertSame(2, $order->items()->count());
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'resto_id' => $b->id, 'qty' => 2]);
        $this->assertSame('60000.00', $order->fresh()->total);
    }

    public function test_development_backfill_uses_current_provider_preserves_items_and_leaves_unknown_provider_null(): void
    {
        $resto = Resto::factory()->create();
        $other = Resto::factory()->create();
        $product = Product::factory()->create(['resto_id' => $resto->id]);
        $order = $this->checkout($product);
        $unknownOrder = $this->checkout(Product::factory()->create());
        $snapshot = $order->items()->firstOrFail()->getRawOriginal();
        unset($snapshot['resto_id'], $snapshot['resto_name']);
        $migration = require database_path('migrations/2026_09_23_030903_add_resto_snapshot_to_order_items_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('order_items', 'resto_id'));
        $product->update(['resto_id' => $other->id]);
        $migration->up();
        $this->assertSame($snapshot, array_intersect_key($order->items()->firstOrFail()->getRawOriginal(), $snapshot));
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'resto_id' => $other->id, 'resto_name' => $other->resto_name]);
        $this->assertDatabaseHas('order_items', ['order_id' => $unknownOrder->id, 'resto_id' => null, 'resto_name' => null]);
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_snapshot_prevents_deleting_resto_even_after_product_moves(): void
    {
        $resto = Resto::factory()->create();
        $product = Product::factory()->create(['resto_id' => $resto->id]);
        $this->checkout($product);
        $product->update(['resto_id' => Resto::factory()->create()->id]);
        $this->expectException(QueryException::class);
        $resto->delete();
    }

    private function checkout(Product $product): Order
    {
        $table = Table::factory()->create();
        $review = $this->post(route('customer.checkout.review', $table->qr_token), ['items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertRedirect();
        $token = basename($review->headers->get('Location'));
        $this->post(route('customer.checkout.store', $table->qr_token), ['checkout_token' => $token, 'customer_name' => 'Evan', 'payment_type' => 'cash'])->assertRedirect();

        return Order::findOrFail($token);
    }
}
