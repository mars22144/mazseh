<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiChatRequest;
use App\Models\SesiChatAi;
use App\Services\AiChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AiChatController extends Controller
{
    public function __construct(public AiChatbotService $chatbotService) {}

    /**
     * Tangani pengiriman pesan ke Asisten AI MAZSEH dan simpan riwayat ke database.
     */
    public function sendMessage(AiChatRequest $request): JsonResponse
    {
        $message = trim((string) $request->validated('message'));
        $history = $request->sanitizedHistory();

        $result = $this->chatbotService->respond($message, $history);

        $statusSesi = $result['needs_whatsapp'] ? 'dialihkan_ke_wa' : 'diselesaikan_ai';
        $whatsappUrl = null;

        if ($statusSesi === 'dialihkan_ke_wa') {
            $adminNumber = preg_replace('/[^0-9]/', '', (string) config('services.whatsapp.admin_number', '6285785180423'));
            $encodedText = urlencode("Halo Admin MAZSEH, saya ingin konsultasi langsung mengenai pertanyaan ini: \"{$message}\"");
            $whatsappUrl = "https://wa.me/{$adminNumber}?text={$encodedText}";
        }

        SesiChatAi::create([
            'user_id' => Auth::id(),
            'pesan_pelanggan' => $message,
            'balasan_ai' => $result['reply'],
            'status_sesi' => $statusSesi,
            'waktu_sesi' => now(),
        ]);

        return response()->json([
            'reply' => $result['reply'],
            'status' => $statusSesi,
            'whatsapp_url' => $whatsappUrl,
            'recommended_cars' => $result['recommended_cars'],
        ]);
    }
}
