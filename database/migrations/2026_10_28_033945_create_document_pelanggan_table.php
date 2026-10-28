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
        Schema::create('document_pelanggan', function (Blueprint $table) {
            $table->id('id_dokumen');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('file_ktp', 255);
            $table->string('file_sim', 255);
            $table->enum('status_verifikasi', ['belum_upload', 'menunggu_validasi', 'disetujui', 'ditolak'])->default('belum_upload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_pelanggan');
    }
};
