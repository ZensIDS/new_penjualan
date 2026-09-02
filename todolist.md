# TODO — Sistem Inventaris, Operasional & Keuangan

**Fitur baru — Export Excel untuk semua Laporan:** `ExcelStyler` (helper styling terpusat di
`app/Services/Export/`) dipakai bareng oleh `ReportExportController` biar 5 laporan (Stok, Laba
Rugi, Arus Kas, Hutang/AP, Piutang/AR) punya tampilan Excel yang konsisten (header gelap+putih,
zebra row, format Rupiah otomatis, baris total di-highlight, freeze header, auto width) tanpa
nulis style dari nol tiap laporan. Route baru di grup `reports.export.*`. Laporan Stok &
AP/AR dapat rincian tambahan (Detail Batch / Riwayat Pembayaran) supaya nggak cuma ringkasan.

**Peningkatan — Laporan Stok kini dijabarkan per batch:** baik di halaman web
(`reports/stock.blade.php`, expand/collapse per produk) maupun di export Excel-nya, tiap produk
sekarang menampilkan rincian tiap batch stok (FIFO) — tanggal masuk, harga beli, qty masuk, qty
sisa, nilai per batch — bukan cuma total per produk. Total keseluruhan tetap ditampilkan di
footer/baris total.

**Bug fix — Nominal harga 100x lipat saat masuk halaman Edit PO/SO & modal Edit Expense:**
`buy_price`/`sell_price`/`amount` di-cast `decimal:2` di model, jadi di-serialize ke JSON sebagai
string (`"45000.00"`). Helper `formatRupiah`/`parseRupiah` di `layouts/app.blade.php` cuma
membuang karakter non-digit, jadi `"45000.00"` → `"4500000"` (Rp 4.500.000, bukan Rp 45.000).
Fix: nilai dibulatkan ke integer dulu sebelum dikirim ke Alpine, baik di sisi Blade
(`purchase-orders/edit.blade.php`, `sales-orders/edit.blade.php`, pakai `(int) round(...)`)
maupun di sisi JS (`expenses/index.blade.php`, `openEdit()` pakai `Math.round(parseFloat(...))`).
**Wajib diikuti**: field uang manapun yang dilempar ke Alpine lewat `Illuminate\Support\Js::from()`
harus di-cast integer/float dulu, jangan biarkan string hasil cast `decimal:2` mentah.

**Fitur baru — Edit pembayaran PO & SO (koreksi salah input):** riwayat pembayaran di halaman
detail PO/SO sekarang punya tombol edit inline (khusus superadmin) — form muncul di tempat
(Alpine `paymentEditRow`), bisa ubah tanggal/nominal/metode/catatan.
`PurchaseOrderService::updatePayment()` / `SalesOrderService::updatePayment()` menghitung ulang
`paid_amount` & `payment_status` PO/SO secara otomatis dalam satu transaksi DB (row PO/SO
di-lock dulu), dan `CashFlowService::updateForSource()` menyinkronkan ulang entry `cash_flows`
terkait supaya Laporan Arus Kas tetap akurat. **Batas nominal edit dihitung per-payment**:
`nominal lama pembayaran ini + sisa hutang/piutang saat ini` — otomatis benar walau PO/SO sudah
dibayar bertahap lebih dari 1 kali, karena `remaining_balance` yang ditampilkan sudah bersih dari
semua pembayaran lain. Divalidasi di 2 lapis: client-side (JS clamp input + tombol "isi
maksimal") dan server-side (`UpdatePurchasePaymentRequest`/`UpdateSalesPaymentRequest` +
exception di service kalau tetap kelebihan). Route baru: `PUT
{resource}/{order}/payments/{payment}`.

**Fitur baru — Edit & Hapus PO/SO (khusus transaksi yang belum dibayar sama sekali):**

- `PurchaseOrder`/`SalesOrder` dapat method `canBeModified()`: true kalau `payment_status ===
'unpaid'` DAN `paid_amount <= 0`. Dipakai di Request (`authorize()`), Controller, dan view
  (tombol Edit/Hapus cuma muncul kalau ini true).
