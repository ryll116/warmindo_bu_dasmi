<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductRestoMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (require database_path('migrations/2026_09_23_021007_create_master_resto_table.php'))->up();
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_available')->default(true);
        });
        (require database_path('migrations/2026_09_23_021820_update_products_table.php'))->up();
    }

    private function correction(): Migration
    {
        return require database_path('migrations/2026_09_23_024414_fix_products_resto_foreign_key.php');
    }

    public function test_correction_preserves_valid_and_null_references_and_can_roll_back(): void
    {
        DB::table('master_resto')->insert(['id' => 1, 'resto_name' => 'Resto Test']);
        DB::table('products')->insert([['id' => 1, 'resto_id' => 1], ['id' => 2, 'resto_id' => null]]);
        $this->correction()->up();
        $this->assertCount(1, Schema::getForeignKeys('products'));
        $this->assertDatabaseHas('products', ['id' => 1, 'resto_id' => 1]);
        $this->assertDatabaseHas('products', ['id' => 2, 'resto_id' => null]);
        $this->correction()->down();
        $this->assertCount(0, Schema::getForeignKeys('products'));
        $this->assertDatabaseHas('products', ['id' => 1, 'resto_id' => 1]);
        (require database_path('migrations/2026_09_23_021820_update_products_table.php'))->down();
        $this->assertFalse(Schema::hasColumn('products', 'resto_id'));
    }

    public function test_orphan_references_stop_migration_without_rewriting_data(): void
    {
        DB::table('products')->insert(['id' => 1, 'resto_id' => 99]);
        try {
            $this->correction()->up();
            $this->fail('Expected orphan references to stop the migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Perbaiki referensi', $exception->getMessage());
        }
        $this->assertDatabaseHas('products', ['id' => 1, 'resto_id' => 99]);
        $this->assertCount(0, Schema::getForeignKeys('products'));
    }

    public function test_foreign_key_rejects_invalid_resto(): void
    {
        $this->correction()->up();
        $this->expectException(QueryException::class);
        DB::table('products')->insert(['resto_id' => 99]);
    }

    public function test_resto_with_products_cannot_be_deleted(): void
    {
        DB::table('master_resto')->insert(['id' => 1, 'resto_name' => 'Resto Test']);
        DB::table('products')->insert(['resto_id' => 1]);
        $this->correction()->up();
        $this->expectException(QueryException::class);
        DB::table('master_resto')->where('id', 1)->delete();
    }

    public function test_rollback_rejects_ids_outside_signed_integer_range(): void
    {
        $this->correction()->up();
        DB::table('master_resto')->insert(['id' => 2147483648, 'resto_name' => 'Resto Test']);
        DB::table('products')->insert(['id' => 1, 'resto_id' => 2147483648]);
        try {
            $this->correction()->down();
            $this->fail('Expected unsafe rollback to stop.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('rollback dibatalkan', $exception->getMessage());
        }
        $this->assertCount(1, Schema::getForeignKeys('products'));
        $this->assertDatabaseHas('products', ['id' => 1, 'resto_id' => 2147483648]);
    }
}
