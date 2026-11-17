<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sesi_chat_ai', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('sesi_chat_ai', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sesi_chat_ai')->whereNull('user_id')->delete();

        Schema::table('sesi_chat_ai', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('sesi_chat_ai', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