- PO: `PurchaseOrderService::update()`/`delete()` — cuma boleh jalan kalau SEMUA batch stok
  dari PO ini juga belum kepakai sama sekali (`StockService::isPurchaseItemBatchUsed()`),
  soalnya status bayar & pergerakan stok itu dua hal terpisah — PO bisa saja belum dibayar
  tapi barangnya sudah kadung terjual. Batch lama dihapus (`StockService::removeUnusedPurchaseBatch()`)
  lalu dibuat ulang.
- SO: `SalesOrderService::update()`/`delete()` — alokasi FIFO lama dikembalikan dulu ke batch
  asal (`StockService::reverseAllocations()`, yang bug lamanya juga sudah diperbaiki: sekarang
  sync `qty_on_hand` untuk SEMUA produk yang kena, bukan cuma produk dari alokasi terakhir),
  baru item baru dialokasikan ulang.
- Route baru: `{resource}.edit` (GET), `{resource}.update` (PUT), `{resource}.destroy` (DELETE)
  untuk `purchase-orders` & `sales-orders`, semua di grup `role:superadmin`.
- View baru: `purchase-orders/edit.blade.php`, `sales-orders/edit.blade.php`.

**Bug fix — PO bisa input produk sama di baris berbeda:** `StorePurchaseOrderRequest` ternyata
dari awal tidak punya validasi cegah produk dobel (beda dengan `StoreSalesOrderRequest` yang
sudah punya), dan JS `initProductSelect` di `purchase-orders/create.blade.php` juga tidak ada
pengecekan sama sekali. Sudah disamakan dengan pola SO: validasi server-side di
`withValidator()` (dipasang juga di `UpdatePurchaseOrderRequest`) + pengecekan JS yang
menolak & mengembalikan pilihan kalau produk sudah dipakai di baris lain.

**Bug fix — SO: select produk auto-pilih produk pertama:** select2 di `sales-orders/create.blade.php`
dibangun murni dari opsi `data:` (bukan `<option>` statis kayak PO), dan tanpa entri kosong di
array itu select2 otomatis menganggap opsi pertama sebagai default terpilih — beda dari yang
terlihat (placeholder "Pilih produk" doang tampil visual, tapi value sebenarnya sudah keisi
produk pertama sejak render awal, jadi pengecekan "produk sudah dipakai di baris lain" jadi
gampang miss di baris-baris yang belum sempat disentuh user). Fix: tambahkan entri kosong
`{ id: '', text: '— Pilih produk —' }` di depan array `data`, di form create maupun edit.

**Fitur baru — Asal Penjualan (channel) di SO:** kolom `source` (string, default `offline`) di
tabel `sales_orders`, daftar valid ada di `App\Models\SalesOrder::SOURCES` (`offline`,
`whatsapp`, `shopee` — gampang ditambah tanpa migration baru). Selalu wajib diisi
(`required|in:...`), select-nya ditaruh sebaris dengan Catatan di form create & edit, dan
ditampilkan sebagai badge di halaman detail SO.

**Akan Dilanjutkan - Penambahan Pagination & Return Order:** Setiap page harus ada pagination, karena nantinya akan ada ribuan data. sehingga harusnya juga tidak hanya pagination tapi termasuk bagaimana agar page tetap optimal meskipun datanya sudah ribuan.

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
      — plus `edit()`/`update()`/`destroy()` + `UpdatePurchaseOrderRequest` (edit/hapus cuma
      untuk PO yang belum dibayar sama sekali & barangnya belum kepakai)
