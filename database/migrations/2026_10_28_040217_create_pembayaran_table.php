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
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id('id_pembayaran');
            $table->unsignedBigInteger('id_transaksi')->unique();
            $table->unsignedBigInteger('kasir_id')->nullable();
            $table->decimal('jumlah_bayar', 10, 2);
            $table->enum('status_pembayaran', ['pending', 'lunas', 'gagal'])->default('pending');
            $table->decimal('jumlah_refund', 10, 2)->default(0);
            $table->enum('status_refund', ['tidak_ada', 'menunggu_proses', 'selesai'])->default('tidak_ada');
            $table->timestamp('waktu_bayar')->nullable();
            
            $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi')->onDelete('cascade');
            $table->foreign('kasir_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
