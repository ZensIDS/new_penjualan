<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header "Distribusi Bagi Hasil": satu baris untuk setiap KEJADIAN
     * pembagian hasil (mis. tiap kali owner benar-benar mencairkan/transfer
     * bagian laba ke orang-orang di master profit_shares). Beda dengan
     * profit_shares (cuma setting %), tabel ini adalah TRANSAKSI riil —
     * jadi jadi sumber histori "kapan & berapa total pernah dibagikan".
     *
     * PENTING: transaksi ini SENGAJA tidak menyentuh Laporan Laba Rugi
     * (lihat ReportService::profitLossReport — tidak ada query ke tabel
     * ini), karena laba sudah dihitung & "dimiliki" oleh masing-masing
     * orang sejak saat itu juga (profit_shares cuma alokasi kepemilikan).
     * Mencairkannya bukan biaya usaha, jadi tidak boleh mengurangi Laba
     * Bersih lagi (kalau dihitung, laba akan "dipotong dua kali").
     * Tapi karena kas beneran keluar dari rekening/kas usaha, transaksi
     * ini TETAP dicatat sebagai kas keluar di cash_flows (lihat
     * ProfitShareDistributionService), supaya Laporan Arus Kas akurat.
     */
    public function up(): void
    {
        Schema::create('profit_share_distributions', function (Blueprint $table) {
            $table->id();
            $table->date('distribution_date');
            // Total keseluruhan yang dibagikan pada transaksi ini = SUM(amount) semua item.
            // Disimpan langsung (bukan dihitung ulang tiap kali) supaya listing histori
            // tidak perlu join agregasi ke tabel item setiap kali ditampilkan.
            $table->decimal('total_amount', 15, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('distribution_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_share_distributions');
    }
};
