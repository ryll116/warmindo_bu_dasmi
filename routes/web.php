<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderItemController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Admin\TableQrController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\MenuController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/menu/{qr_token}', MenuController::class)->name('customer.menu');
Route::post('/menu/{qr_token}/checkout/review', [CheckoutController::class, 'review'])->name('customer.checkout.review');
Route::get('/menu/{qr_token}/checkout/{checkout_token}', [CheckoutController::class, 'show'])->name('customer.checkout.show');
Route::post('/menu/{qr_token}/checkout', [CheckoutController::class, 'store'])->name('customer.checkout.store');
Route::get('/menu/{qr_token}/orders/{order}/success', [CheckoutController::class, 'success'])->middleware('signed')->name('customer.checkout.success');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/products');
    Route::view('/dashboard', 'admin.placeholder', ['title' => 'Dashboard'])->name('dashboard');
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('tables', TableController::class)->except('show');
    Route::get('/tables/{table}/qr', [TableQrController::class, 'show'])->name('tables.qr');
    Route::get('/tables/{table}/qr/download', [TableQrController::class, 'download'])->name('tables.qr.download');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/items', [OrderItemController::class, 'store'])->name('orders.items.store');
    Route::patch('/orders/{order}/items/{item}', [OrderItemController::class, 'update'])->name('orders.items.update');
    Route::delete('/orders/{order}/items/{item}', [OrderItemController::class, 'destroy'])->name('orders.items.destroy');
    Route::patch('/orders/{order}/status', [OrderController::class, 'status'])->name('orders.status');
    Route::patch('/orders/{order}/payment', [OrderController::class, 'payment'])->name('orders.payment');
    Route::patch('/products/{product}/availability', [ProductController::class, 'updateAvailability'])->name('products.availability');
    Route::resource('products', ProductController::class)->except('show');
});
