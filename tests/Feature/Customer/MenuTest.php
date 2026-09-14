<?php

namespace Tests\Feature\Customer;

use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class MenuTest extends AdminDatabaseTestCase
{
    public function test_valid_token_shows_only_available_products_from_active_categories(): void
    {
        $table = Table::factory()->create(['table_no' => 5]);
        $shown = Product::factory()->create(['product_name' => 'Indomie Goreng', 'price' => '15000.00']);
        Product::factory()->create(['product_name' => 'Produk habis', 'is_available' => false]);
        $inactive = Category::factory()->create(['status' => 'inactive']);
        Product::factory()->create(['category_id' => $inactive->id, 'product_name' => 'Kategori nonaktif']);
        $legacy = Category::factory()->create(['status' => null]);
        Product::factory()->create(['category_id' => $legacy->id, 'product_name' => 'Kategori belum diatur']);

        $this->get(route('customer.menu', $table->qr_token))->assertOk()
            ->assertSee('Meja 05')->assertSee('Indomie Goreng')->assertSee('Rp 15.000')
            ->assertSee('images/product-placeholder.svg')->assertSee('category-sidebar')
            ->assertDontSee('Produk habis')->assertDontSee('Kategori nonaktif')->assertDontSee('Kategori belum diatur')
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$shown->id]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_unknown_inactive_and_numeric_table_urls_show_friendly_error(): void
    {
        $table = Table::factory()->create(['is_available' => false]);
        foreach (['unknown-token', $table->qr_token, (string) $table->id] as $token) {
            $this->get(route('customer.menu', $token))->assertNotFound()
                ->assertSee('Menu belum dapat dibuka')->assertDontSee('cart-bar');
        }
    }

    public function test_search_and_category_are_combined_and_catalog_keeps_other_available_items(): void
    {
        $table = Table::factory()->create();
        $mie = Category::factory()->create(['category_name' => 'Mie']);
        $other = Category::factory()->create(['category_name' => 'Nasi']);
        $match = Product::factory()->create(['category_id' => $mie->id, 'product_name' => 'Mie Goreng']);
        $excluded = Product::factory()->create(['category_id' => $mie->id, 'product_name' => 'Mie Rebus']);
        Product::factory()->create(['category_id' => $other->id, 'product_name' => 'Nasi Goreng']);
        $response = $this->get(route('customer.menu', ['qr_token' => $table->qr_token, 'category' => $mie->id, 'search' => 'GORENG']))
            ->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$match->id])
            ->assertSee('name="category"', false)->assertSee('search=GORENG', false);
        $this->assertCount(3, $response->viewData('catalog'));
        $this->assertTrue($response->viewData('catalog')->has($excluded->id));
    }

    public function test_search_uses_name_and_empty_search_returns_all_eligible_products(): void
    {
        $table = Table::factory()->create();
        Product::factory()->create(['product_code' => 'CODE-ONLY', 'product_name' => 'Mie']);
        $this->get(route('customer.menu', ['qr_token' => $table->qr_token, 'search' => 'CODE-ONLY']))
            ->assertOk()->assertSee('Belum ada menu yang cocok')
            ->assertViewHas('products', fn ($products) => $products->isEmpty());
        $this->get(route('customer.menu', ['qr_token' => $table->qr_token, 'search' => '  ']))
            ->assertOk()->assertViewHas('products', fn ($products) => $products->count() === 1);
    }

    public function test_invalid_or_inactive_category_cannot_expose_products(): void
    {
        $table = Table::factory()->create();
        $inactive = Category::factory()->create(['status' => 'inactive']);
        Product::factory()->create(['category_id' => $inactive->id]);
        foreach ([$inactive->id, 99999] as $category) {
            $this->get(route('customer.menu', ['qr_token' => $table->qr_token, 'category' => $category]))
                ->assertOk()->assertViewHas('products', fn ($products) => $products->isEmpty());
        }
    }

    public function test_malformed_filters_redirect_to_menu_without_leaking_sql(): void
    {
        $table = Table::factory()->create();
        foreach ([['search' => ['bad']], ['category' => 'bad'], ['search' => str_repeat('x', 256)]] as $filters) {
            $this->get(route('customer.menu', ['qr_token' => $table->qr_token] + $filters))
                ->assertRedirect(route('customer.menu', $table->qr_token))->assertSessionHasErrors();
        }
    }

    public function test_product_names_are_escaped_and_no_order_endpoint_is_added(): void
    {
        $table = Table::factory()->create();
        Product::factory()->create(['product_name' => '<script>alert(1)</script>']);
        $this->get(route('customer.menu', $table->qr_token))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->post(route('customer.menu', $table->qr_token), [])->assertStatus(405);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }
}
