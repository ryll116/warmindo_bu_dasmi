<?php

use App\Models\Order;
use App\OrderQueue;
use App\OrderTransaction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $database, $directory, $index] = $argv;
if (! preg_match('/^warmindo_queue_test_[a-f0-9]{16}$/', $database) || ! in_array($index, ['0', '1'], true)) {
    exit(1);
}
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
DB::purge('mysql');
$attempts = 0;
app(OrderTransaction::class)->run(function () use ($directory, $index, &$attempts): void {
    $attempts++;
    $order = new Order;
    app(OrderQueue::class)->assign($order);
    if ($attempts === 1) {
        file_put_contents($directory.'/ready'.$index, '1');
        $deadline = microtime(true) + 20;
        while (! file_exists($directory.'/go')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Queue concurrency barrier timed out.');
            }
            usleep(10000);
        }
    }
    $order->save();
});
echo $attempts;
