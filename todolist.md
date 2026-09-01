# TODO — Sistem Inventaris, Operasional & Keuangan

Terakhir diupdate: tahap Laporan (Reports) selesai dibuat.

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
- [ ] `ProductController`, `CategoryController` (CRUD sederhana, pola sama seperti contoh di atas)
- [ ] `SupplierController`, `CustomerController` (CRUD sederhana)
- [ ] `ExpenseController`, `ExpenseCategoryController` (CRUD sederhana + pakai `ExpenseService`)

## ✅ Fase 6 — Laporan (Reports)

- [x] `ReportService` — Stok breakdown, Laba Rugi, Cash Flow, AP (hutang), AR (piutang)
- [x] `ReportController` + routes
- [ ] View/Blade untuk tiap laporan (atau export Excel/PDF kalau dibutuhkan)

## ⬜ Fase 7 — Autentikasi & Otorisasi

- [ ] Setup Laravel Breeze/Fortify dengan login `username` (bukan email) — sesuaikan `config/auth.php`
- [ ] Middleware/gate berdasarkan `role` (admin/kasir/gudang/finance) untuk membatasi akses menu

## ⬜ Fase 8 — Views / Frontend

- [ ] Layout dasar (sidebar menu: Pembelian, Stok, Penjualan, Operasional, Laporan)
- [ ] Form input PO (dynamic add item baris)
- [ ] Form input SO (dynamic add item baris, tampilkan stok tersedia per produk)
- [ ] Halaman detail PO/SO (breakdown batch, histori pembayaran)
- [ ] Dashboard ringkasan (total stok, piutang jatuh tempo, laba bulan ini, dll)

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
