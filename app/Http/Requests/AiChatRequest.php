<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AiChatRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna diizinkan membuat request ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi input pesan dan riwayat percakapan chatbot.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required', 'string', 'in:user,model'],
            'history.*.text' => ['required', 'string', 'max:2000'],
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
            'message.required' => 'Pesan pertanyaan wajib diisi.',
            'message.max' => 'Pesan pertanyaan maksimal 1000 karakter.',
            'history.array' => 'Format riwayat percakapan tidak valid.',
            'history.max' => 'Riwayat percakapan maksimal 10 pesan terakhir.',
            'history.*.role.in' => 'Peran riwayat percakapan hanya boleh user atau model.',
            'history.*.text.max' => 'Panjang pesan riwayat melebihi batas maksimal.',
        ];
    }

    /**
     * Ambil riwayat percakapan yang sudah disanitasi (maksimal 8 pesan terakhir).
     *
     * @return array<int, array{role: string, text: string}>
     */
    public function sanitizedHistory(): array
    {
        $rawHistory = $this->validated('history', []);
        if (! is_array($rawHistory)) {
            return [];
        }

        $sanitized = [];
        foreach (array_slice($rawHistory, -8) as $turn) {
            $text = trim((string) ($turn['text'] ?? ''));
            $role = (string) ($turn['role'] ?? '');

            if ($text !== '' && in_array($role, ['user', 'model'], true)) {
                $sanitized[] = [
                    'role' => $role,
                    'text' => $text,
                ];
            }
        }

        return $sanitized;
    }
}
