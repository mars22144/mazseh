<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyDokumenRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna diizinkan memverifikasi dokumen pelanggan.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['kasir', 'admin'], true);
    }

    /**
     * Aturan validasi verifikasi dokumen KTP & SIM pelanggan.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status_verifikasi' => ['required', 'in:disetujui,ditolak'],
            'catatan_verifikasi' => ['nullable', 'required_if:status_verifikasi,ditolak', 'string', 'max:500'],
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
            'status_verifikasi.required' => 'Status verifikasi dokumen wajib dipilih.',
            'status_verifikasi.in' => 'Status verifikasi harus berupa disetujui atau ditolak.',
            'catatan_verifikasi.required_if' => 'Catatan verifikasi wajib diisi apabila dokumen ditolak.',
            'catatan_verifikasi.string' => 'Catatan verifikasi harus berupa teks.',
            'catatan_verifikasi.max' => 'Catatan verifikasi maksimal 500 karakter.',
        ];
    }
}
