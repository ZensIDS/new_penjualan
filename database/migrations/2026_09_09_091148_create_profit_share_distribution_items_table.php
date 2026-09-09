<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rincian per-orang dari satu transaksi profit_share_distributions.
     *
     * name & percentage di-SNAPSHOT (disalin) dari profit_shares pada saat
     * transaksi dibuat — bukan hanya nyimpen profit_share_id lalu join —
     * supaya histori tetap akurat walau nanti persentase seseorang diubah,
     * dia dinonaktifkan, atau bahkan dihapus dari master profit_shares
     * (pola snapshot ini sama seperti kolom description di expenses/cash_flows
     * yang tidak hilang meski master kategori berubah).
     */
    public function up(): void
    {
        Schema::create('profit_share_distribution_items', function (Blueprint $table) {
            $table->id();

            // Menggunakan tipe data unsignedBigInteger dan foreign key manual agar nama constraint pendek
            $table->unsignedBigInteger('profit_share_distribution_id');
            $table->foreign('profit_share_distribution_id', 'psd_items_dist_fk')
                ->references('id')
                ->on('profit_share_distributions')
                ->cascadeOnDelete();

            // Nullable + nullOnDelete: kalau orangnya nanti dihapus dari master
            // profit_shares, baris histori pembagian yang sudah terjadi TETAP ada
            // (nama & persentase sudah ke-snapshot di kolom name/percentage).
            $table->unsignedBigInteger('profit_share_id')->nullable();
            $table->foreign('profit_share_id', 'psd_items_ps_fk')
                ->references('id')
                ->on('profit_shares')
                ->nullOnDelete();

            $table->string('name');
            $table->decimal('percentage', 5, 2)->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index('profit_share_distribution_id', 'psd_items_distribution_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_share_distribution_items');
    }
};
