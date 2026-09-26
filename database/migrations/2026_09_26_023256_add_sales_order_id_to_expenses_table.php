<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menautkan Expense ke SalesOrder (opsional/nullable) — dipakai untuk
     * fitur "Biaya Tambahan SO" (ongkir kirim ke customer, biaya packing,
     * dll) yang diinput & dikelola dari halaman detail SO, tapi tetap
     * tercatat sebagai Expense biasa (masuk Laporan Pengeluaran & Laba
     * Rugi seperti expense lainnya). Sama persis pola purchase_order_id
     * (lihat migration add_purchase_order_id_to_expenses_table).
     *
     * nullOnDelete (bukan cascadeOnDelete) dengan alasan yang sama: hapus
     * SO SELALU lewat SalesOrderService::delete(), yang menghapus Expense
     * + entry cash_flow terkait secara eksplisit satu per satu supaya
     * ledger kas ikut bersih.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('sales_order_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('sales_orders')
                ->nullOnDelete();

            $table->index('sales_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_order_id');
        });
    }
};