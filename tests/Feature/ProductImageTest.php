<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Resto;
use App\Models\Table;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class ProductImageTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_create_without_image_and_with_allowed_images_uses_generated_relative_paths(): void
    {
        $this->post(route('admin.products.store'), $this->payload())->assertSessionHas('success');
        $this->assertNull(Product::firstOrFail()->img);
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $payload = $this->payload();
            $this->post(route('admin.products.store'), $payload + ['image' => UploadedFile::fake()->image('customer filename.'.$extension)])->assertSessionHas('success');
            $product = Product::latest('id')->firstOrFail();
            $this->assertMatchesRegularExpression('/^products\/[a-zA-Z0-9]{40}\.(jpg|jpeg|png|webp)$/', $product->img);
            $this->assertStringNotContainsString('customer filename', $product->img);
            Storage::disk('public')->assertExists($product->img);
        }
        $this->assertCount(4, Storage::disk('public')->allFiles('products'));
    }

    public function test_invalid_disguised_svg_and_oversize_files_and_arbitrary_paths_are_rejected(): void
    {
        $payload = $this->payload();
        $disguised = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "bad";');
        foreach ([
            new UploadedFile($disguised->getPathname(), 'photo.jpg', 'image/jpeg', null, true),
            UploadedFile::fake()->createWithContent('photo.html', '<html>bad</html>'),
            UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>'),
            UploadedFile::fake()->image('photo.gif'),
            UploadedFile::fake()->image('photo.jpg')->size(2049),
        ] as $file) {
            $this->postJson(route('admin.products.store'), $payload + ['image' => $file])->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->postJson(route('admin.products.store'), $payload + ['image' => 'products/forged.jpg'])->assertUnprocessable();
        $this->postJson(route('admin.products.store'), $payload + ['img' => '../forged.jpg'])->assertUnprocessable()->assertJsonValidationErrors('img');
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_edit_preserves_replaces_and_removes_image_after_success(): void
    {
        $product = $this->productWithImage();
        $old = $product->img;
        $payload = $this->productPayload($product);
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('enctype="multipart/form-data"', false)->assertSee(Storage::disk('public')->url($old));
        $this->put(route('admin.products.update', $product), $payload)->assertSessionHas('success');
        $this->assertSame($old, $product->fresh()->img);
        Storage::disk('public')->assertExists($old);
        $this->put(route('admin.products.update', $product), $payload + ['image' => UploadedFile::fake()->image('new.png')])->assertSessionHas('success');
        $new = $product->fresh()->img;
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing($old);
        $this->put(route('admin.products.update', $product), $payload + ['remove_image' => 1])->assertSessionHas('success');
        $this->assertNull($product->fresh()->img);
        Storage::disk('public')->assertMissing($new);
    }

    public function test_database_failure_cleans_new_upload_and_preserves_old_image(): void
    {
        $product = $this->productWithImage();
        $old = $product->img;
        $payload = $this->productPayload($product);
        Product::saving(function (): void {
            throw new \RuntimeException('Private SQL failure');
        });
        try {
            $this->from(route('admin.products.create'))->post(route('admin.products.store'), $payload + ['image' => UploadedFile::fake()->image('create.png')])->assertSessionHasErrors('image');
            $this->from(route('admin.products.edit', $product))->put(route('admin.products.update', $product), $payload + ['image' => UploadedFile::fake()->image('replace.png')])->assertSessionHasErrors('image');
        } finally {
            Product::flushEventListeners();
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertSame($old, $product->fresh()->img);
        $this->assertSame([$old], Storage::disk('public')->allFiles('products'));
    }

    public function test_failed_code_generation_cleans_uploaded_file(): void
    {
        $payload = $this->payload();
        DB::table('categories')->where('id', $payload['category_id'])->update(['category_code' => '']);
        $this->post(route('admin.products.store'), $payload + ['image' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasErrors('category_id');
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('products', 0);
    }

    public function test_hard_delete_removes_image_but_unavailable_and_referenced_products_keep_it(): void
    {
        $product = $this->productWithImage();
        $path = $product->img;
        $this->patch(route('admin.products.availability', $product), ['is_available' => 0])->assertSessionHas('success');
        Storage::disk('public')->assertExists($path);
        $this->delete(route('admin.products.destroy', $product))->assertSessionHas('success');
        Storage::disk('public')->assertMissing($path);
        $used = $this->productWithImage();
        DB::table('order_items')->insert(['product_id' => $used->id]);
        $this->delete(route('admin.products.destroy', $used))->assertSessionHas('error');
        Storage::disk('public')->assertExists($used->img);
    }

    public function test_menu_filters_catalog_and_admin_index_display_storage_images_and_placeholder_with_discount(): void
    {
        $product = $this->productWithImage();
        $product->update(['product_name' => 'Coto Foto', 'disc' => 15]);
        $table = Table::factory()->create();
        Product::factory()->create(['product_name' => 'Tanpa Foto']);
        $url = Storage::disk('public')->url($product->img);
        foreach ([[], ['category' => $product->category_id], ['search' => 'Coto Foto']] as $filter) {
            $response = $this->get(route('customer.menu', ['qr_token' => $table->qr_token] + $filter))->assertOk()->assertSee('src="'.$url.'"', false)->assertSee('loading="lazy"', false)->assertSee('PROMO 15%')->assertSee('Rp 25.500');
            $this->assertSame($url, $response->viewData('catalog')->get($product->id)['image_url']);
        }
        $this->get(route('customer.menu', ['qr_token' => $table->qr_token, 'search' => 'Tanpa Foto']))->assertOk()->assertSee('images/product-placeholder.svg');
        $this->get(route('admin.products.index'))->assertOk()->assertSee($url)->assertSee('width="48" height="48"', false);
    }

    public function test_unauthorized_uploads_do_not_write_files(): void
    {
        $payload = $this->payload();
        foreach (['kasir', 'other'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->post(route('admin.products.store'), $payload + ['image' => UploadedFile::fake()->image('bad.jpg')])->assertForbidden();
        }
        auth()->forgetGuards();
        $this->postJson(route('admin.products.store'), $payload + ['image' => UploadedFile::fake()->image('guest.jpg')])->assertUnauthorized();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function productWithImage(): Product
    {
        $this->post(route('admin.products.store'), $this->payload() + ['image' => UploadedFile::fake()->image('old.jpg')])->assertSessionHas('success');

        return Product::latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['category_id' => Category::factory()->create()->id, 'resto_id' => Resto::factory()->create()->id, 'product_name' => 'Coto', 'price' => '30000.00', 'is_available' => true];
    }

    /** @return array<string, mixed> */
    private function productPayload(Product $product): array
    {
        return $product->only(['category_id', 'resto_id', 'product_name', 'price', 'is_available']);
    }
}
