<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Order $order): View
    {
        $order->load('table', 'items');

        return view('admin.orders.receipt', compact('order'));
    }
}
