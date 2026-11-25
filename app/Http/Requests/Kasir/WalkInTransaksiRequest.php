<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WalkInTransaksiRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna diizinkan membuat transaksi walk-in.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['kasir', 'admin'], true);
    }

    /**
     * Aturan validasi pembuatan transaksi walk-in di loket kasir.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'exists:users,id'],
            'customer_name' => ['required_without:user_id', 'nullable', 'string', 'max:100'],
            'customer_email' => ['required_without:user_id', 'nullable', 'email', 'max:255'],
            'customer_phone' => ['required_without:user_id', 'nullable', 'string', 'max:30'],
            'sim_number' => ['required', 'string', 'max:50'],
            'id_mobil' => ['required', 'exists:katalog_mobil,id_mobil'],
            'start' => ['required', 'date', 'after_or_equal:today'],
            'end' => ['required', 'date', 'after:start'],
            'waktu_ambil' => ['required', 'string', 'max:10'],
            'opsi_pengemudi' => ['required', 'in:Lepas kunci,Dengan sopir'],
            'metode_pengambilan' => ['required', 'string', 'max:50'],
            'titik_jemput' => ['required', 'string', 'max:500'],
            'metode_pembayaran' => ['required', 'in:Tunai di kasir,QRIS,Transfer bank'],
            'referensi_pembayaran' => ['nullable', 'string', 'max:100'],
            'langsung_lunas' => ['nullable', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'versi' => ['nullable', 'integer'],
            'file_ktp' => ['nullable', 'image', 'max:3072'],
            'file_sim' => ['nullable', 'image', 'max:3072'],
            'verifikasi_dokumen' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Pesan error validasi dalam Bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'Pelanggan yang dipilih tidak ditemukan.',
            'customer_name.required_without' => 'Nama pelanggan wajib diisi jika tidak memilih pelanggan terdaftar.',
            'customer_name.max' => 'Nama pelanggan maksimal 100 karakter.',
            'customer_email.required_without' => 'Email pelanggan wajib diisi jika tidak memilih pelanggan terdaftar.',
            'customer_email.email' => 'Format alamat email pelanggan tidak valid.',
            'customer_email.max' => 'Email pelanggan maksimal 255 karakter.',
            'customer_phone.required_without' => 'Nomor telepon pelanggan wajib diisi jika tidak memilih pelanggan terdaftar.',
            'customer_phone.max' => 'Nomor telepon pelanggan maksimal 30 karakter.',
            'sim_number.required' => 'Nomor SIM pelanggan wajib diisi.',
            'sim_number.max' => 'Nomor SIM maksimal 50 karakter.',
            'id_mobil.required' => 'Unit armada wajib dipilih.',
            'id_mobil.exists' => 'Unit armada yang dipilih tidak ditemukan.',
            'start.required' => 'Tanggal mulai sewa wajib diisi.',
            'start.date' => 'Format tanggal mulai sewa tidak valid.',
            'start.after_or_equal' => 'Tanggal mulai sewa minimal hari ini.',
            'end.required' => 'Tanggal selesai sewa wajib diisi.',
            'end.date' => 'Format tanggal selesai sewa tidak valid.',
            'end.after' => 'Tanggal selesai sewa harus setelah tanggal mulai.',
            'waktu_ambil.required' => 'Waktu pengambilan unit wajib diisi.',
            'waktu_ambil.max' => 'Waktu pengambilan maksimal 10 karakter.',
            'opsi_pengemudi.required' => 'Opsi pengemudi wajib dipilih.',
            'opsi_pengemudi.in' => 'Opsi pengemudi harus Lepas kunci atau Dengan sopir.',
            'metode_pengambilan.required' => 'Metode pengambilan unit wajib diisi.',
            'metode_pengambilan.max' => 'Metode pengambilan maksimal 50 karakter.',
            'titik_jemput.required' => 'Alamat atau titik jemput wajib diisi.',
            'titik_jemput.max' => 'Titik jemput maksimal 500 karakter.',
            'metode_pembayaran.required' => 'Metode pembayaran wajib dipilih.',
            'metode_pembayaran.in' => 'Metode pembayaran harus Tunai di kasir, QRIS, atau Transfer bank.',
            'referensi_pembayaran.max' => 'Referensi pembayaran maksimal 100 karakter.',
            'langsung_lunas.boolean' => 'Status langsung lunas tidak valid.',
            'catatan.max' => 'Catatan maksimal 500 karakter.',
            'versi.integer' => 'Versi data armada harus berupa angka bulat.',
            'file_ktp.image' => 'Foto KTP harus berupa file gambar (JPG, PNG, atau WebP).',
            'file_ktp.max' => 'Ukuran foto KTP maksimal 3MB.',
            'file_sim.image' => 'Foto SIM harus berupa file gambar (JPG, PNG, atau WebP).',
            'file_sim.max' => 'Ukuran foto SIM maksimal 3MB.',
            'verifikasi_dokumen.boolean' => 'Status verifikasi dokumen tidak valid.',
        ];
    }
}
