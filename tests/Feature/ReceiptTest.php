<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class ReceiptTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::drop('order_items');
        Schema::drop('orders');
        (require database_path('migrations/2026_09_12_065707_create_orders_table.php'))->up();
        (require database_path('migrations/2026_09_13_124020_create_order_items_table.php'))->up();
        (require database_path('migrations/2026_09_16_024730_add_customer_name_to_orders_table.php'))->up();
        config(['app.timezone' => 'UTC']);
    }

    #[DataProvider('roles')]
    public function test_staff_see_correct_receipt_snapshots_without_modifying_transactions(string $role): void
    {
        $order = $this->order();
        $other = $this->order();
        $other->customer_name = 'Customer lainnya';
        $other->save();
        Product::query()->update(['product_name' => 'Master berubah', 'price' => '99999.00']);
        $ordersBefore = DB::table('orders')->orderBy('id')->get()->toJson();
        $itemsBefore = DB::table('order_items')->orderBy('id')->get()->toJson();
        $this->actingAs(User::factory()->create(['role' => $role]));
        DB::enableQueryLog();
        $response = $this->get(route('admin.orders.receipt', $order))->assertOk()
            ->assertSee('Warmindo Bu Dasmi')->assertSee('Evan')->assertDontSee('Customer lainnya')
            ->assertSee('05')->assertSee('17/09/2026 09:00 WIB')->assertSee('17/09/2026 09:05 WIB')
            ->assertSee(str_repeat('Indomie spesial ', 12))->assertSee('Es Teh')
            ->assertSee('2 × Rp15.000,25')->assertSee('Rp30.000,50')
            ->assertSee('3 × Rp5.000')->assertSee('Rp15.000')->assertSee('Rp45.000,50')
            ->assertSee('Cash')->assertSee('LUNAS')->assertSee('Tanpa sambal')
            ->assertDontSee('Master berubah')->assertDontSee('Rp99.999')
            ->assertViewHas('order', fn (Order $shown): bool => $shown->id === $order->id && $shown->items->count() === 2);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertStringStartsWith('select', strtolower(ltrim($query['query'])));
            $this->assertStringNotContainsString('products', strtolower($query['query']));
        }
        $this->assertStringNotContainsString($order->id, strip_tags($response->getContent()));
        $this->assertSame($ordersBefore, DB::table('orders')->orderBy('id')->get()->toJson());
        $this->assertSame($itemsBefore, DB::table('order_items')->orderBy('id')->get()->toJson());
    }

    public function test_guest_is_redirected_and_missing_order_returns_404_for_staff(): void
    {
        $order = $this->order();
        $this->get(route('admin.orders.receipt', $order))->assertRedirect(route('login'));
        $this->getJson(route('admin.orders.receipt', $order))->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.orders.receipt', (string) Str::uuid()))->assertNotFound();
    }

    public function test_unpaid_and_manual_qris_receipts_use_stored_payment_values(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create());
        $order->payment_type = 'qris_manual';
        $order->save();
        $this->get(route('admin.orders.receipt', $order))->assertOk()->assertSee('QRIS Manual')->assertSee('LUNAS');
        $order->payment_status = 'unpaid';
        $order->payment_type = null;
        $order->payment_time = null;
        $order->customer_name = null;
        $order->save();
        $this->get(route('admin.orders.receipt', $order))->assertOk()->assertSee('BELUM DIBAYAR')->assertSee('Belum tercatat')->assertSee('Nama belum tersedia')->assertDontSee('LUNAS');
    }

    public function test_receipt_displays_stored_totals_even_when_they_differ_from_calculated_amounts(): void
    {
        $order = $this->order();
        $order->total = '40000.00';
        $order->save();
        $item = $order->items()->firstOrFail();
        $item->subtotal = '28000.00';
        $item->save();
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.orders.receipt', $order))->assertOk()->assertSee('Rp40.000')->assertSee('Rp28.000')->assertDontSee('Rp45.000,50');
        $this->assertSame('40000.00', $order->fresh()->total);
        $this->assertSame('28000.00', $item->fresh()->subtotal);
    }

    public function test_cashier_list_partial_and_detail_link_to_receipt(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create());
        foreach ([route('admin.orders.index'), route('admin.orders.show', $order)] as $url) {
            $this->get($url)->assertOk()->assertSee('Lihat Struk')->assertSee(route('admin.orders.receipt', $order));
        }
        $this->get(route('admin.orders.index'), ['X-Orders-Partial' => '1'])->assertOk()->assertSee('Lihat Struk');
        $order->order_status = 'completed';
        $order->save();
        $this->get(route('admin.orders.index', ['tab' => 'completed']))->assertOk()->assertSee(route('admin.orders.receipt', $order));
    }

    public function test_receipt_escapes_customer_product_and_notes_and_loads_print_assets(): void
    {
        $order = $this->order();
        $order->customer_name = '<script>alert(1)</script>';
        $order->save();
        $item = $order->items()->firstOrFail();
        $item->product_name = '<b>Nama menu</b>';
        $item->notes = '<img src=x onerror=alert(1)>';
        $item->save();
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.orders.receipt', $order))->assertOk()
            ->assertSee($order->customer_name)->assertDontSee($order->customer_name, false)
            ->assertSee($item->product_name)->assertDontSee($item->product_name, false)
            ->assertSee($item->notes)->assertDontSee($item->notes, false)
            ->assertSee('css/receipt.css')->assertSee('js/receipt.js')->assertSee('Print Struk');
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return ['admin' => ['admin'], 'kasir' => ['kasir']];
    }

    private function order(): Order
    {
        $table = Table::factory()->create(['table_no' => 5]);
        $first = Product::factory()->create(['product_name' => str_repeat('Indomie spesial ', 12), 'price' => '15000.25']);
        $second = Product::factory()->create(['product_name' => 'Es Teh', 'price' => '5000.00']);
        $review = $this->post(route('customer.checkout.review', $table->qr_token), ['items' => [
            ['product_id' => $first->id, 'quantity' => 2],
            ['product_id' => $second->id, 'quantity' => 3],
        ]])->assertRedirect();
        $token = basename($review->headers->get('Location'));
        $this->post(route('customer.checkout.store', $table->qr_token), ['payment_type' => 'cash', 'checkout_token' => $token, 'customer_name' => 'Evan', 'notes' => 'Tanpa sambal'])->assertRedirect();
        $order = Order::findOrFail($token);
        $order->created_at = '2026-09-17 02:00:00';
        $order->payment_time = '2026-09-17 02:05:00';
        $order->payment_status = 'paid';
        $order->payment_type = 'cash';
        $order->save();

        return $order;
    }
}
