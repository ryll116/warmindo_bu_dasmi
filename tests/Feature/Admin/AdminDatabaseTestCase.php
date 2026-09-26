<?php

namespace Tests\Feature\Admin;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class AdminDatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_09_17_023811_add_role_to_users_table.php'))->up();

        // Test-only schema reflects master columns inspected in MySQL, without running migrations.
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->text('category_name')->nullable();
            $table->text('status')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code');
            $table->foreignId('category_id')->constrained();
            $table->string('product_name');
            $table->decimal('price', 12, 2);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
        (require database_path('migrations/2026_09_23_021007_create_master_resto_table.php'))->up();
        (require database_path('migrations/2026_09_23_021820_update_products_table.php'))->up();
        (require database_path('migrations/2026_09_23_024414_fix_products_resto_foreign_key.php'))->up();
        (require database_path('migrations/2026_09_25_032213_insert_disc_to_products_table.php'))->up();

        Schema::create('tables', function (Blueprint $table): void {
            $table->id();
            $table->integer('table_no');
            $table->string('qr_token', 100);
            $table->boolean('is_available');
        });
        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('table_id')->constrained();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained();
        });
        (require database_path('migrations/2026_09_23_030903_add_resto_snapshot_to_order_items_table.php'))->up();
    }
}
