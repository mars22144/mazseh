<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom jejak verifikasi kasir pada tabel pembayaran.
     */
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table): void {
            $table->timestamp('waktu_verifikasi')->nullable()->after('waktu_bayar');
            $table->text('catatan_kasir')->nullable()->after('waktu_verifikasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table): void {
            $table->dropColumn(['waktu_verifikasi', 'catatan_kasir']);
        });
    }
};
