<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menautkan Expense ke PurchaseOrder (opsional/nullable) — dipakai untuk
     * fitur "Biaya Tambahan PO" (ongkir, biaya bongkar, dll) yang diinput &
     * dikelola dari halaman detail PO, tapi tetap tercatat sebagai Expense
     * biasa (masuk Laporan Pengeluaran & Laba Rugi seperti expense lainnya).
     *
     * Sengaja nullOnDelete (bukan cascadeOnDelete): penghapusan PO beserta
     * biaya tambahannya SELALU dilakukan lewat PurchaseOrderService::delete(),
     * yang menghapus Expense + entry cash_flow terkait secara eksplisit satu
     * per satu (supaya ledger kas ikut bersih, bukan cuma baris expense-nya).
     * Kalau FK ini cascadeOnDelete, penghapusan PO lewat jalur lain (mis. query
     * builder langsung / tinker) bisa menghapus baris expenses tanpa sempat
     * membersihkan cash_flow-nya dan meninggalkan entry "hantu" di arus kas.
     * nullOnDelete jauh lebih aman: expense-nya tetap ada (riwayat biaya tidak
     * hilang), cuma tautan ke PO yang sudah tidak ada itu diputus.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->after('id')
                ->constrained('purchase_orders')
                ->nullOnDelete();

            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
        });
    }
};