<?php

namespace App;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OrderTransaction
{
    public function run(callable $callback): mixed
    {
        if (DB::transactionLevel() > 0) {
            return $callback();
        }

        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction($callback, 3);
            } catch (QueryException $exception) {
                $queueConflict = str_contains($exception->getMessage(), 'orders_queue_date_number_unique')
                    || str_contains($exception->getMessage(), 'UNIQUE constraint failed: orders.queue_date, orders.queue_number');
                if (! $queueConflict || $attempt >= 9) {
                    throw $exception;
                }
                usleep(random_int(1000, 10000));
            }
        }
    }
}
