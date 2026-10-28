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
        Schema::create('pemeliharaan', function (Blueprint $table) {
            $table->id('id_pemeliharaan');
            $table->unsignedBigInteger('id_mobil');
            $table->date('tanggal_masuk');
            $table->date('tanggal_selesai')->nullable();
            $table->string('jenis_perawatan', 100);
            $table->decimal('total_biaya', 10, 2);
            $table->text('keterangan_bengkel')->nullable();

            $table->foreign('id_mobil')->references('id_mobil')->on('katalog_mobil')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemeliharaan');
    }
};
