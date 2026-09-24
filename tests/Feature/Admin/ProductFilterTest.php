<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Resto;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;

class ProductFilterTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    #[DataProvider('searchTerms')]
    public function test_search_matches_code_name_or_category_without_case_sensitivity(string $term): void
    {
        $category = Category::factory()->create(['category_name' => 'Minuman Segar']);
        $match = Product::factory()->create(['category_id' => $category->id, 'product_code' => 'DR-001', 'product_name' => 'Es Teh']);
        Product::factory()->create(['product_name' => 'Nasi Goreng', 'product_code' => 'NAS-001']);

        $this->get(route('admin.products.index', ['search' => $term]))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$match->id]);
    }

    public static function searchTerms(): array
    {
        return [['dr-001'], ['ES TEH'], ['minuman segar']];
    }

    public function test_search_category_and_availability_are_combined_without_or_leaks(): void
    {
        $category = Category::factory()->create(['category_name' => 'Aneka Mie']);
        $otherCategory = Category::factory()->create();
        $match = Product::factory()->create(['category_id' => $category->id, 'product_name' => 'Indomie Goreng', 'is_available' => false]);
        Product::factory()->create(['category_id' => $category->id, 'product_name' => 'Indomie Rebus', 'is_available' => true]);
        Product::factory()->create(['category_id' => $otherCategory->id, 'product_code' => 'INDOMIE-001', 'is_available' => false]);
        Product::factory()->create(['category_id' => $category->id, 'product_name' => 'Nasi', 'is_available' => false]);

        $this->get(route('admin.products.index', ['search' => 'indomie', 'category' => $category->id, 'status' => 'inactive']))
            ->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$match->id]);
    }

    public function test_each_optional_filter_works_without_search(): void
    {
        $category = Category::factory()->create();
        $active = Product::factory()->create(['category_id' => $category->id, 'is_available' => true]);
        $inactive = Product::factory()->create(['is_available' => false]);

        $this->get(route('admin.products.index', ['category' => $category->id]))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$active->id]);
        $this->get(route('admin.products.index', ['status' => 'inactive']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$inactive->id]);
        $this->get(route('admin.products.index', ['status' => 'active']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$active->id]);
    }

    public function test_empty_search_and_filters_return_all_products(): void
    {
        Product::factory()->count(2)->create();
        $this->get(route('admin.products.index', ['search' => '   ', 'category' => '', 'status' => '']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 2);
    }

    public function test_pagination_preserves_all_product_filters(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(16)->create(['category_id' => $category->id, 'product_name' => 'Indomie', 'is_available' => true]);
        Product::factory()->create(['category_id' => $category->id, 'product_name' => 'Indomie', 'is_available' => false]);
        $filters = ['search' => 'indomie', 'category' => (string) $category->id, 'status' => 'active'];
        $response = $this->get(route('admin.products.index', $filters))->assertOk();
        $products = $response->viewData('products');
        parse_str(parse_url($products->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame($filters + ['page' => '2'], $query);
        $this->get($products->nextPageUrl())->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 16 && $products->count() === 1);
    }

    public function test_no_results_and_reset_are_displayed(): void
    {
        Product::factory()->create();
        $this->get(route('admin.products.index', ['search' => 'not-found-xyz']))->assertOk()
            ->assertSee('Tidak ada hasil yang sesuai')->assertSee('Reset')
            ->assertSee('href="'.route('admin.products.index').'"', false);
    }

    #[DataProvider('invalidFilters')]
    public function test_malformed_filters_redirect_to_clean_index(array $filters, string $field): void
    {
        $this->get(route('admin.products.index', $filters))
            ->assertRedirect(route('admin.products.index'))->assertSessionHasErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            [['resto' => 999], 'resto'],
            [['resto' => 'abc'], 'resto'],
            [['resto' => ['bad']], 'resto'],
            [['search' => ['bad']], 'search'],
            [['search' => str_repeat('x', 256)], 'search'],
            [['category' => 999], 'category'],
            [['category' => 'abc'], 'category'],
            [['status' => 'wrong'], 'status'],
            [['page' => 0], 'page'],
        ];
    }

    public function test_resto_filter_combines_with_existing_filters_and_pagination(): void
    {
        $resto = Resto::factory()->create();
        $other = Resto::factory()->create();
        $category = Category::factory()->create();
        Product::factory()->count(16)->create(['category_id' => $category->id, 'resto_id' => $resto->id, 'product_name' => 'Es Teh']);
        Product::factory()->create(['category_id' => $category->id, 'resto_id' => $other->id, 'product_name' => 'Es Teh']);
        Product::factory()->create(['category_id' => $category->id, 'resto_id' => $resto->id, 'product_name' => 'Es Teh', 'is_available' => false]);
        $filters = ['resto' => (string) $resto->id, 'search' => 'teh', 'category' => (string) $category->id, 'status' => 'active'];
        $response = $this->get(route('admin.products.index', $filters))->assertOk()->assertSee('Semua Resto')
            ->assertViewHas('products', fn ($products): bool => $products->total() === 16 && $products->every(fn (Product $product): bool => $product->resto_id === $resto->id));
        $next = $response->viewData('products')->nextPageUrl();
        parse_str(parse_url($next, PHP_URL_QUERY), $query);
        $this->assertSame($filters + ['page' => '2'], $query);
        $this->get($next)->assertOk()->assertViewHas('products', fn ($products): bool => $products->count() === 1);
        $this->get(route('admin.products.index', ['resto' => $other->id]))->assertOk()
            ->assertViewHas('products', fn ($products): bool => $products->total() === 1);
    }
}
