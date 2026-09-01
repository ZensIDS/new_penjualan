<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

// --- Auth (guest only) ---
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store']);
});

Route::post('logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// --- Halaman yang butuh login (semua role: superadmin & viewer boleh lihat) ---
Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Route 'create' WAJIB didaftarkan sebelum resource(['index','show']) di bawah ini,
    // supaya path /purchase-orders/create tidak ketangkep duluan oleh wildcard
    // /purchase-orders/{purchaseOrder} milik route 'show'.
    Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])
        ->middleware('role:superadmin')
        ->name('purchase-orders.create');

    Route::resource('purchase-orders', PurchaseOrderController::class)
        ->only(['index', 'show']);

    Route::resource('sales-orders', SalesOrderController::class)
        ->only(['index', 'show']);

    // Modul di bawah ini pakai pola index + modal (create/edit AJAX),
    // jadi cuma butuh route 'index' — store/update/destroy ada di grup superadmin.
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('stock', [ReportController::class, 'stock'])->name('stock');
        Route::get('profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('payable', [ReportController::class, 'payable'])->name('payable');
        Route::get('receivable', [ReportController::class, 'receivable'])->name('receivable');
    });
});

// --- Aksi tulis (create/update/delete) — KHUSUS superadmin ---
// Dipisah dari grup 'auth' di atas supaya viewer otomatis 403 di semua
// aksi tulis tanpa perlu diingat satu-satu tiap kali bikin route baru.
// Product/Category/Supplier/Customer/Expense/ExpenseCategory: store/update/destroy
// dipanggil via AJAX dari modal di halaman index masing-masing (tidak ada halaman create/edit terpisah).
Route::middleware(['auth', 'role:superadmin'])->group(function () {

    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
        ->name('purchase-orders.store');
    Route::post('purchase-orders/{purchaseOrder}/payments', [PurchaseOrderController::class, 'storePayment'])
        ->name('purchase-orders.payments.store');

    Route::post('sales-orders', [SalesOrderController::class, 'store'])
        ->name('sales-orders.store');
    Route::post('sales-orders/{salesOrder}/payments', [SalesOrderController::class, 'storePayment'])
        ->name('sales-orders.payments.store');

    Route::resource('products', ProductController::class)
        ->only(['store', 'update', 'destroy']);

    Route::resource('categories', CategoryController::class)
        ->only(['store', 'update', 'destroy']);

    Route::resource('suppliers', SupplierController::class)
        ->only(['store', 'update', 'destroy']);

    Route::resource('customers', CustomerController::class)
        ->only(['store', 'update', 'destroy']);

    Route::resource('expenses', ExpenseController::class)
        ->only(['store', 'update', 'destroy']);

    Route::resource('expense-categories', ExpenseCategoryController::class)
        ->only(['store', 'update', 'destroy']);
});
