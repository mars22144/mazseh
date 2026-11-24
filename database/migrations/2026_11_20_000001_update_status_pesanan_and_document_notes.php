<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perluas status siklus hidup transaksi dan tambah catatan verifikasi dokumen.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transaksi MODIFY COLUMN status_pesanan ENUM('menunggu_pembayaran', 'dikonfirmasi', 'sedang_disewa', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'menunggu_pembayaran'");
        } else {
            Schema::table('transaksi', function (Blueprint $table): void {
                $table->string('status_pesanan', 30)->default('menunggu_pembayaran')->change();
            });
        }

        Schema::table('document_pelanggan', function (Blueprint $table): void {
            if (! Schema::hasColumn('document_pelanggan', 'catatan_verifikasi')) {
                $table->text('catatan_verifikasi')->nullable()->after('status_verifikasi');
            }
        });
    }

    /**
     * Kembalikan perubahan skema status pesanan dan catatan verifikasi.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE transaksi SET status_pesanan = 'dikonfirmasi' WHERE status_pesanan IN ('sedang_disewa', 'selesai')");
            DB::statement("ALTER TABLE transaksi MODIFY COLUMN status_pesanan ENUM('menunggu_pembayaran', 'dikonfirmasi', 'dibatalkan') NOT NULL DEFAULT 'menunggu_pembayaran'");
        }

        Schema::table('document_pelanggan', function (Blueprint $table): void {
            if (Schema::hasColumn('document_pelanggan', 'catatan_verifikasi')) {
                $table->dropColumn('catatan_verifikasi');
            }
        });
    }
};
