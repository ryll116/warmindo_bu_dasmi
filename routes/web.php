<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\TableController;
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
    Route::patch('/products/{product}/availability', [ProductController::class, 'updateAvailability'])->name('products.availability');
    Route::resource('products', ProductController::class)->except('show');
});
