<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        DB::table('users')->insert([
            [
                'name' => 'Super Admin',
                'email' => 'admin@rental.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Staf Kasir',
                'email' => 'kasir@rental.com',
                'password' => Hash::make('password123'),
                'role' => 'kasir',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Budi Pelanggan',
                'email' => 'budi@gmail.com',
                'password' => Hash::make('password123'),
                'role' => 'pelanggan',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        DB::table('kategori_mobil')->insert([
            ['nama_kategori' => 'MPV'],
            ['nama_kategori' => 'SUV'],
            ['nama_kategori' => 'Sedan'],
        ]);

        DB::table('katalog_mobil')->insert([
            [
                'id_kategori' => 1, 
                'plat_nomor' => 'B 1234 ABC',
                'merk_model' => 'Toyota Avanza',
                'harga_sewa' => 350000.00,
                'status_ketersediaan' => 'tersedia',
                'versi' => 0,
            ],
            [
                'id_kategori' => 2, 
                'plat_nomor' => 'D 5678 DEF',
                'merk_model' => 'Honda CR-V',
                'harga_sewa' => 600000.00,
                'status_ketersediaan' => 'tersedia',
                'versi' => 0,
            ]
        ]);
    }
}
