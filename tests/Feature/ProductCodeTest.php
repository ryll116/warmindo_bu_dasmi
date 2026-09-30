<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Resto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class ProductCodeTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    #[DataProvider('sequences')]
    public function test_preview_uses_highest_valid_suffix_and_does_not_write(array $codes, string $expected): void
    {
        $category = Category::factory()->create(['category_code' => 'MNU']);
        foreach ($codes as $code) {
            Product::factory()->create(['category_id' => $category->id, 'product_code' => $code]);
        }
        $before = DB::table('products')->get()->toJson();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->getJson(route('admin.products.next-code', ['category_id' => $category->id]))->assertOk()->assertJsonPath('product_code', $expected);
        }
        $this->assertSame($before, DB::table('products')->get()->toJson());
    }

    public static function sequences(): array
    {
        return [
            'empty' => [[], 'MNU-001'],
            'one' => [['MNU-001'], 'MNU-002'],
            'nine' => [['MNU-009'], 'MNU-010'],
            'ninety-nine' => [['MNU-099'], 'MNU-100'],
            'beyond 999' => [['MNU-999'], 'MNU-1000'],
            'gaps and legacy' => [['MNU-001', 'MNU-002', 'MNU-005', 'MNU-009', 'MNU-999bad', 'MNU--999', 'MNU-1e9', 'MNU-99', 'MNU-1000 '], 'MNU-010'],
            'numeric order' => [['MNU-999', 'MNU-1000', 'MNU-000000005'], 'MNU-1001'],
            'large suffix' => [['MNU-99999999999999999999'], 'MNU-100000000000000000000'],
            'legacy lowercase' => [['mnu-009'], 'MNU-010'],
        ];
    }

    public function test_sequences_are_separate_and_moved_products_still_reserve_their_original_code(): void
    {
        $food = Category::factory()->create(['category_code' => 'MNU']);
        $drink = Category::factory()->create(['category_code' => 'MNM']);
        Product::factory()->create(['category_id' => $food->id, 'product_code' => 'MNU-009']);
        Product::factory()->create(['category_id' => $drink->id, 'product_code' => 'MNU-010']);
        Product::factory()->create(['category_id' => $drink->id, 'product_code' => 'MNM-003']);
        $this->getJson(route('admin.products.next-code', ['category_id' => $food->id]))->assertJsonPath('product_code', 'MNU-011');
        $this->getJson(route('admin.products.next-code', ['category_id' => $drink->id]))->assertJsonPath('product_code', 'MNM-004');
    }

    public function test_stale_previews_and_forged_browser_codes_are_recomputed_at_insert(): void
    {
        $category = Category::factory()->create(['category_code' => 'MNU']);
        Product::factory()->create(['category_id' => $category->id, 'product_code' => 'MNU-009']);
        foreach (['admin', 'superAdmin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson(route('admin.products.next-code', ['category_id' => $category->id]))->assertJsonPath('product_code', 'MNU-010');
        }
        foreach (['HACK-999', 'MNU-010', '', str_repeat('x', 256), []] as $index => $forged) {
            $this->post(route('admin.products.store'), $this->payload($category) + ['product_code' => $forged, 'category_code' => 'BAD'])->assertSessionHas('success');
            $this->assertDatabaseHas('products', ['product_code' => 'MNU-'.str_pad((string) ($index + 10), 3, '0', STR_PAD_LEFT), 'category_id' => $category->id]);
        }
        $this->assertSame(6, Product::distinct()->count('product_code'));
    }

    public function test_invalid_missing_or_ambiguous_category_prefix_is_rejected_for_preview_and_create(): void
    {
        foreach (['', 'MN', 'M-N', 'mnu'] as $prefix) {
            $category = Category::factory()->create(['category_code' => $prefix]);
            $this->getJson(route('admin.products.next-code', ['category_id' => $category->id]))->assertUnprocessable()->assertJsonValidationErrors('category_id');
            $this->postJson(route('admin.products.store'), $this->payload($category))->assertUnprocessable()->assertJsonValidationErrors('category_id');
        }
        $category = Category::factory()->create(['category_code' => 'DUP']);
        Category::factory()->create(['category_code' => 'dup']);
        $this->getJson(route('admin.products.next-code', ['category_id' => $category->id]))->assertUnprocessable();
        $this->postJson(route('admin.products.store'), $this->payload($category))->assertUnprocessable();
        foreach ([null, 999999, 'bad', []] as $id) {
            $this->getJson(route('admin.products.next-code', ['category_id' => $id]))->assertUnprocessable();
            $this->postJson(route('admin.products.store'), array_replace($this->payload($category), ['category_id' => $id]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_edit_preserves_code_even_when_category_or_browser_code_changes(): void
    {
        $category = Category::factory()->create(['category_code' => 'MNU']);
        $product = Product::factory()->create(['category_id' => $category->id, 'product_code' => 'MNU-007']);
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('value="MNU-007" readonly', false)->assertDontSee('data-preview-url', false);
        foreach ([$category, Category::factory()->create(['category_code' => 'MNM'])] as $target) {
            $this->put(route('admin.products.update', $product), $this->payload($target) + ['product_code' => 'HACK-999'])->assertSessionHas('success');
            $this->assertDatabaseHas('products', ['id' => $product->id, 'product_code' => 'MNU-007', 'category_id' => $target->id]);
        }
    }

    public function test_deleted_highest_code_can_be_reused_without_persistent_counter(): void
    {
        $category = Category::factory()->create(['category_code' => 'MNU']);
        Product::factory()->create(['category_id' => $category->id, 'product_code' => 'MNU-008']);
        $last = Product::factory()->create(['category_id' => $category->id, 'product_code' => 'MNU-009']);
        $this->delete(route('admin.products.destroy', $last))->assertSessionHas('success');
        $this->getJson(route('admin.products.next-code', ['category_id' => $category->id]))->assertJsonPath('product_code', 'MNU-009');
    }

    public function test_preview_requires_product_management_authorization(): void
    {
        $category = Category::factory()->create(['category_code' => 'MNU']);
        foreach (['kasir', 'other'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson(route('admin.products.next-code', ['category_id' => $category->id]))->assertForbidden();
            $this->postJson(route('admin.products.store'), $this->payload($category))->assertForbidden();
        }
        auth()->forgetGuards();
        $this->getJson(route('admin.products.next-code', ['category_id' => $category->id]))->assertUnauthorized();
    }

    /** @return array{category_id: int, resto_id: int, product_name: string, price: string, is_available: bool} */
    private function payload(Category $category): array
    {
        return ['category_id' => $category->id, 'resto_id' => Resto::factory()->create()->id, 'product_name' => 'Coto', 'price' => '30000.00', 'is_available' => true];
    }
}
