# TODO — Sistem Inventaris, Operasional & Keuangan

Terakhir diupdate: CRUD Product, Category, Supplier, Customer, Expense, ExpenseCategory sudah
selesai end-to-end (Controller JSON + view index dengan modal Alpine.js/AJAX). Sisa Fase 8:
form input PO/SO (dynamic item baris), halaman detail PO/SO, dan halaman laporan.

## ✅ Fase 1 — Database (Migration)

- [x] `categories`, `products` (dengan cache `qty_on_hand`)
- [x] `suppliers`, `customers`
- [x] `purchase_orders`, `purchase_order_items`, `purchase_payments`
- [x] `stock_batches` (inti FIFO), `stock_movements` (audit trail stok)
- [x] `sales_orders`, `sale_items`, `sale_item_allocations` (mapping FIFO), `sales_payments`
- [x] `expense_categories`, `expenses`
- [x] `cash_flows` (ledger kas masuk/keluar)
- [x] `users` (custom: username + role), `activity_logs` (custom, manual)

## ✅ Fase 2 — Activity Log Otomatis

- [x] Model `ActivityLog`
- [x] `GlobalActivityObserver` — mencatat created/updated/deleted semua model otomatis
- [x] `AppServiceProvider` — auto-register observer ke semua model di `app/Models`, tanpa edit tiap model

## ✅ Fase 3 — Model Eloquent + Relasi

- [x] Semua model dibuat lengkap dengan relasi (`Category`, `Product`, `Supplier`, `Customer`,
      `PurchaseOrder`, `PurchaseOrderItem`, `StockBatch`, `PurchasePayment`,
      `SalesOrder`, `SaleItem`, `SaleItemAllocation`, `SalesPayment`,
      `ExpenseCategory`, `Expense`, `StockMovement`, `CashFlow`, `User`)
- [x] Standar: kolom `decimal` (uang) di-cast `'amount' => 'decimal:2'` dst di semua model
      supaya konsisten sebagai angka, bukan string mentah dari MySQL — **wajib diikuti**
      kalau nambah kolom uang baru di model manapun nanti.
- [x] `Category`, `Customer`, `Supplier`, `ExpenseCategory` sengaja tetap minimal
      (murni master data, tanpa logika) — bukan kelupaan.

## ✅ Fase 4 — Service Layer (Logika Bisnis Inti)

- [x] `StockService` — FIFO allocation, sync qty_on_hand, batch breakdown, reverse allocation (retur)
- [x] `CashFlowService` — pencatat ledger kas masuk/keluar
- [x] `PurchaseOrderService` — create PO + item + auto stok masuk + payment
- [x] `SalesOrderService` — create SO + item + auto FIFO + HPP + payment
- [x] `ExpenseService` — create expense + catat kas keluar
- [x] `DocumentNumberService` — nomor otomatis `PO/{Bulan Romawi}/{Tahun}/{Urut}`, reset per bulan

## ✅ Fase 5 — Controller & Validasi (Web)

- [x] `PurchaseOrderController` + `StorePurchaseOrderRequest` + `StorePurchasePaymentRequest`
- [x] `SalesOrderController` + `StoreSalesOrderRequest` + `StoreSalesPaymentRequest`
- [x] Routes snippet siap tempel ke `routes/web.php`
- [x] `ProductController`, `CategoryController` (CRUD lengkap, pola sama seperti contoh di atas)
- [x] `SupplierController`, `CustomerController` (CRUD lengkap)
- [x] `ExpenseController`, `ExpenseCategoryController` (CRUD lengkap + pakai `ExpenseService`,
      termasuk `ExpenseService::update()`/`delete()` baru supaya `cash_flows` tetap sinkron)
- [x] `routes/web.php` sudah full diupdate dengan resource routes 6 controller di atas
      (index/show di grup `auth`, create/store/edit/update/destroy di grup `role:superadmin`)
- [x] Model `Category`, `Supplier`, `Customer`, `ExpenseCategory` ditambahkan `$fillable` + relasi yang dibutuhkan controller (`products()`, `purchaseOrders()`, `salesOrders()`, `expenses()`)

## ✅ Fase 6 — Laporan (Reports)

- [x] `ReportService` — Stok breakdown, Laba Rugi, Cash Flow, AP (hutang), AR (piutang)
- [x] `ReportController` + routes
- [ ] View/Blade untuk tiap laporan (atau export Excel/PDF kalau dibutuhkan)

## ✅ Fase 7 — Autentikasi & Otorisasi

- [x] `AuthController` (login/logout) + `LoginRequest` (login pakai `username`, dengan rate-limit)
- [x] `EnsureUserHasRole` middleware (pakai: `->middleware('role:admin,finance')`)
- [x] Daftarkan middleware `role` di Kernel.php (manual, lihat `AUTH_SETUP.md`)

## 🟡 Fase 8 — Views / Frontend (sebagian selesai)

- [x] Layout master `layouts/app.blade.php` — semua view lain wajib `@extends` dari sini
- [x] Sidebar (`layouts/partials/sidebar.blade.php`) — grup menu semua modul, responsive (drawer di mobile)
- [x] Topbar (`layouts/partials/topbar.blade.php`) — hamburger mobile + judul halaman
- [x] Halaman login (`auth/login.blade.php`) — desain 2 kolom, standalone (tanpa sidebar)
- [x] Dashboard (`dashboard.blade.php`) — KPI, ringkasan laba rugi, piutang/hutang, stok menipis
- [ ] Form input PO (dynamic add item baris) — pakai layout master yang sudah ada
- [ ] Form input SO (dynamic add item baris, tampilkan stok tersedia per produk)
- [ ] Halaman detail PO/SO (breakdown batch, histori pembayaran)
- [x] Halaman index untuk Product, Category, Supplier, Customer, Expense, ExpenseCategory —
      1 view per modul, create/edit pakai modal Alpine.js + AJAX (bukan halaman terpisah),
      konsisten dengan token desain (border tipis, bukan shadow). Controller-nya sekarang
      return JSON untuk store/update/destroy (route create/edit dihapus dari `routes/web.php`
      karena tidak dipakai lagi).
- [ ] Halaman laporan (Stok, Laba Rugi, Cash Flow, AP/AR) — Controller & Service sudah siap, tinggal buat view-nya

## ⬜ Fase 9 — Data Pendukung

- [ ] Seeder data contoh (kategori, produk, supplier, customer) untuk development/testing
- [ ] Factory untuk testing otomatis

## ⬜ Fase 10 — Pengujian & Penyempurnaan

- [ ] Unit test `StockService::allocateFifo()` — kasus normal, kasus lintas-batch, kasus stok kurang
- [ ] Unit test perhitungan `ReportService::profitLossReport()`
- [ ] Uji concurrency (2 transaksi jual bersamaan, pastikan tidak oversell)
- [ ] Review index database untuk query laporan yang berat (kalau data sudah banyak)

---

**Belum diputuskan / perlu didiskusikan nanti:**

- Multi-metode pembayaran dalam 1 transaksi → sudah diputuskan: TIDAK, 1 metode per baris.
- Multi-gudang → sudah diputuskan: TIDAK, 1 lokasi saja.
- Barang PO datang bertahap (partial receiving dari 1 PO) → belum dibahas, saat ini asumsi 1 PO item = langsung masuk stok penuh.
- Retur penjualan / retur pembelian ke supplier → `StockService::reverseAllocations()` sudah disiapkan untuk retur penjualan, tapi alur Controller & Request-nya belum dibuat.
