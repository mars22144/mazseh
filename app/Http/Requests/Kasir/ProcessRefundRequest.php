<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProcessRefundRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna diizinkan memproses refund.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['kasir', 'admin'], true);
    }

    /**
     * Aturan validasi pemrosesan refund oleh kasir.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'jumlah_refund' => ['required', 'numeric', 'min:1'],
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
            'jumlah_refund.required' => 'Nominal refund wajib diisi.',
            'jumlah_refund.numeric' => 'Nominal refund harus berupa angka.',
            'jumlah_refund.min' => 'Nominal refund minimal Rp1.',
            'catatan.string' => 'Catatan refund harus berupa teks.',
            'catatan.max' => 'Catatan refund maksimal 500 karakter.',
        ];
    }
}
