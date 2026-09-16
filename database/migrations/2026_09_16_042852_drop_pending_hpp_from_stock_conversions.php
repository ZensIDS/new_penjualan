<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus konsep "HPP disisihkan" (pending_hpp).
 *
 * Dulu, kalau pembongkaran dicatat bertahap, user harus mengetik sendiri berapa
 * rupiah HPP yang disisihkan untuk komponen yang belum diketahui. Itu memaksa
 * user memikirkan hal yang sebetulnya urusan sistem, dan bikin form terasa ribet.
 *
 * Sekarang: SELURUH HPP unit selalu dibagi habis ke komponen yang sudah tercatat
 * saat itu. Begitu komponen berikutnya ditambahkan lewat "Lanjutkan Bongkar",
 * HPP otomatis dibagi ulang ke semua komponen (mekanisme pembagian ulang ini
 * memang sudah jalan tiap kali ada penjualan — lihat StockConversionService).
 *
 * Kolom status tetap dipakai, artinya cuma: "masih boleh ditambah komponen lagi"
 * vs "sudah lengkap".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_conversions', function (Blueprint $table) {
            $table->dropColumn('pending_hpp');
        });
    }

    public function down(): void
    {
        Schema::table('stock_conversions', function (Blueprint $table) {
            $table->decimal('pending_hpp', 15, 2)->default(0)->after('total_hpp');
        });
    }
};