<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderItemController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\SalesReportController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Admin\TableQrController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\MenuController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/menu/{qr_token}', MenuController::class)->name('customer.menu');
Route::post('/menu/{qr_token}/checkout/review', [CheckoutController::class, 'review'])->name('customer.checkout.review');
Route::get('/menu/{qr_token}/checkout/{checkout_token}', [CheckoutController::class, 'show'])->name('customer.checkout.show');
Route::post('/menu/{qr_token}/checkout', [CheckoutController::class, 'store'])->name('customer.checkout.store');
Route::get('/menu/{qr_token}/orders/{order}/success', [CheckoutController::class, 'success'])->middleware('signed')->name('customer.checkout.success');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin,kasir,superAdmin'])->group(function () {
    Route::get('/', fn () => to_route(auth()->user()->isAdmin() ? 'admin.products.index' : 'admin.orders.index'))->name('home');
    Route::middleware('role:admin,superAdmin')->group(function () {
        Route::view('/dashboard', 'admin.placeholder', ['title' => 'Dashboard'])->name('dashboard');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('tables', TableController::class)->except('show');
        Route::get('/reports/sales', SalesReportController::class)->name('reports.sales');
        Route::get('/tables/{table}/qr', [TableQrController::class, 'show'])->name('tables.qr');
        Route::get('/tables/{table}/qr/download', [TableQrController::class, 'download'])->name('tables.qr.download');
        Route::patch('/products/{product}/availability', [ProductController::class, 'updateAvailability'])->name('products.availability');
        Route::resource('products', ProductController::class)->except('show');
    });
    Route::middleware('role:superAdmin')->group(function () {
        Route::resource('users', UserController::class)->except('show');
    });
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/receipt', ReceiptController::class)->name('orders.receipt');
    Route::post('/orders/{order}/items', [OrderItemController::class, 'store'])->name('orders.items.store');
    Route::patch('/orders/{order}/items/{item}', [OrderItemController::class, 'update'])->name('orders.items.update');
    Route::delete('/orders/{order}/items/{item}', [OrderItemController::class, 'destroy'])->name('orders.items.destroy');
    Route::patch('/orders/{order}/status', [OrderController::class, 'status'])->name('orders.status');
    Route::patch('/orders/{order}/payment', [OrderController::class, 'payment'])->name('orders.payment');
});
