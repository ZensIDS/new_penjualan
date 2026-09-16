<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sampai sekarang SETIAP batch stok wajib lahir dari 1 baris purchase_order_item
 * (kolom purchase_order_item_id NOT NULL + unique). Dengan adanya fitur
 * "Bongkar Unit" (lihat migration berikutnya), batch stok bisa lahir dari
 * sumber kedua: hasil pembongkaran unit utuh menjadi komponen.
 *
 * Yang diubah:
 * 1. purchase_order_item_id jadi NULLABLE (batch hasil bongkar tidak punya PO item).
 *    Unique tetap dipertahankan — di MySQL/PostgreSQL, unique index MENGIZINKAN
 *    banyak baris NULL, jadi aturan "1 PO item = 1 batch" tetap terjaga.
 * 2. Tambah kolom origin_type untuk membedakan asal batch secara eksplisit,
 *    supaya query/laporan tidak perlu menebak dari NULL-nya foreign key.
 *
 * CATATAN IMPLEMENTASI: sengaja TIDAK memakai Blueprint::change() supaya tidak
 * butuh doctrine/dbal. MySQL tidak bisa mengubah nullable pada kolom yang masih
 * terikat foreign key, jadi FK-nya di-drop dulu, kolom diubah via raw SQL
 * (MODIFY), lalu FK-nya dipasang lagi. Ditulis khusus untuk MySQL/MariaDB
 * (sesuai project ini) — kalau connection-mu Postgres/SQLite, beri tahu saya,
 * sintaksnya beda.
 */
return new class extends Migration
{
    public function up(): void
    {
        $foreignKey = $this->findForeignKeyName();

        Schema::table('stock_batches', function (Blueprint $table) use ($foreignKey) {
            $table->dropForeign($foreignKey);
        });

        // Definisi kolom disamakan persis dengan migration aslinya
        // (foreignId = BIGINT UNSIGNED), cuma menghapus NOT NULL.
        DB::statement('ALTER TABLE stock_batches MODIFY purchase_order_item_id BIGINT UNSIGNED NULL');

        Schema::table('stock_batches', function (Blueprint $table) {
            $table->foreign('purchase_order_item_id')
                ->references('id')->on('purchase_order_items')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::table('stock_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_batches', 'origin_type')) {
                $table->enum('origin_type', ['purchase', 'conversion'])
                    ->default('purchase')
                    ->after('purchase_order_item_id');
                $table->index(['product_id', 'origin_type']);
            }
        });

        // Semua batch lama pasti berasal dari pembelian.
        DB::table('stock_batches')->whereNull('origin_type')->update(['origin_type' => 'purchase']);
    }

    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'origin_type']);
            $table->dropColumn('origin_type');
        });

        // Catatan: mengembalikan purchase_order_item_id jadi NOT NULL hanya aman
        // kalau semua batch hasil bongkar sudah dihapus lebih dulu.
        $foreignKey = $this->findForeignKeyName();

        Schema::table('stock_batches', function (Blueprint $table) use ($foreignKey) {
            $table->dropForeign($foreignKey);
        });

        DB::statement('ALTER TABLE stock_batches MODIFY purchase_order_item_id BIGINT UNSIGNED NOT NULL');

        Schema::table('stock_batches', function (Blueprint $table) {
            $table->foreign('purchase_order_item_id')
                ->references('id')->on('purchase_order_items')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Cari nama constraint foreign key untuk purchase_order_item_id secara
     * dinamis (bukan hardcode 'stock_batches_purchase_order_item_id_foreign'),
     * supaya migration tetap jalan walau nama constraint-nya sempat diubah manual.
     */
    protected function findForeignKeyName(): string
    {
        $row = DB::selectOne(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'stock_batches'
               AND COLUMN_NAME = 'purchase_order_item_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );

        if (! $row) {
            throw new \RuntimeException(
                'Tidak menemukan foreign key untuk stock_batches.purchase_order_item_id. Cek manual nama constraint-nya lalu sesuaikan migration ini.'
            );
        }

        return $row->CONSTRAINT_NAME;
    }
};