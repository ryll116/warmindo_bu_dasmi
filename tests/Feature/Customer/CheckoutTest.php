<?php

namespace Tests\Feature\Customer;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class CheckoutTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_checkout_recalculates_prices_ignores_browser_table_and_preserves_snapshots(): void
    {
        $table = Table::factory()->create();
        $other = Table::factory()->create();
        $product = Product::factory()->create(['product_name' => 'Indomie Telur', 'price' => '15000.50']);
        $second = Product::factory()->create(['price' => '10000.25']);
        $response = $this->post(route('customer.checkout.review', $table->qr_token), ['table_id' => $other->id, 'total' => 1, 'items' => [
            ['product_id' => $product->id, 'quantity' => 2, 'price' => 1], ['product_id' => $second->id, 'quantity' => 1],
        ]])->assertRedirect();
        $url = $response->headers->get('Location');
        $token = basename($url);
        $this->get($url)->assertOk()->assertSee('Rp40.001,25');
        $this->assertDatabaseCount('orders', 0);
        $product->update(['price' => '16000.50']);

        $response = $this->post(route('customer.checkout.store', $table->qr_token), ['customer_name' => '  Evan  ', 'checkout_token' => $token, 'notes' => 'Tidak pedas', 'total' => 1, 'table_id' => $other->id])->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $token, 'table_id' => $table->id, 'total' => '42001.25', 'order_status' => 'pending', 'payment_status' => 'unpaid']);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_items', ['order_id' => $token, 'product_id' => $product->id, 'product_name' => 'Indomie Telur', 'price' => '16000.50', 'qty' => 2, 'subtotal' => '32001.00', 'notes' => 'Tidak pedas']);
        $product->update(['product_name' => 'Nama baru', 'price' => '1.00']);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'product_name' => 'Indomie Telur', 'price' => '16000.50']);
        $success = $response->headers->get('Location');
        $this->get($success)->assertOk()->assertSee('Pesanan Berhasil')->assertSee('data-clear-cart="true"', false);
        $this->get($success)->assertOk()->assertSee('data-clear-cart="false"', false);
        $this->get(route('customer.checkout.success', ['qr_token' => $table->qr_token, 'order' => $token]))->assertForbidden();
        $this->postJson(route('customer.checkout.store', $other->qr_token), ['customer_name' => '  Evan  ', 'checkout_token' => $token])->assertUnprocessable();
        $this->post(route('customer.checkout.store', $table->qr_token), ['customer_name' => '  Evan  ', 'checkout_token' => $token])->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_customer_name_is_required_and_limited(): void
    {
        $table = Table::factory()->create();
        foreach ([null, '   ', ['bad'], str_repeat('a', 101)] as $name) {
            $this->postJson(route('customer.checkout.store', $table->qr_token), ['checkout_token' => (string) Str::uuid(), 'customer_name' => $name])->assertUnprocessable()->assertJsonValidationErrors('customer_name');
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_cart_quantities_products_and_tables_create_nothing(): void
    {
        $table = Table::factory()->create();
        $product = Product::factory()->create();
        foreach ([0, -1, 1.5, 100, 'invalid'] as $quantity) {
            $this->postJson(route('customer.checkout.review', $table->qr_token), ['items' => [['product_id' => $product->id, 'quantity' => $quantity]]])->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        }
        $inactive = Product::factory()->create(['category_id' => Category::factory()->create(['status' => 'inactive'])->id]);
        $product->update(['is_available' => false]);
        foreach ([[], [['product_id' => 9999, 'quantity' => 1]], [['product_id' => $product->id, 'quantity' => 1]], [['product_id' => $inactive->id, 'quantity' => 1]], [['product_id' => $product->id, 'quantity' => 1], ['product_id' => $product->id, 'quantity' => 1]]] as $items) {
            $this->postJson(route('customer.checkout.review', $table->qr_token), ['items' => $items])->assertUnprocessable();
        }
        $table->update(['is_available' => false]);
        foreach (['unknown', $table->qr_token] as $token) {
            $this->postJson(route('customer.checkout.review', $token), ['items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('checkout');
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_submit_rechecks_availability_and_rejects_unowned_draft(): void
    {
        $table = Table::factory()->create();
        $product = Product::factory()->create(['category_id' => Category::factory()->create(['status' => null])->id]);
        $response = $this->post(route('customer.checkout.review', $table->qr_token), ['items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertRedirect();
        $token = basename($response->headers->get('Location'));
        $this->postJson(route('customer.checkout.store', $table->qr_token), ['customer_name' => '  Evan  ', 'checkout_token' => (string) Str::uuid()])->assertUnprocessable();
        $product->update(['is_available' => false]);
        $this->postJson(route('customer.checkout.store', $table->qr_token), ['customer_name' => '  Evan  ', 'checkout_token' => $token])->assertUnprocessable()->assertJsonValidationErrors('items');
        $product->update(['is_available' => true]);
        $table->update(['is_available' => false]);
        $this->postJson(route('customer.checkout.store', $table->qr_token), ['customer_name' => '  Evan  ', 'checkout_token' => $token])->assertUnprocessable()->assertJsonValidationErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_item_failure_rolls_back_order_and_previously_saved_items(): void
    {
        $table = Table::factory()->create();
        $products = Product::factory()->count(2)->create();
        $response = $this->post(route('customer.checkout.review', $table->qr_token), ['items' => $products->map(fn (Product $product): array => ['product_id' => $product->id, 'quantity' => 1])->all()]);
        $url = $response->headers->get('Location');
        $secondId = $products->last()->id;
        OrderItem::creating(function (OrderItem $item) use ($secondId): void {
            if ($item->product_id === $secondId) {
                throw new \RuntimeException('Simulated storage failure');
            }
        });
        try {
            $this->from($url)->post(route('customer.checkout.store', $table->qr_token), ['customer_name' => '  Evan  ', 'checkout_token' => basename($url), 'notes' => 'Tidak pedas'])
                ->assertRedirect($url)->assertSessionHasErrors('checkout')->assertSessionHasInput('notes', 'Tidak pedas');
        } finally {
            OrderItem::flushEventListeners();
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }
}
