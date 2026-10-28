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
        Schema::create('sesi_chat_ai', function (Blueprint $table) {
            $table->id('id_sesi');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('pesan_pelanggan');
            $table->text('balasan_ai');
            $table->enum('status_sesi', ['diselesaikan_ai', 'dialihkan_ke_wa'])->default('diselesaikan_ai');
            $table->timestamp('waktu_sesi')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sesi_chat_ai');
    }
};
