<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

class MasterCrudTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_category_can_be_created_edited_and_deleted(): void
    {
        $this->get(route('admin.categories.create'))->assertOk()->assertSee('Category Name');
        $this->post(route('admin.categories.store'), ['category_name' => 'Minuman', 'status' => 'active', 'id' => 999])
            ->assertRedirect(route('admin.categories.index'))->assertSessionHas('success');
        $category = Category::firstOrFail();
        $this->assertNotSame(999, $category->id);
        $this->get(route('admin.categories.edit', $category))->assertOk()->assertSee('Minuman');
        $this->put(route('admin.categories.update', $category), ['category_name' => 'Minuman', 'status' => 'inactive'])
            ->assertSessionHas('success');
        $this->assertSame('inactive', $category->fresh()->status);
        $this->put(route('admin.categories.update', $category), ['category_name' => 'Minuman Segar', 'status' => 'active'])
            ->assertSessionHas('success');
        $this->assertSame('Minuman Segar', $category->fresh()->category_name);
        $this->assertSame('active', $category->fresh()->status);
        $this->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))->assertSessionHas('success');
        $this->assertModelMissing($category);
    }

    public function test_category_name_validation_and_duplicates(): void
    {
        $category = Category::factory()->create(['category_name' => 'Makanan']);
        $other = Category::factory()->create();
        foreach (['', str_repeat('x', 256), ['bad'], 'Makanan'] as $name) {
            $this->post(route('admin.categories.store'), ['category_name' => $name, 'status' => 'active'])->assertSessionHasErrors('category_name');
        }
        $this->put(route('admin.categories.update', $other), ['category_name' => $category->category_name, 'status' => 'active'])
            ->assertSessionHasErrors('category_name');
        $this->assertDatabaseCount('categories', 2);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        $this->delete(route('admin.categories.destroy', $product->category))
            ->assertSessionHas('error')->assertRedirect(route('admin.categories.index'));
        $this->assertModelExists($product->category);
        $this->assertModelExists($product);
    }

    public function test_category_search_is_case_insensitive_and_paginated(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            Category::factory()->create(['category_name' => 'Minuman '.$i]);
        }
        Category::factory()->create(['category_name' => 'Makanan']);
        $response = $this->get(route('admin.categories.index', ['search' => 'MINUMAN']))->assertOk();
        $categories = $response->viewData('categories');
        $this->assertSame(16, $categories->total());
        $this->assertCount(15, $categories);
        $this->assertStringContainsString('search=MINUMAN', $categories->nextPageUrl());
        $this->get($categories->nextPageUrl())->assertOk()
            ->assertViewHas('categories', fn ($categories) => $categories->count() === 1 && $categories->firstItem() === 16);
        $this->get(route('admin.categories.index', ['search' => '  ']))->assertOk()
            ->assertViewHas('categories', fn ($categories) => $categories->total() === 17);
    }

    public function test_category_search_empty_state_and_delete_confirmation_are_rendered(): void
    {
        Category::factory()->create(['category_name' => '<script>alert(1)</script>']);
        $this->get(route('admin.categories.index'))->assertOk()
            ->assertSee('data-bs-target="#delete-record"', false)
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('admin.categories.index', ['search' => 'no-match-xyz']))->assertOk()
            ->assertSee('Tidak ada hasil yang sesuai')->assertSee('Reset');
    }

    public function test_table_tokens_are_generated_uniquely_and_not_accepted_from_forms(): void
    {
        $this->get(route('admin.tables.create'))->assertOk()->assertDontSee('name="qr_token"', false);
        foreach ([1, 2] as $number) {
            $this->post(route('admin.tables.store'), ['table_no' => $number, 'is_available' => '1', 'qr_token' => 'manual-token'])
                ->assertRedirect(route('admin.tables.index'))->assertSessionHas('success');
        }
        $tokens = Table::pluck('qr_token');
        $this->assertCount(2, $tokens->unique());
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $token);
        }
    }

    public function test_table_token_collision_is_retried(): void
    {
        $existing = Table::factory()->create();
        Str::createRandomStringsUsingSequence([$existing->qr_token, str_repeat('Z', 64)]);
        try {
            $newTable = Table::factory()->create();
            $this->assertSame(str_repeat('Z', 64), $newTable->qr_token);
        } finally {
            Str::createRandomStringsNormally();
        }
    }

    public function test_table_can_be_edited_and_activated_without_changing_token(): void
    {
        $table = Table::factory()->create(['table_no' => 1]);
        $token = $table->qr_token;
        $this->get(route('admin.tables.edit', $table))->assertOk()->assertSee('Nomor Meja')->assertDontSee('name="qr_token"', false);
        foreach ([0, 1] as $status) {
            $this->put(route('admin.tables.update', $table), ['table_no' => 1, 'is_available' => $status, 'qr_token' => 'replacement'])
                ->assertRedirect(route('admin.tables.index'))->assertSessionHas('success');
            $this->assertSame((bool) $status, $table->fresh()->is_available);
            $this->assertSame($token, $table->fresh()->qr_token);
        }
        $this->put(route('admin.tables.update', $table), ['table_no' => 2, 'is_available' => 0])->assertSessionHas('success');
        $this->assertSame(2, $table->fresh()->table_no);
    }

    #[DataProvider('invalidTableFields')]
    public function test_invalid_table_fields_are_rejected(string $field, mixed $value): void
    {
        $payload = array_replace(['table_no' => 1, 'is_available' => 1], [$field => $value]);
        $this->post(route('admin.tables.store'), $payload)->assertSessionHasErrors($field);
        $this->assertDatabaseCount('tables', 0);
    }

    public static function invalidTableFields(): array
    {
        return [
            ['table_no', ''],
            ['table_no', 0],
            ['table_no', -1],
            ['table_no', 1.5],
            ['table_no', 'abc'],
            ['table_no', 2147483648],
            ['is_available', 'yes'],
            ['is_available', null],
        ];
    }

    public function test_duplicate_table_numbers_are_rejected(): void
    {
        Table::factory()->create(['table_no' => 1]);
        $other = Table::factory()->create(['table_no' => 2]);
        $this->post(route('admin.tables.store'), ['table_no' => 1, 'is_available' => 1])->assertSessionHasErrors('table_no');
        $this->put(route('admin.tables.update', $other), ['table_no' => 1, 'is_available' => 1])->assertSessionHasErrors('table_no');
        $this->assertSame(2, $other->fresh()->table_no);
    }

    public function test_unused_table_can_be_deleted_but_referenced_table_is_kept(): void
    {
        $unused = Table::factory()->create();
        $this->delete(route('admin.tables.destroy', $unused))->assertSessionHas('success');
        $this->assertModelMissing($unused);
        $used = Table::factory()->create();
        DB::table('orders')->insert(['id' => (string) Str::uuid(), 'table_id' => $used->id]);
        $this->delete(route('admin.tables.destroy', $used))->assertSessionHas('error');
        $this->assertModelExists($used);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_table_search_and_status_work_together_and_preserve_pagination(): void
    {
        for ($i = 10; $i <= 25; $i++) {
            Table::factory()->create(['table_no' => 1000 + $i, 'is_available' => false]);
        }
        Table::factory()->create(['table_no' => 1099, 'is_available' => true]);
        Table::factory()->create(['table_no' => 9999, 'is_available' => false]);
        $response = $this->get(route('admin.tables.index', ['search' => '10', 'status' => 'inactive']))->assertOk();
        $tables = $response->viewData('tables');
        $this->assertSame(16, $tables->total());
        $this->assertCount(15, $tables);
        $this->assertStringContainsString('search=10', $tables->nextPageUrl());
        $this->assertStringContainsString('status=inactive', $tables->nextPageUrl());
        $this->get($tables->nextPageUrl())->assertOk()
            ->assertViewHas('tables', fn ($tables) => $tables->count() === 1 && $tables->firstItem() === 16);
        $this->get(route('admin.tables.index', ['status' => 'active']))->assertOk()
            ->assertViewHas('tables', fn ($tables) => $tables->total() === 1);
        $this->get(route('admin.tables.index', ['search' => '9999']))->assertOk()
            ->assertViewHas('tables', fn ($tables) => $tables->total() === 1);
        $this->get(route('admin.tables.index', ['search' => '', 'status' => '']))->assertOk()
            ->assertViewHas('tables', fn ($tables) => $tables->total() === 18);
    }

    public function test_empty_master_lists_render(): void
    {
        $this->get(route('admin.categories.index'))->assertOk()->assertSee('Belum ada kategori');
        $this->get(route('admin.tables.index'))->assertOk()->assertSee('Belum ada meja');
        $this->get(route('admin.tables.index', ['search' => '999']))->assertOk()->assertSee('Tidak ada hasil yang sesuai');
    }

    public function test_invalid_master_filters_are_rejected(): void
    {
        $this->get(route('admin.categories.index', ['search' => ['bad']]))
            ->assertRedirect(route('admin.categories.index'))->assertSessionHasErrors('search');
        $this->get(route('admin.tables.index', ['status' => 'wrong']))
            ->assertRedirect(route('admin.tables.index'))->assertSessionHasErrors('status');
    }

    public function test_category_status_filter_matches_only_the_selected_status(): void
    {
        $active = Category::factory()->create(['status' => 'active']);
        $inactive = Category::factory()->create(['status' => 'inactive']);
        $legacy = Category::factory()->create(['status' => null]);
        $this->get(route('admin.categories.index', ['status' => 'active']))
            ->assertOk()->assertViewHas('categories', fn ($categories) => $categories->pluck('id')->all() === [$active->id]);
        $this->get(route('admin.categories.index', ['status' => 'inactive']))
            ->assertOk()->assertViewHas('categories', fn ($categories) => $categories->pluck('id')->all() === [$inactive->id]);
        $this->get(route('admin.categories.index', ['status' => '']))
            ->assertOk()->assertSee('Belum diatur')->assertViewHas('categories', fn ($categories) => $categories->total() === 3);
        $this->assertNull($legacy->fresh()->status);
    }

    public function test_category_search_and_status_are_combined_and_preserved_on_pagination(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            Category::factory()->create(['category_name' => 'Minuman '.$i, 'status' => 'inactive']);
        }
        Category::factory()->create(['category_name' => 'Minuman aktif', 'status' => 'active']);
        Category::factory()->create(['category_name' => 'Makanan', 'status' => 'inactive']);
        $response = $this->get(route('admin.categories.index', ['search' => 'MINUMAN', 'status' => 'inactive']))->assertOk();
        $categories = $response->viewData('categories');
        $this->assertSame(16, $categories->total());
        $this->assertStringContainsString('search=MINUMAN', $categories->nextPageUrl());
        $this->assertStringContainsString('status=inactive', $categories->nextPageUrl());
        $this->get($categories->nextPageUrl())->assertOk()
            ->assertViewHas('categories', fn ($categories) => $categories->count() === 1 && $categories->firstItem() === 16);
    }

    public function test_category_rejects_missing_or_invalid_status_on_create_and_update(): void
    {
        $category = Category::factory()->create(['category_name' => 'Existing', 'status' => 'active']);
        foreach ([null, '', 'invalid', 'ACTIVE', 1, ['active']] as $status) {
            $this->post(route('admin.categories.store'), ['category_name' => 'New category', 'status' => $status])
                ->assertSessionHasErrors('status');
            $this->put(route('admin.categories.update', $category), ['category_name' => 'Existing', 'status' => $status])
                ->assertSessionHasErrors('status');
        }
        $this->assertDatabaseCount('categories', 1);
        $this->assertSame('active', $category->fresh()->status);
        $this->get(route('admin.categories.index', ['status' => 'invalid']))
            ->assertRedirect(route('admin.categories.index'))->assertSessionHasErrors('status');
    }

    public function test_category_forms_show_status_and_preserve_it_after_validation_failure(): void
    {
        $this->get(route('admin.categories.create'))->assertOk()->assertSee('value="active" selected', false);
        $category = Category::factory()->create(['status' => 'inactive']);
        $this->get(route('admin.categories.edit', $category))->assertOk()->assertSee('value="inactive" selected', false);
        $this->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), ['category_name' => '', 'status' => 'inactive'])
            ->assertRedirect(route('admin.categories.create'));
        $this->get(route('admin.categories.create'))->assertOk()->assertSee('Category Name wajib diisi.')
            ->assertSee('value="inactive" selected', false);
    }

    public function test_legacy_category_status_can_be_set_without_changing_products(): void
    {
        $category = Category::factory()->create(['status' => null]);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_available' => true]);
        $this->put(route('admin.categories.update', $category), ['category_name' => $category->category_name, 'status' => 'inactive'])
            ->assertSessionHas('success');
        $this->assertSame('inactive', $category->fresh()->status);
        $this->assertTrue($product->fresh()->is_available);
    }

    public function test_table_validation_retains_inputs_and_shows_errors(): void
    {
        $this->from(route('admin.tables.create'))->post(route('admin.tables.store'), ['table_no' => -1, 'is_available' => 0])
            ->assertRedirect(route('admin.tables.create'));
        $this->get(route('admin.tables.create'))->assertOk()
            ->assertSee('Nomor Meja minimal 1.')->assertSee('value="0" selected', false);
    }

    public function test_missing_master_records_return_not_found(): void
    {
        foreach (['categories', 'tables'] as $resource) {
            $this->get(route('admin.'.$resource.'.edit', 999))->assertNotFound();
            $this->put(route('admin.'.$resource.'.update', 999), [])->assertNotFound();
            $this->delete(route('admin.'.$resource.'.destroy', 999))->assertNotFound();
        }
    }
}
