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
        Schema::create('katalog_mobil', function (Blueprint $table) {
            $table->id('id_mobil');
            $table->unsignedBigInterger('id_kategori');
            $table->string('plat_nomor', 20)->unique();
            $table->string('merk_model', 100);
            $table->decimal('harga_sewa', 10, 2);
            $table->enum('status_ketersediaan', ['tersedia', 'disewakan', 'maintenance'])->default('tersedia');
            $table->integer('versi')->default(0);

            $table->foreign('id_karegori')->references('id_kategori')->on('kategori_mobil')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('katalog_mobil');
    }
};
