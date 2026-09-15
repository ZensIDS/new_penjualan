<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MODUL "BONGKAR UNIT" (stock conversion / disassembly).
 *
 * Kasus bisnis: client beli barang UTUH (mis. baterai "Huawei B1"), tapi kadang
 * dijual utuh, kadang dibongkar dan dijual ecer per komponen (cell, BMS, casing).
 *
 * Aturan inti yang dipegang desain ini:
 * - Unit utuh dan komponen adalah PRODUK YANG BERBEDA di master produk.
 * - Pembongkaran BUKAN penjualan dan BUKAN pembelian: tidak ada uang keluar/masuk,
 *   jadi tidak menyentuh cash_flow maupun laba rugi.
 * - Yang terjadi hanyalah PEMINDAHAN NILAI: HPP unit utuh (diambil FIFO dari
 *   batch-nya) dipecah ke batch-batch baru milik tiap komponen. Total HPP sebelum
 *   dan sesudah bongkar SAMA (selisih pembulatan disimpan di rounding_diff).
 * - Setelah dibongkar, komponen dijual lewat Sales Order seperti produk biasa,
 *   FIFO & perhitungan laba jalan otomatis tanpa perubahan apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Header pembongkaran ---
        Schema::create('stock_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('conversion_number')->unique(); // BK/IX/2026/001
            $table->date('conversion_date');

            // Produk utuh yang dibongkar + berapa unit yang dibongkar
            $table->foreignId('source_product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('source_qty');

            // Total HPP yang diambil dari batch-batch sumber (hasil FIFO).
            // Nilai inilah yang dibagi habis ke komponen.
            $table->decimal('total_hpp', 15, 2);

            // Cara membagi HPP ke komponen:
            // - percent : user isi persentase per komponen (total harus 100%)
            // - market  : dibagi proporsional terhadap estimasi harga jual x qty
            //             (relative sales value method — paling adil & paling lazim)
            // - manual  : user isi langsung nominal HPP per komponen (total harus = total_hpp)
            $table->enum('allocation_method', ['percent', 'market', 'manual'])->default('market');

            // Sisa pembulatan (biasanya 0 s/d beberapa rupiah) supaya selisihnya
            // tercatat dan bisa ditelusuri, bukan hilang diam-diam.
            $table->decimal('rounding_diff', 15, 2)->default(0);

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['conversion_date', 'source_product_id']);
        });

        // --- Batch sumber yang dipotong (hasil FIFO), pola sama seperti sale_item_allocations ---
        Schema::create('stock_conversion_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_conversion_id')->constrained('stock_conversions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained('stock_batches')->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('qty_taken');
            $table->decimal('buy_price_at_time', 15, 2); // snapshot HPP/unit batch sumber
            $table->decimal('hpp_subtotal', 15, 2);      // qty_taken * buy_price_at_time
            $table->timestamps();

            $table->index('stock_batch_id');
        });

        // --- Komponen hasil bongkar + batch baru yang dilahirkannya ---
        Schema::create('stock_conversion_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_conversion_id')->constrained('stock_conversions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();

            // Batch stok yang dibuat untuk komponen ini. Nullable supaya kalau
            // batch dihapus (pembatalan bongkar) barisnya tidak ikut hilang mendadak.
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();

            $table->integer('qty'); // total qty komponen yang dihasilkan dari source_qty unit

            // Input mentah dari user, disimpan apa adanya supaya bisa diaudit ulang:
            $table->decimal('allocation_percent', 8, 4)->nullable();     // dipakai saat method = percent
            $table->decimal('estimated_sell_price', 15, 2)->nullable();  // dipakai saat method = market

            // Hasil akhir perhitungan:
            $table->decimal('buy_price', 15, 2); // HPP per unit komponen -> masuk ke stock_batches.buy_price
            $table->decimal('hpp_total', 15, 2); // buy_price * qty

            $table->timestamps();

            $table->index(['stock_conversion_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_conversion_results');
        Schema::dropIfExists('stock_conversion_sources');
        Schema::dropIfExists('stock_conversions');
    }
};