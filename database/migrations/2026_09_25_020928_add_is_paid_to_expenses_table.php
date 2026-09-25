<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menandai apakah sebuah Expense sudah "Lunas" (sudah tercatat di
     * ledger cash_flows & ikut dihitung ke Laporan Pengeluaran/Laba Rugi)
     * atau masih berupa catatan biaya yang menunggu pelunasan.
     *
     * Default TRUE supaya expense biasa (bukan dari "Biaya Tambahan PO")
     * tetap berperilaku persis seperti sebelumnya: begitu dibuat, langsung
     * dianggap lunas & langsung tercatat ke kas keluar + laporan.
     *
     * Khusus Expense yang berasal dari "Biaya Tambahan PO"
     * (purchase_order_id terisi), baris dibuat dengan is_paid=false dulu
     * (lihat ExpenseService::createUnpaid & PurchaseOrderService::addExtraCost)
     * — belum masuk cash_flow/Laporan Pengeluaran/Laba Rugi sampai user
     * menekan tombol "Lunas" di halaman detail PO (ExpenseService::markPaid).
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('is_paid')->default(true)->after('purchase_order_id');

            $table->index('is_paid');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['is_paid']);
            $table->dropColumn('is_paid');
        });
    }
};