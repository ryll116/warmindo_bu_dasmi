<?php

/**
 * Browser fixture server. Uses an isolated temporary SQLite database.
 * Run only through tests/Browser/customer-menu.mjs.
 */

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__, 2);
$public = realpath($root.'/public');
$asset = realpath($public.'/'.urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
if ($asset && str_starts_with($asset, $public.DIRECTORY_SEPARATOR) && is_file($asset)) {
    return false;
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$testDirectory = realpath(getenv('WARMINDO_BROWSER_DIR') ?: '');
if (! $testDirectory || ! str_starts_with($testDirectory, realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'warmindo-mobile-')) {
    throw new RuntimeException('Browser fixtures require an isolated temporary directory.');
}
config([
    'app.env' => 'testing',
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $testDirectory.'/browser.sqlite',
    'database.connections.sqlite.url' => null,
    'session.driver' => 'file',
    'session.files' => $testDirectory.'/sessions',
    'cache.default' => 'array',
]);
DB::purge('sqlite');
if (! Schema::hasTable('tables')) {
    Schema::create('categories', function (Blueprint $table): void {
        $table->id();
        $table->text('category_name');
        $table->text('status')->nullable();
        $table->timestamps();
    });
    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('category_id');
        $table->string('product_name');
        $table->string('product_code');
        $table->decimal('price', 12, 2);
        $table->boolean('is_available');
        $table->timestamps();
    });
    Schema::create('tables', function (Blueprint $table): void {
        $table->id();
        $table->integer('table_no');
        $table->string('qr_token', 100);
        $table->boolean('is_available');
    });
    Schema::create('orders', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignId('table_id')->constrained();
        $table->string('order_status')->default('pending');
        $table->string('payment_status')->default('unpaid');
        $table->string('payment_type')->nullable();
        $table->dateTime('payment_time')->nullable();
        $table->decimal('total', 20, 2)->default(0);
        $table->timestamps();
    });
    Schema::create('order_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignUuid('order_id')->constrained();
        $table->foreignId('product_id')->constrained();
        $table->string('product_name');
        $table->decimal('price', 12, 2);
        $table->integer('qty');
        $table->decimal('subtotal', 20, 2);
        $table->text('notes')->nullable();
        $table->timestamps();
    });
    foreach (['valid-menu-token', 'other-menu-token', 'inactive-menu-token'] as $index => $token) {
        DB::table('tables')->insert(['table_no' => $index + 5, 'qr_token' => $token, 'is_available' => $index !== 2]);
    }
    foreach (['Aneka Mie', 'Minuman Segar', 'Makanan Ringan dan Camilan', 'Nasi'] as $index => $name) {
        $category = Category::create(['category_name' => $name, 'status' => 'active']);
        for ($item = 1; $item <= 6; $item++) {
            Product::create([
                'category_id' => $category->id,
                'product_name' => $index === 0 ? 'Indomie Goreng Telur Spesial '.$item : $name.' Pilihan '.$item,
                'product_code' => 'TEST-'.$index.'-'.$item,
                'price' => $item === 6 ? '17500.50' : '15000.00',
                'is_available' => true,
            ]);
        }
    }
}
$app->handleRequest(Request::capture());
