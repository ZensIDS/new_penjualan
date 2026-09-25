<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyederhanakan cara HPP unit utuh dibagi ke komponen.
 *
 * SEBELUM: user memilih sendiri metode pembagian (percent / market / manual) dan
 * untuk metode market harus mengetik "estimasi harga jual per unit" tiap komponen.
 *
 * MASALAHNYA: harga jual komponen itu fluktuatif — beda customer, beda hasil
 * negosiasi. Angka estimasi yang diketik saat membongkar hampir pasti meleset,
 * padahal angka itulah yang menentukan HPP tiap komponen.
 *
 * SESUDAH: hanya ada SATU cara pembagian (yang paling lazim di akuntansi:
 * relative sales value / proporsi harga jual), dan harga jual yang dipakai
 * TIDAK LAGI diketik user — sistem memakai harga jual RIIL yang terakhir
 * terjadi di Sales Order untuk komponen tersebut, lalu menghitung ulang
 * pembagian HPP di balik layar setiap kali ada penjualan baru.
 *
 * Konsekuensi skema:
 * - stock_conversions.allocation_method  -> dihapus (cuma ada 1 metode sekarang)
 * - stock_conversion_results.allocation_percent  -> dihapus (metode percent hilang)
 * - stock_conversion_results.estimated_sell_price -> diganti ref_sell_price,
 *   artinya bukan lagi "tebakan user" melainkan "harga jual acuan yang dipakai
 *   sistem saat pembagian terakhir dihitung" (untuk audit: kenapa HPP-nya segini).
 *
 * Pembongkaran lama tetap aman: nilai HPP yang sudah tersimpan di tiap batch
 * tidak diutak-atik migration ini, yang hilang hanya kolom input metodenya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_conversion_results', function (Blueprint $table) {
            // Harga jual acuan yang dipakai sistem saat membagi HPP terakhir kali.
            $table->decimal('ref_sell_price', 15, 2)->nullable()->after('qty');
        });

        // Bawa nilai estimasi lama jadi acuan awal, supaya angka pembongkaran
        // yang sudah ada tidak berubah sampai ada penjualan riil yang memperbarui.
        if (Schema::hasColumn('stock_conversion_results', 'estimated_sell_price')) {
            \DB::statement('UPDATE stock_conversion_results SET ref_sell_price = estimated_sell_price');
        }

        Schema::table('stock_conversion_results', function (Blueprint $table) {
            $table->dropColumn(['allocation_percent', 'estimated_sell_price']);
        });

        Schema::table('stock_conversions', function (Blueprint $table) {
            $table->dropColumn('allocation_method');
        });
    }

    public function down(): void
    {
        Schema::table('stock_conversions', function (Blueprint $table) {
            $table->enum('allocation_method', ['percent', 'market', 'manual'])
                ->default('market')
                ->after('total_hpp');
        });

        Schema::table('stock_conversion_results', function (Blueprint $table) {
            $table->decimal('allocation_percent', 8, 4)->nullable()->after('qty');
            $table->decimal('estimated_sell_price', 15, 2)->nullable()->after('allocation_percent');
        });

        \DB::statement('UPDATE stock_conversion_results SET estimated_sell_price = ref_sell_price');

        Schema::table('stock_conversion_results', function (Blueprint $table) {
            $table->dropColumn('ref_sell_price');
        });
    }
};