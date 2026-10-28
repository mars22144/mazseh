<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id('id_transaksi');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('id_mobil');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->decimal('total_harga', 10, 2);
            $table->enum('status_pesanan', ['menunggu_pembayaran', 'dikonfirmasi', 'dibatalkan'])->default('menunggu_pembayaran');
            $table->text('alasan_batal')->nullable();
            $table->string('latitude', 50)->nullable();
            $table->string('longitude', 50)->nullable();
            $table->text('titik_jemput')->nullable();
            $table->timestamp('waktu_pesan')->useCurrent();

            $table->foreign('id_mobil')->references('id_mobil')->on('katalog_mobil')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
