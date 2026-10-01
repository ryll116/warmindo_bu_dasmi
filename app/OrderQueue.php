<?php

namespace App;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use LogicException;

class OrderQueue
{
    public function assign(Order $order): void
    {
        if (DB::transactionLevel() === 0 || $order->exists) {
            throw new LogicException('Queue allocation requires a new order in an order transaction.');
        }
        $date = now(config('app.timezone'))->toDateString();
        $order->queue_date = $date;
        $order->queue_number = ((int) Order::where('queue_date', $date)->max('queue_number')) + 1;
    }
}
