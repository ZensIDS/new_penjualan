<?php

use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesOrderController;
use Illuminate\Support\Facades\Route;

// Tambahkan ke routes/web.php (dalam middleware 'auth')

Route::middleware('auth')->group(function () {

    Route::resource('purchase-orders', PurchaseOrderController::class)
        ->only(['index', 'show', 'store']);
    Route::post('purchase-orders/{purchaseOrder}/payments', [PurchaseOrderController::class, 'storePayment'])
        ->name('purchase-orders.payments.store');

    Route::resource('sales-orders', SalesOrderController::class)
        ->only(['index', 'show', 'store']);
    Route::post('sales-orders/{salesOrder}/payments', [SalesOrderController::class, 'storePayment'])
        ->name('sales-orders.payments.store');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('stock', [ReportController::class, 'stock'])->name('stock');
        Route::get('profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('payable', [ReportController::class, 'payable'])->name('payable');
        Route::get('receivable', [ReportController::class, 'receivable'])->name('receivable');
    });
});
