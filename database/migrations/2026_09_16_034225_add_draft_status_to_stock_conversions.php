<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan "pembongkaran belum selesai" (Skenario B).
 *
 * Sebelumnya 1 pembongkaran = 1 transaksi sekali-jalan: semua komponen wajib
 * diketahui saat submit, dan seluruh total_hpp langsung dibagi habis saat itu juga.
 *
 * Sekarang pembongkaran boleh dicatat BERTAHAP:
 * - status = 'draft'   : belum semua komponen diinput. Sebagian HPP unit yang
 *                        sudah dipotong FIFO belum dibagi ke komponen manapun —
 *                        nilainya "nongkrong" dulu di kolom pending_hpp (bukan di
 *                        stok siapa pun) sambil menunggu transaksi dilanjutkan.
 * - status = 'selesai' : semua komponen sudah tercatat, pending_hpp = 0. Ini
 *                        status default supaya pembongkaran sekali-jalan yang
 *                        sudah ada (dan yang tidak memilih mode bertahap) tidak
 *                        berubah perilakunya sama sekali.
 *
 * total_hpp TIDAK PERNAH berubah setelah dibuat (sudah final begitu FIFO jalan
 * di langkah pertama) — yang berubah tiap kali "Lanjutkan Bongkar" cuma cara
 * pending_hpp dipecah ulang ke komponen yang makin lengkap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_conversions', function (Blueprint $table) {
            $table->enum('status', ['draft', 'selesai'])
                ->default('selesai')
                ->after('allocation_method');

            $table->decimal('pending_hpp', 15, 2)
                ->default(0)
                ->after('total_hpp');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('stock_conversions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'pending_hpp']);
        });
    }
};