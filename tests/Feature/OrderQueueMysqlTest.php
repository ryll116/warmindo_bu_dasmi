<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OrderQueueMysqlTest extends TestCase
{
    public function test_parallel_mysql_allocations_retry_on_a_real_unique_collision(): void
    {
        if (getenv('RUN_QUEUE_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Set RUN_QUEUE_MYSQL_TEST=1 with local MySQL CREATE DATABASE permission.');
        }
        $name = 'warmindo_queue_test_'.bin2hex(random_bytes(8));
        $directory = sys_get_temp_dir().'/'.$name;
        mkdir($directory);
        config(['database.connections.queue_admin' => array_merge(config('database.connections.mysql'), ['database' => null, 'url' => null])]);
        $admin = DB::connection('queue_admin');
        $admin->statement('CREATE DATABASE `'.$name.'`');
        $processes = [];
        try {
            config(['database.connections.queue_test' => array_merge(config('database.connections.mysql'), ['database' => $name, 'url' => null])]);
            Schema::connection('queue_test')->create('orders', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->unsignedInteger('queue_number')->nullable();
                $table->date('queue_date')->nullable();
                $table->unique(['queue_date', 'queue_number'], 'orders_queue_date_number_unique');
                $table->timestamps();
            });
            foreach ([0, 1] as $index) {
                $process = new Process([PHP_BINARY, base_path('tests/queue-mysql-worker.php'), $name, $directory, (string) $index], base_path());
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while ((! file_exists($directory.'/ready0') || ! file_exists($directory.'/ready1')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($directory.'/ready0');
            $this->assertFileExists($directory.'/ready1');
            file_put_contents($directory.'/go', '1');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            }
            $numbers = DB::connection('queue_test')->table('orders')->orderBy('queue_number')->pluck('queue_number')->map(fn ($number): int => (int) $number)->all();
            $this->assertSame([1, 2], $numbers);
            $attempts = array_sum(array_map(fn (Process $process): int => (int) $process->getOutput(), $processes));
            $this->assertGreaterThanOrEqual(3, $attempts);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            DB::purge('queue_test');
            $admin->statement('DROP DATABASE `'.$name.'`');
            foreach (['ready0', 'ready1', 'go'] as $file) {
                if (file_exists($directory.'/'.$file)) {
                    unlink($directory.'/'.$file);
                }
            }
            rmdir($directory);
        }
    }
}