- [x] `SalesOrderController` + `StoreSalesOrderRequest` + `StoreSalesPaymentRequest`
      — plus `edit()`/`update()`/`destroy()` + `UpdateSalesOrderRequest` (edit/hapus cuma
      untuk transaksi yang belum dibayar sama sekali)
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
- [x] View/Blade untuk tiap laporan (Stok, Laba Rugi, Arus Kas, AP, AR) — sudah ada semua
- [x] Export Excel untuk semua laporan (`ReportExportController` + `ExcelStyler`, lihat catatan
      fitur baru di atas)

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
- [x] `PurchaseOrderController::create()` + route `purchase-orders.create` (didaftarkan
      SEBELUM `resource(['index','show'])` supaya path literal `/create` tidak ketangkep
      wildcard `{purchaseOrder}` milik route `show`)
- [x] Halaman index PO (`purchase-orders/index.blade.php`) — tabel + status badge + link detail
- [x] Form input PO (`purchase-orders/create.blade.php`) — dynamic add/remove item baris pakai
      Alpine, select2 per baris (produk + supplier), input Rupiah terformat, form submit biasa
      (bukan AJAX, sesuai pola controller yang redirect/back()->withErrors())
- [x] Form input SO (dynamic add item baris, tampilkan stok tersedia per produk)
- [x] Halaman detail PO (`purchase-orders/show.blade.php`) — breakdown item + sisa batch,
      ringkasan pembayaran, riwayat pembayaran, form tambah pembayaran (superadmin, muncul
      kalau masih ada sisa hutang), edit inline per pembayaran (lihat catatan fitur baru di atas)
- [x] Halaman detail SO (breakdown batch FIFO yang kepakai, histori pembayaran, edit inline per
      pembayaran sama seperti PO)
- [x] Halaman Stok (`stock/index.blade.php`, route `stock.index`, menu "Persediaan → Stok") —
      view cek cepat: search instan nama produk (client-side), expand/collapse breakdown batch
      per produk, KPI ringkas. Data dari `ReportService::stockReport()` yang sama dipakai
      `reports.stock`, cuma tampilannya beda (operasional harian vs laporan formal)
- [x] Halaman index untuk Product, Category, Supplier, Customer, Expense, ExpenseCategory —
      1 view per modul, create/edit pakai modal Alpine.js + AJAX (bukan halaman terpisah),
      konsisten dengan token desain (border tipis, bukan shadow). Controller-nya sekarang
      return JSON untuk store/update/destroy (route create/edit dihapus dari `routes/web.php`
      karena tidak dipakai lagi).
- [x] Halaman laporan (Stok, Laba Rugi, Cash Flow, AP/AR) — sudah ada semua + export Excel
      (lihat Fase 6 & catatan fitur baru di atas)
- [ ] Penambahan Pagination pada setiap page yang memungkinkan diberikan pagination

## 🟡 Fase 9 — Data Pendukung

- [x] Seeder data contoh (kategori, produk, supplier, customer) untuk development/testing —
      `CategorySeeder` (6 kategori), `ProductSeeder` (18 produk, terhubung ke kategori via nama,
      tanpa `sku` karena kolom itu sudah sengaja didrop dari tabel `products`, `qty_on_hand`
      sengaja 0 karena itu cache dari `StockService`), `SupplierSeeder` (4 supplier),
      `CustomerSeeder` (5 customer). Semua sudah didaftarkan di `DatabaseSeeder` dengan urutan
      `UserSeeder → CategorySeeder → ProductSeeder → SupplierSeeder → CustomerSeeder`.

## ⬜ Fase 10 — Pengujian & Penyempurnaan

- [ ] Unit test `StockService::allocateFifo()` — kasus normal, kasus lintas-batch, kasus stok kurang
- [ ] Unit test perhitungan `ReportService::profitLossReport()`
- [ ] Uji concurrency (2 transaksi jual bersamaan, pastikan tidak oversell)
- [ ] Review index database untuk query laporan yang berat (kalau data sudah banyak)

---

**Belum diputuskan / perlu didiskusikan nanti:**

- Retur penjualan / retur pembelian ke supplier → `StockService::reverseAllocations()` sudah disiapkan untuk retur penjualan, tapi alur Controller & Request-nya belum dibuat.
