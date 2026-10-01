<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use App\OrderCreation;
use App\OrderQueue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class OrderQueueTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::drop('order_items');
        Schema::drop('orders');
        foreach (['2026_09_12_065707_create_orders_table.php', '2026_09_13_124020_create_order_items_table.php', '2026_09_16_024730_add_customer_name_to_orders_table.php', '2026_09_23_030903_add_resto_snapshot_to_order_items_table.php', '2026_10_01_040631_add_queue_number_to_orders_table.php', '2026_10_01_041131_add_queue_date_to_orders_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        config(['app.timezone' => 'Asia/Jakarta']);
        $this->travelTo(CarbonImmutable::parse('2026-10-01 23:59:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create());
    }

    private function qr(): Order
    {
        $table = Table::factory()->create();
        $product = Product::factory()->create();
        $review = $this->post(route('customer.checkout.review', $table->qr_token), ['items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertRedirect();
        $token = basename($review->headers->get('Location'));
        $response = $this->post(route('customer.checkout.store', $table->qr_token), ['checkout_token' => $token, 'customer_name' => 'QR', 'payment_type' => 'cash', 'queue_number' => 999, 'queue_date' => '2000-01-01'])->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::findOrFail($token);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($order->queue_label);

        return $order;
    }

    private function manual(): Order
    {
        $table = Table::factory()->create();
        $product = Product::factory()->create();
        $token = $this->get(route('admin.orders.create'))->viewData('token');
        $this->post(route('admin.orders.store'), ['checkout_token' => $token, 'table_id' => $table->id, 'customer_name' => 'Manual', 'items' => [['product_id' => $product->id, 'quantity' => 1]], 'queue_number' => 999, 'queue_date' => '2000-01-01'])->assertSessionHasNoErrors()->assertRedirect();

        return Order::findOrFail($token);
    }

    public function test_qr_and_manual_share_daily_sequence_and_ignore_browser_values(): void
    {
        $this->assertSame(1, $this->qr()->queue_number);
        $this->assertSame(2, $this->manual()->queue_number);
        $third = $this->qr();
        $this->assertSame(3, $third->queue_number);
        $this->assertSame('2026-10-01', $third->queue_date);
        $this->travelTo(CarbonImmutable::parse('2026-10-02 00:00:00', 'Asia/Jakarta'));
        $next = $this->manual();
        $this->assertSame(1, $next->queue_number);
        $this->assertSame('2026-10-02', $next->queue_date);
    }

    public function test_queue_is_preserved_during_edits_lifecycle_and_receipt(): void
    {
        $order = $this->manual();
        $item = $order->items()->firstOrFail();
        $this->patchJson(route('admin.orders.items.update', [$order, $item]), ['quantity' => 2])->assertRedirect();
        foreach (['confirmed', 'processing'] as $state) {
            $this->patchJson(route('admin.orders.status', $order), ['order_status' => $state, 'queue_number' => 999])->assertOk();
        }
        $this->patchJson(route('admin.orders.payment', $order), ['payment_type' => 'cash', 'queue_date' => '2000-01-01'])->assertOk();
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('A-001');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('A-001');
        $this->get(route('admin.orders.receipt', $order))->assertOk()->assertSee('A-001');
        $this->patchJson(route('admin.orders.status', $order), ['order_status' => 'completed'])->assertOk();
        $this->assertSame(1, $order->fresh()->queue_number);
        $this->assertSame('2026-10-01', $order->fresh()->queue_date);
    }

    public function test_formatter_and_legacy_order_do_not_allocate_queue(): void
    {
        foreach ([1 => 'A-001', 12 => 'A-012', 123 => 'A-123', 1000 => 'A-1000'] as $number => $label) {
            $order = new Order;
            $order->queue_number = $number;
            $this->assertSame($label, $order->queue_label);
        }
        $legacy = new Order;
        $legacy->table_id = Table::factory()->create()->id;
        $legacy->save();
        $this->assertSame('-', $legacy->queue_label);
        $this->get(route('admin.orders.show', $legacy))->assertOk();
        $this->get(route('admin.orders.receipt', $legacy))->assertOk();
        $this->assertNull($legacy->fresh()->queue_number);
        $this->assertNull($legacy->fresh()->queue_date);
    }

    public function test_replayed_creation_keeps_original_queue(): void
    {
        $order = $this->manual();
        $replay = app(OrderCreation::class)->create($order->table_id, $order->id, [], 'Replay');
        $this->assertSame(1, $replay->queue_number);
        $this->assertSame(2, $this->qr()->queue_number);
    }

    public function test_queue_collision_retries_entire_qr_transaction(): void
    {
        $this->manual();
        $attempts = 0;
        $this->mock(OrderQueue::class, function ($mock) use (&$attempts): void {
            $mock->shouldReceive('assign')->twice()->andReturnUsing(function (Order $order) use (&$attempts): void {
                $attempts++;
                if ($attempts === 1) {
                    $order->queue_date = '2026-10-01';
                    $order->queue_number = 1;
                } else {
                    (new OrderQueue)->assign($order);
                }
            });
        });
        $this->assertSame(2, $this->qr()->queue_number);
        $this->assertSame(2, Order::count());
        $this->assertSame(2, OrderItem::count());
    }

    public function test_failed_item_save_rolls_back_order_and_queue(): void
    {
        OrderItem::creating(fn () => throw new \RuntimeException('Simulated failure'));
        try {
            app(OrderCreation::class)->create(Table::factory()->create()->id, (string) Str::uuid(), [['product_id' => Product::factory()->create()->id, 'quantity' => 1]], 'Failure');
            $this->fail('Expected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated failure', $exception->getMessage());
        } finally {
            OrderItem::flushEventListeners();
        }
        $this->assertSame(0, Order::count());
        $this->assertSame(1, $this->manual()->queue_number);
    }

    public function test_model_rejects_queue_mutation(): void
    {
        $order = $this->manual();
        $order->queue_number = 999;
        $this->expectException(\LogicException::class);
        $order->save();
    }

    public function test_existing_polling_notifications_include_formatted_queue(): void
    {
        $monitor = $this->get(route('admin.orders.index'))->viewData('monitor');
        $order = $this->manual();
        $response = $this->getJson(route('admin.orders.index'), ['X-Orders-Partial' => '1', 'X-Order-Monitor' => $monitor])->assertOk();
        $response->assertJsonFragment(['queue_label' => 'A-001', 'id' => $order->id]);
    }
}
