<?php

namespace Tests\Feature;

use App\InventoryService;
use App\InventorySessionService;
use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class InventoryFoundationTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['attendance.timezone' => 'Asia/Jakarta']);
        foreach ([
            '2026_10_01_074550_create_inventory_items_table',
            '2026_10_01_074759_create_stock_movements_table',
            '2026_10_01_081843_create_inventory_sessions_table',
            '2026_10_01_081939_add_inventory_session_id_to_stock_movements_table',
            '2026_10_02_075201_reconcile_inventory_ledger_foundation',
            '2026_10_02_075202_enforce_inventory_session_uniqueness',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
    }

    /** @return array{InventoryItem, InventorySession} */
    private function inventory(string $stock = '10.000'): array
    {
        $this->actingAs(User::factory()->create());
        $item = InventoryItem::factory()->create(['current_stock' => $stock]);
        $session = app(InventorySessionService::class)->open('morning');

        return [$item, $session];
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function mutations(): array
    {
        return [
            'stock in' => ['stockIn', '2.125', '2.125', '12.125'],
            'manual usage' => ['manualUsage', '2.125', '-2.125', '7.875'],
            'waste' => ['waste', '0.001', '-0.001', '9.999'],
            'positive adjustment' => ['adjust', '0.500', '0.500', '10.500'],
            'negative adjustment' => ['adjust', '-0.500', '-0.500', '9.500'],
        ];
    }

    #[DataProvider('mutations')]
    public function test_mutations_keep_signed_ledger_and_balance_synchronized(string $method, string $input, string $signed, string $balance): void
    {
        [$item, $session] = $this->inventory();

        $movement = app(InventoryService::class)->{$method}($item->id, $session->id, $input, 'Penghitungan operator');

        $this->assertSame('10.000', $movement->stock_before);
        $this->assertSame($signed, $movement->quantity);
        $this->assertSame($balance, $movement->stock_after);
        $this->assertSame($balance, $item->fresh()->current_stock);
        $this->assertSame(auth()->id(), $movement->created_by);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(0, DB::table('stock_movements')->whereRaw('ABS(stock_before + quantity - stock_after) > 0.0000001')->count());
    }

    /** @return array<string, array{string}> */
    public static function deductions(): array
    {
        return ['usage' => ['manualUsage'], 'waste' => ['waste']];
    }

    #[DataProvider('deductions')]
    public function test_insufficient_stock_rejects_deduction_without_writes(string $method): void
    {
        [$item, $session] = $this->inventory();
        try {
            app(InventoryService::class)->{$method}($item->id, $session->id, '10.001');
            $this->fail('Insufficient stock was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $this->assertSame('10.000', $item->fresh()->current_stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_adjustment_requires_a_reason(): void
    {
        [$item, $session] = $this->inventory();

        $this->postJson(route('admin.inventory.movement'), [
            'inventory_item_id' => $item->id, 'inventory_session_id' => $session->id,
            'movement_type' => 'adjustment', 'quantity' => '-0.500', 'notes' => ' ',
            'reference_key' => 'adjust:empty-reason',
        ])->assertUnprocessable()->assertJsonValidationErrors('notes');

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('10.000', $item->fresh()->current_stock);
    }

    /** @return array<string, array{string, string}> */
    public static function checkpoints(): array
    {
        return ['opening' => ['recordOpeningCount', 'opening'], 'closing' => ['recordClosingCount', 'closing']];
    }

    #[DataProvider('checkpoints')]
    public function test_checkpoints_preserve_balance_and_show_variance(string $method, string $type): void
    {
        [$item, $session] = $this->inventory();

        $movement = app(InventoryService::class)->{$method}($item->id, $session->id, '9.500');
        $retry = app(InventoryService::class)->{$method}($item->id, $session->id, '9.500');

        $this->assertSame('0.000', $movement->quantity);
        $this->assertSame('10.000', $movement->stock_before);
        $this->assertSame('10.000', $movement->stock_after);
        $this->assertSame('10.000', $item->fresh()->current_stock);
        $this->assertSame('-0.500', $movement->variance());
        $this->assertSame("count:{$session->id}:{$item->id}:{$type}", $movement->reference_key);
        $this->assertSame($movement->id, $retry->id);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->get(route('admin.inventory.index'))->assertSee('-0.500')->assertSee('9.500');
    }

    public function test_reference_retry_after_session_closes_returns_original_movement(): void
    {
        [$item, $session] = $this->inventory();
        $service = app(InventoryService::class);
        $first = $service->stockIn($item->id, $session->id, '1.125', null, 'retry:one');
        app(InventorySessionService::class)->close($session->id, true);

        $retry = $service->stockIn($item->id, $session->id, '1.125', null, 'retry:one');

        $this->assertSame($first->id, $retry->id);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame('11.125', $item->fresh()->current_stock);
    }

    public function test_reference_key_cannot_be_reused_for_another_item_or_payload(): void
    {
        [$item, $session] = $this->inventory();
        $service = app(InventoryService::class);
        $service->stockIn($item->id, $session->id, '1', null, 'same:key');
        $other = InventoryItem::factory()->create();

        try {
            $service->stockIn($other->id, $session->id, '2', null, 'same:key');
            $this->fail('Conflicting key accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reference_key', $exception->errors());
        }
        $this->assertSame('0.000', $other->fresh()->current_stock);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_handover_transfers_global_balance_and_leaves_one_active_session(): void
    {
        [$item, $session] = $this->inventory();
        app(InventoryService::class)->manualUsage($item->id, $session->id, '2');

        $this->post(route('admin.inventory.handover', $session), ['manual_usage_complete' => 1])
            ->assertRedirect(route('admin.inventory.index'));

        $next = InventorySession::where('status', 'open')->sole();
        $this->assertSame('afternoon', $next->shift_type);
        $this->assertSame($session->session_date->toDateString(), $next->session_date->toDateString());
        $this->assertSame('closed', $session->fresh()->status);
        $this->assertSame('8.000', $item->fresh()->current_stock);
        $movement = app(InventoryService::class)->manualUsage($item->id, $next->id, '1');
        $this->assertSame('8.000', $movement->stock_before);
        $this->assertSame('7.000', $movement->stock_after);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_handover_requires_manual_usage_confirmation(): void
    {
        [, $session] = $this->inventory();

        $this->postJson(route('admin.inventory.handover', $session), [])
            ->assertUnprocessable()->assertJsonValidationErrors('manual_usage_complete');

        $this->assertSame('open', $session->fresh()->status);
        $this->assertDatabaseCount('inventory_sessions', 1);
    }

    public function test_failed_handover_rolls_back_session_close(): void
    {
        [, $session] = $this->inventory();
        InventorySession::factory()->closed()->create(['shift_type' => 'afternoon', 'session_date' => $session->session_date]);

        $this->postJson(route('admin.inventory.handover', $session), ['manual_usage_complete' => 1])->assertUnprocessable();

        $this->assertSame('open', $session->fresh()->status);
        $this->assertNull($session->fresh()->closed_at);
        $this->assertSame(1, InventorySession::where('status', 'open')->count());
    }

    public function test_duplicate_date_shift_is_rejected_after_close(): void
    {
        [, $session] = $this->inventory();
        app(InventorySessionService::class)->close($session->id, true);

        $this->postJson(route('admin.inventory.open'), ['shift_type' => 'morning'])->assertUnprocessable();

        $this->assertDatabaseCount('inventory_sessions', 1);
    }

    public function test_database_rejects_duplicate_date_shift_even_outside_service(): void
    {
        [, $session] = $this->inventory();
        app(InventorySessionService::class)->close($session->id, true);

        $this->expectException(QueryException::class);
        InventorySession::factory()->closed()->create(['session_date' => $session->session_date]);
    }

    public function test_database_rejects_a_second_active_session(): void
    {
        $this->inventory();

        $this->expectException(QueryException::class);
        InventorySession::factory()->create(['shift_type' => 'afternoon']);
    }

    public function test_open_uses_attendance_operational_date_and_explicit_shift(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 18:30:00', 'UTC'));
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.inventory.open'), ['shift_type' => 'afternoon', 'session_date' => '2000-01-01', 'opened_by' => 999])
            ->assertRedirect(route('admin.inventory.index'));

        $session = InventorySession::sole();
        $this->assertSame('2026-10-02', $session->session_date->toDateString());
        $this->assertSame('afternoon', $session->shift_type);
        $this->assertSame(auth()->id(), $session->opened_by);
        $this->assertSame('2026-10-01 18:30:00', $session->opened_at->format('Y-m-d H:i:s'));
    }

    public function test_http_movement_ignores_client_balances_and_actor_and_escapes_notes(): void
    {
        [$item, $session] = $this->inventory();
        $notes = '<script>alert(1)</script>';

        $payload = [
            'inventory_item_id' => $item->id, 'inventory_session_id' => $session->id,
            'movement_type' => 'manual_usage', 'quantity' => '0.125', 'reference_key' => 'http:one',
            'created_by' => 999, 'current_stock' => '1000', 'stock_before' => '2000',
            'stock_after' => '3000', 'notes' => $notes,
        ];
        $this->post(route('admin.inventory.movement'), $payload)->assertRedirect(route('admin.inventory.index'));
        $this->post(route('admin.inventory.movement'), $payload)->assertRedirect(route('admin.inventory.index'));

        $movement = StockMovement::sole();
        $this->assertSame(auth()->id(), $movement->created_by);
        $this->assertSame('10.000', $movement->stock_before);
        $this->assertSame('9.875', $item->fresh()->current_stock);
        $this->assertSame('-0.125', $movement->quantity);
        $this->get(route('admin.inventory.index'))->assertSee($notes)->assertDontSee($notes, false);
    }

    public function test_closed_session_rejects_new_movement(): void
    {
        [$item, $session] = $this->inventory();
        app(InventorySessionService::class)->close($session->id, true);

        $this->postJson(route('admin.inventory.movement'), [
            'inventory_item_id' => $item->id, 'inventory_session_id' => $session->id,
            'movement_type' => 'stock_in', 'quantity' => '1', 'reference_key' => 'closed:one',
        ])->assertUnprocessable()->assertJsonValidationErrors('session');

        $this->assertSame('10.000', $item->fresh()->current_stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_http_inventory_requires_authentication_and_authorized_role(): void
    {
        $this->get(route('admin.inventory.index'))->assertRedirect(route('login'));
        $this->postJson(route('admin.inventory.open'), ['shift_type' => 'morning'])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'pegawai']));
        $this->get(route('admin.inventory.index'))->assertForbidden();
        $this->postJson(route('admin.inventory.open'), ['shift_type' => 'morning'])->assertForbidden();
        $this->postJson(route('admin.inventory.movement'), [])->assertForbidden();
        $this->assertDatabaseCount('inventory_sessions', 0);
    }

    public function test_session_with_a_movement_cannot_be_deleted_in_database(): void
    {
        [$item, $session] = $this->inventory();
        app(InventoryService::class)->recordOpeningCount($item->id, $session->id, '10');

        $this->expectException(QueryException::class);
        DB::table('inventory_sessions')->where('id', $session->id)->delete();
    }

    public function test_historical_movement_cannot_be_edited(): void
    {
        [$item, $session] = $this->inventory();
        $movement = app(InventoryService::class)->stockIn($item->id, $session->id, '1');
        $movement->quantity = '2';

        $this->expectException(\LogicException::class);
        $movement->save();
    }

    public function test_historical_movement_cannot_be_deleted(): void
    {
        [$item, $session] = $this->inventory();
        $movement = app(InventoryService::class)->stockIn($item->id, $session->id, '1');

        $this->expectException(\LogicException::class);
        $movement->delete();
    }

    /** @return array<string, array{string}> */
    public static function invalidQuantities(): array
    {
        return ['negative' => ['-1'], 'zero' => ['0'], 'fraction precision' => ['0.0001'], 'exponent' => ['1e3'], 'overflow' => ['1000000000000']];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_usage_quantity_cannot_change_stock(string $quantity): void
    {
        [$item, $session] = $this->inventory();

        try {
            app(InventoryService::class)->manualUsage($item->id, $session->id, $quantity);
            $this->fail('Invalid quantity accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('10.000', $item->fresh()->current_stock);
    }

    public function test_stock_update_rolls_back_when_ledger_insert_fails(): void
    {
        [$item, $session] = $this->inventory();
        DB::statement("CREATE TRIGGER reject_inventory_movement BEFORE INSERT ON stock_movements BEGIN SELECT RAISE(ABORT, 'test failure'); END");

        try {
            app(InventoryService::class)->stockIn($item->id, $session->id, '1');
            $this->fail('Expected ledger insert failure.');
        } catch (QueryException) {
            $this->assertSame('10.000', $item->fresh()->current_stock);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_reconciliation_can_be_retried_without_duplicate_indexes(): void
    {
        $before = Schema::getIndexes('stock_movements');

        (require database_path('migrations/2026_10_02_075201_reconcile_inventory_ledger_foundation.php'))->up();

        $this->assertSame($before, Schema::getIndexes('stock_movements'));
    }
}
