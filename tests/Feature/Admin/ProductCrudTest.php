<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class ProductCrudTest extends AdminDatabaseTestCase
{
    public function test_list_displays_products_categories_and_pagination(): void
    {
        $category = Category::factory()->create(['category_name' => 'Aneka Mie']);
        Product::factory()->count(16)->create(['category_id' => $category->id]);

        $this->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Aneka Mie')
            ->assertSee('Rp 15.000,00')
            ->assertSee('Available')
            ->assertSee('page=2')
            ->assertSee('Dashboard')
            ->assertSee('Categories')
            ->assertSee('Tables')
            ->assertViewHas('products', fn ($products) => $products->count() === 15 && $products->total() === 16);

        $this->get(route('admin.products.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->count() === 1);
    }

    public function test_empty_state_and_missing_categories_are_explained(): void
    {
        $this->get(route('admin.products.index'))->assertOk()->assertSee('Belum ada produk');
        $this->get(route('admin.products.create'))->assertOk()->assertSee('Belum ada kategori.');
    }

    public function test_create_and_edit_forms_show_category_options_and_values(): void
    {
        $product = Product::factory()->create(['product_name' => 'Mie Goreng', 'is_available' => false]);
        $this->get(route('admin.products.create'))
            ->assertOk()->assertSee($product->category->category_name);
        $this->get(route('admin.products.edit', $product))
            ->assertOk()->assertSee('Mie Goreng')->assertSee('value="0" selected', false);
    }

    public function test_product_can_be_created_with_only_validated_fields(): void
    {
        $payload = $this->payload();
        $this->post(route('admin.products.store'), $payload + ['id' => 999, 'created_at' => '2000-01-01'])
            ->assertRedirect(route('admin.products.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('products', $payload);
        $this->assertDatabaseMissing('products', ['id' => 999]);
    }

    public function test_product_can_be_updated_without_changing_its_code(): void
    {
        $product = Product::factory()->create();
        $payload = $this->payload(['product_code' => $product->product_code, 'product_name' => 'Menu Baru', 'is_available' => '0']);
        $this->put(route('admin.products.update', $product), $payload)
            ->assertRedirect(route('admin.products.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('products', ['id' => $product->id] + $payload);
    }

    public function test_duplicate_code_is_rejected_on_create_and_update(): void
    {
        $existing = Product::factory()->create();
        $other = Product::factory()->create();
        $payload = $this->payload(['product_code' => $existing->product_code]);
        $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('product_code');
        $this->put(route('admin.products.update', $other), $payload)->assertSessionHasErrors('product_code');
        $this->assertSame($other->product_code, $other->fresh()->product_code);
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_product_fields_are_rejected(string $field, mixed $value): void
    {
        $payload = $this->payload([$field => $value]);
        $this->from(route('admin.products.create'))->post(route('admin.products.store'), $payload)
            ->assertRedirect(route('admin.products.create'))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('products', 0);
    }

    public static function invalidFields(): array
    {
        return [
            'missing code' => ['product_code', ''],
            'long code' => ['product_code', str_repeat('x', 256)],
            'missing name' => ['product_name', ''],
            'long name' => ['product_name', str_repeat('x', 256)],
            'unknown category' => ['category_id', 999999],
            'negative price' => ['price', '-1'],
            'too large price' => ['price', '10000000000'],
            'too precise price' => ['price', '1.001'],
            'invalid price' => ['price', 'abc'],
            'invalid availability' => ['is_available', 'yes'],
        ];
    }

    public function test_validation_errors_preserve_form_values(): void
    {
        $this->from(route('admin.products.create'))
            ->post(route('admin.products.store'), $this->payload(['price' => '-1', 'is_available' => '0']))
            ->assertRedirect(route('admin.products.create'));
        $this->get(route('admin.products.create'))
            ->assertOk()->assertSee('Price tidak boleh negatif.')
            ->assertSee('value="PRD-TEST"', false)->assertSee('value="0" selected', false);
    }

    public function test_availability_can_be_set_in_both_directions_and_repeated_safely(): void
    {
        $product = Product::factory()->create();
        foreach ([0, 0, 1] as $available) {
            $this->patch(route('admin.products.availability', $product), ['is_available' => $available, 'price' => 1])
                ->assertRedirect(route('admin.products.index'))->assertSessionHas('success');
            $this->assertSame((bool) $available, $product->fresh()->is_available);
            $this->assertSame('15000.00', $product->fresh()->price);
        }
    }

    public function test_invalid_or_missing_availability_does_not_change_product(): void
    {
        $product = Product::factory()->create();
        foreach ([[], ['is_available' => 'invalid']] as $payload) {
            $this->patch(route('admin.products.availability', $product), $payload)->assertSessionHasErrors('is_available');
            $this->assertTrue($product->fresh()->is_available);
        }
    }

    public function test_unused_product_can_be_deleted(): void
    {
        $product = Product::factory()->create();
        $this->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))->assertSessionHas('success');
        $this->assertModelMissing($product);
    }

    public function test_product_with_order_items_cannot_be_deleted_but_can_be_disabled(): void
    {
        $product = Product::factory()->create();
        DB::table('order_items')->insert(['product_id' => $product->id]);
        $this->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))->assertSessionHas('error');
        $this->assertModelExists($product);
        $this->assertDatabaseCount('order_items', 1);
        $this->patch(route('admin.products.availability', $product), ['is_available' => 0])->assertSessionHas('success');
        $this->assertFalse($product->fresh()->is_available);
    }

    public function test_missing_products_return_not_found(): void
    {
        $this->get(route('admin.products.edit', 999))->assertNotFound();
        $this->put(route('admin.products.update', 999), $this->payload())->assertNotFound();
        $this->patch(route('admin.products.availability', 999), ['is_available' => 0])->assertNotFound();
        $this->delete(route('admin.products.destroy', 999))->assertNotFound();
    }

    public function test_product_name_is_escaped_in_list_and_delete_dialog_data(): void
    {
        Product::factory()->create(['product_name' => '<script>alert("x")</script>']);
        $this->get(route('admin.products.index'))->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("x")</script>', false);
    }

    public function test_sidebar_destinations_and_admin_redirect_work(): void
    {
        $this->get('/admin')->assertRedirect('/admin/products');
        foreach (['admin.dashboard', 'admin.categories.index', 'admin.tables.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'product_code' => 'PRD-TEST',
            'category_id' => Category::factory()->create()->id,
            'product_name' => 'Indomie Goreng',
            'price' => '12000.50',
            'is_available' => '1',
        ], $overrides);
    }
}
