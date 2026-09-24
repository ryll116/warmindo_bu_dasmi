<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Feature\Admin\AdminDatabaseTestCase;

#[Group('concurrency')]
class CheckoutConcurrencyTest extends AdminDatabaseTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::drop('order_items');
        Schema::drop('orders');
        foreach (['2026_09_12_065707_create_orders_table.php', '2026_09_13_124020_create_order_items_table.php', '2026_09_16_024730_add_customer_name_to_orders_table.php', '2026_09_23_030903_add_resto_snapshot_to_order_items_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Table::factory()->count(4)->create();
        Product::factory()->count(4)->sequence(
            ['price' => '12000.25'], ['price' => '8000.50'], ['price' => '5500.00'], ['price' => '17000.75'],
        )->create();
        $this->directory = sys_get_temp_dir().'/warmindo-concurrency-'.Str::uuid();
        mkdir($this->directory);
        mkdir($this->directory.'/sessions');
        DB::statement('VACUUM INTO '.DB::getPdo()->quote($this->directory.'/database.sqlite'));
        config(['database.connections.sqlite.database' => $this->directory.'/database.sqlite']);
        DB::purge('sqlite');
        $this->assertSame(4, Table::count());
    }

    public function test_four_tables_checkout_in_parallel_without_mixing_transactions(): void
    {
        $jobs = [];
        foreach (Table::orderBy('id')->get() as $index => $table) {
            $jobs[] = $this->checkout($table, $index);
        }
        $this->audit('four-table', $jobs, $this->parallel($jobs), 4);
    }

    public function test_two_devices_use_the_same_existing_table_without_mixing_items(): void
    {
        $table = Table::orderBy('id')->firstOrFail();
        $jobs = [$this->checkout($table, 0), $this->checkout($table, 2)];
        $this->audit('same-table', $jobs, $this->parallel($jobs), 2);
    }

    public function test_four_table_serial_control_preserves_all_transaction_data(): void
    {
        $jobs = $responses = [];
        foreach (Table::orderBy('id')->get() as $index => $table) {
            $job = $this->checkout($table, $index);
            $jobs[] = $job;
            $responses[] = $this->parallel([$job])[0];
        }
        $this->audit('four-table-serial-control', $jobs, $responses, 4, false);
    }

    public function test_identical_final_submit_in_parallel_creates_only_one_order(): void
    {
        $job = $this->checkout(Table::orderBy('id')->firstOrFail(), 1);
        $prepared = $this->prepare($job);
        unset($job['review']);
        $job['cookies'] = $prepared['cookies'];
        $job['payload']['checkout_token'] = $prepared['token'];
        $jobs = [$job, $job];
        $this->audit('double-submit', $jobs, $this->parallel($jobs), 1);
    }

    public function test_parallel_cashier_additions_preserve_both_quantities(): void
    {
        $checkout = $this->checkout(Table::orderBy('id')->firstOrFail(), 0);
        $order = $this->createOrder($checkout);
        $line = $order->items()->orderBy('id')->firstOrFail();
        $user = User::factory()->create();
        $jobs = [];
        foreach ([2, 3] as $quantity) {
            $jobs[] = [
                'login' => ['email' => $user->email, 'password' => 'password'],
                'path' => '/admin/orders/'.$order->id.'/items',
                'payload' => ['product_id' => $line->product_id, 'quantity' => $quantity],
            ];
        }
        $responses = $this->parallel($jobs);
        $fresh = $order->fresh('items');
        $result = [
            'responses' => $responses,
            'both_requests_successful' => count(array_filter($responses, fn (array $response): bool => $response['status'] === 302)) === 2,
            'quantity' => $line->fresh()->qty,
            'expected_quantity' => $line->qty + 5,
            'consistent_total' => $this->cents($fresh->total) === $fresh->items->sum(fn ($item): int => $this->cents($item->subtotal)),
        ];
        $this->record('cashier-additions', $result);
        $this->assertTrue($result['both_requests_successful'], json_encode($result));
        $this->assertSame($result['expected_quantity'], $result['quantity']);
        $this->assertTrue($result['consistent_total']);
    }

    public function test_confirmation_racing_with_item_edit_keeps_a_consistent_order(): void
    {
        $order = $this->createOrder($this->checkout(Table::orderBy('id')->firstOrFail(), 0));
        $line = $order->items()->orderBy('id')->firstOrFail();
        $user = User::factory()->create();
        $login = ['email' => $user->email, 'password' => 'password'];
        $responses = $this->parallel([
            ['login' => $login, 'method' => 'PATCH', 'path' => '/admin/orders/'.$order->id.'/status', 'payload' => ['order_status' => 'confirmed']],
            ['login' => $login, 'method' => 'PATCH', 'path' => '/admin/orders/'.$order->id.'/items/'.$line->id, 'payload' => ['quantity' => 8]],
        ]);
        $fresh = $order->fresh('items');
        $result = [
            'responses' => $responses, 'status' => $fresh->order_status,
            'quantity' => $line->fresh()->qty,
            'expected_quantity' => $responses[1]['status'] === 302 ? 8 : $line->qty,
            'consistent_total' => $this->cents($fresh->total) === $fresh->items->sum(fn ($item): int => $this->cents($item->subtotal)),
        ];
        $this->record('cashier-confirm-edit', $result);
        $this->assertSame(302, $responses[0]['status'], json_encode($result));
        $this->assertContains($responses[1]['status'], [302, 422], json_encode($result));
        $this->assertSame('confirmed', $result['status']);
        $this->assertSame($result['expected_quantity'], $result['quantity']);
        $this->assertTrue($result['consistent_total']);
    }

    /** @return array<string, mixed> */
    private function checkout(Table $table, int $index): array
    {
        $products = Product::orderBy('id')->get();
        $items = [
            ['product_id' => $products[$index]->id, 'quantity' => $index + 1, 'price' => '0.01', 'subtotal' => '0.01'],
            ['product_id' => $products[($index + 1) % 4]->id, 'quantity' => $index + 2, 'price' => '0.01', 'subtotal' => '0.01'],
        ];

        return [
            'qr_token' => $table->qr_token, 'table_id' => $table->id,
            'review' => ['items' => $items, 'table_id' => Table::whereKeyNot($table->id)->firstOrFail()->id, 'subtotal' => '0.01', 'total' => '0.01'],
            'payload' => ['customer_name' => 'Customer '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'table_id' => -1, 'price' => '0.01', 'subtotal' => '0.01', 'total' => '0.01'],
            'expected_items' => $items,
        ];
    }

    /** @param array<string, mixed> $job */
    private function createOrder(array $job): Order
    {
        $response = $this->parallel([$job])[0];
        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/success?', $response['location'] ?? '');

        return Order::findOrFail($response['token']);
    }

    /** @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private function prepare(array $job): array
    {
        $process = $this->worker($job + ['mode' => 'prepare']);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array<int, array<string, mixed>> $jobs
     * @return array<int, array<string, mixed>>
     */
    private function parallel(array $jobs): array
    {
        $batch = (string) Str::uuid();
        $gate = $this->directory.'/'.$batch.'.go';
        $processes = [];
        $readyFiles = [];
        try {
            foreach ($jobs as $index => $job) {
                $ready = $this->directory.'/'.$batch.'-'.$index.'.ready';
                $process = $this->worker($job + ['ready' => $ready, 'gate' => $gate]);
                $process->start();
                $processes[] = $process;
                $readyFiles[] = $ready;
            }
            $deadline = microtime(true) + 25;
            while (count(array_filter($readyFiles, 'is_file')) !== count($jobs)) {
                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $this->fail('Worker failed before barrier: '.$process->getErrorOutput().$process->getOutput());
                    }
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not reach start barrier.');
                }
                usleep(1000);
            }
            touch($gate);
            $responses = [];
            foreach ($processes as $index => $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                file_put_contents($this->directory.'/'.$batch.'-'.$index.'.response.json', $process->getOutput());
                $responses[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $responses;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    /** @param array<string, mixed> $job */
    private function worker(array $job): Process
    {
        $path = $this->directory.'/job-'.Str::uuid().'.json';
        file_put_contents($path, json_encode($job + ['directory' => $this->directory], JSON_THROW_ON_ERROR));

        return new Process([PHP_BINARY, '-d', 'extension=pdo_sqlite', base_path('tests/checkout-concurrency-worker.php'), $path], base_path(), ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false'], timeout: 40);
    }

    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @param  array<int, array<string, mixed>>  $responses
     */
    private function audit(string $scenario, array $jobs, array $responses, int $expectedOrders, bool $expectOverlap = true): void
    {
        $correctTable = $correctCustomer = $correctItems = $correctPrices = $correctTotals = true;
        $crossItems = $partial = $missing = 0;
        foreach ($jobs as $index => $job) {
            $order = Order::with('items')->find($responses[$index]['token']);
            if (! $order) {
                $missing++;
                $correctTable = $correctCustomer = $correctItems = $correctPrices = $correctTotals = false;

                continue;
            }
            $correctTable = $correctTable && $order->table_id === $job['table_id'];
            $correctCustomer = $correctCustomer && $order->customer_name === $job['payload']['customer_name'];
            $partial += $order->items->count() !== count($job['expected_items']) ? 1 : 0;
            $crossItems += $order->items->whereNotIn('product_id', array_column($job['expected_items'], 'product_id'))->count();
            $total = 0;
            foreach ($job['expected_items'] as $expected) {
                $product = Product::findOrFail($expected['product_id']);
                $item = $order->items->firstWhere('product_id', $product->id);
                $correctItems = $correctItems && $item && $item->order_id === $order->id && $item->qty === $expected['quantity'] && $item->product_name === $product->product_name;
                $subtotal = $this->cents($product->price) * $expected['quantity'];
                $correctPrices = $correctPrices && $item && $item->price === $product->price && $this->cents($item->subtotal) === $subtotal;
                $total += $subtotal;
            }
            $correctTotals = $correctTotals && $this->cents($order->total) === $total;
        }
        $result = [
            'requests' => count($jobs),
            'successful' => count(array_filter($responses, fn (array $response): bool => $response['status'] === 302 && str_contains($response['location'] ?? '', '/success?'))),
            'orders_created' => Order::count(), 'expected_orders' => $expectedOrders,
            'correct_table_mapping' => $correctTable, 'correct_customer' => $correctCustomer,
            'correct_order_items' => $correctItems, 'server_side_pricing' => $correctPrices, 'correct_totals' => $correctTotals,
            'orphan_order_items' => DB::table('order_items')->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')->whereNull('orders.id')->count(),
            'cross_order_items' => $crossItems, 'partial_orders' => $partial, 'missing_orders' => $missing,
            'duplicate_orders' => max(0, Order::count() - $expectedOrders),
            'requests_overlapped' => max(array_column($responses, 'start')) < min(array_column($responses, 'end')),
            'start_spread_ms' => round((max(array_column($responses, 'start')) - min(array_column($responses, 'start'))) * 1000, 3),
            'tables' => Table::orderBy('id')->get(['id', 'table_no'])->toArray(),
            'responses' => $responses,
        ];
        $this->record($scenario, $result);
        $this->assertSame(count($jobs), $result['successful'], json_encode($result));
        $this->assertSame($expectedOrders, $result['orders_created']);
        $this->assertSame($expectOverlap, $result['requests_overlapped']);
        foreach (['correct_table_mapping', 'correct_customer', 'correct_order_items', 'server_side_pricing', 'correct_totals'] as $field) {
            $this->assertTrue($result[$field], $field);
        }
        foreach (['orphan_order_items', 'cross_order_items', 'partial_orders', 'missing_orders', 'duplicate_orders'] as $field) {
            $this->assertSame(0, $result[$field], $field);
        }
    }

    /** @param array<string, mixed> $result */
    private function record(string $scenario, array $result): void
    {
        file_put_contents($this->directory.'/'.$scenario.'.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        fwrite(STDERR, "\nAudit ".$scenario.': '.$this->directory."\n");
    }

    private function cents(string $amount): int
    {
        return (int) str_replace('.', '', $amount);
    }
}
