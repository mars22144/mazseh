<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPembayaranRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna diizinkan memverifikasi pembayaran.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['kasir', 'admin'], true);
    }

    /**
     * Aturan validasi verifikasi pembayaran oleh kasir.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keputusan' => ['required', 'in:terima,tolak'],
            'catatan' => ['nullable', 'string', 'max:500'],
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
            'keputusan.required' => 'Keputusan verifikasi wajib dipilih.',
            'keputusan.in' => 'Keputusan verifikasi harus berupa terima atau tolak.',
            'catatan.string' => 'Catatan verifikasi harus berupa teks.',
            'catatan.max' => 'Catatan verifikasi maksimal 500 karakter.',
        ];
    }
}
