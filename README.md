Saya ingin membuat sistem manajemen inventaris, operasional, dan keuangan berbasis web menggunakan Laravel 9 dan MySQL (phpMyAdmin). Tolong bantu saya merancang arsitektur kode, skema database (migration), dan logika sistem secara bertahap.
Berikut adalah gambaran detail sistem dan aturan bisnis (business logic) yang harus diterapkan:

### 1. KONTETSK & LATAR BELAKANG

Sistem ini dibuat untuk bisnis penjualan baterai panel surya, sepeda listrik, dan komponen terkait. Saat ini pencatatan masih menggunakan spreadsheet secara manual, sehingga membutuhkan sistem terintegrasi yang mencatat seluruh alur mulai dari Pembelian (PO), Stok, Operasional, Penjualan, hingga Laporan Keuangan.

### 2. ATURAN BISNIS & LOGIKA UTAMA (BUSINESS LOGIC)

#### A. Pembelian (Purchase Order / PO) & Hutang

- Mengakomodasi fleksibilitas pembayaran ke supplier: Cash/Tunai, Belum Dibayar (Unpaid), dan Pembayaran Bertahap/Termin (Partial).
- Setiap PO harus mencatat histori pembayarannya.
- Barang dapat masuk ke stok terlebih dahulu meskipun pembayaran belum lunas.
- Harga beli per unit produk dapat berbeda-beda pada setiap PO/transaksi pembelian.

#### B. Persediaan & Metode FIFO (Lot / Batch Tracking)

- Stok tidak digabung begitu saja menjadi harga rata-rata, melainkan dipisah berdasarkan _Batch Pembelian_ (tanggal beli dan harga beli masing-masing).
- Contoh Logika:
    - Hari 1 beli 5 unit @ Rp 1.000.000.
    - Hari 2 beli 6 unit @ Rp 1.100.000.
    - Total stok di sistem mencatat 11 unit (terbagi 2 batch dan di tampilkan breakdown nya).
- Saat Penjualan: Sistem secara otomatis memotong stok dari batch tertua (First-In, First-Out / FIFO).
    - Jika terjual 4 unit, sistem akan memotong 3 unit dari Batch Hari 1 dan 1 unit dari Batch Hari 2.
- HPP (Harga Pokok Penjualan) dihitung secara riil berdasarkan harga beli asli dari unit/batch yang terpakai.

#### C. Penjualan & Piutang

- Harga jual per produk ke customer bersifat dinamis (dapat diubah sesuai kesepakatan/negosiasi per transaksi).
- Mengakomodasi pembayaran dari customer secara Cash, Unpaid, maupun Termin/Cicilan.
- Setiap transaksi penjualan secara otomatis menghitung HPP (beban pokok) berdasarkan pemotongan stok FIFO di atas.

#### D. Pengeluaran Operasional

- CRUD sederhana untuk pencatatan biaya operasional harian/bulanan (Gaji, Listrik, Sewa, Transportasi, dll).
- Kategori biaya operasional bersifat dinamis.

#### E. Pelaporan

Sistem wajib menghasilkan laporan akurat sebagai berikut:

1. Laporan Stok: Menampilkan total stok beserta rincian breakdown per batch pembelian.
2. Laporan Laba Rugi (Profit & Loss):
    - Pendapatan Penjualan - Total HPP (FIFO) = Laba Kotor.
    - Laba Kotor - Total Biaya Operasional = Laba Bersih.
3. Laporan Arus Kas (Cash Flow): Pencatatan real-time kas masuk (pembayaran customer) dan kas keluar (pembelian supplier + operasional).
4. Laporan Hutang & Piutang (AP & AR): Tracking sisa tagihan dan histori pembayaran termin untuk supplier dan customer.

---

### TUGAS PERTAMA KAMU:

Tolong konfirmasi pemahamanmu terhadap alur di atas. Jika sudah paham, langkah awal buatkan saya **Migration Laravel 9 lengkap** (beserta relasi Foreign Key dan tipe datanya) yang mencakup seluruh struktur database untuk memenuhi kebutuhan sistem ini.
