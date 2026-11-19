<?php

namespace Tests\Feature;

use App\Models\KatalogMobil;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * Memastikan tamu belum login dapat mengirim pesan dan user_id tersimpan sebagai null.
     */
    public function test_tamu_dapat_mengirim_pesan_chat_dan_user_id_disimpan_null(): void
    {
        $response = $this->postJson('/ai-chat', [
            'message' => 'Halo MAZSEH',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'reply',
            'status',
            'whatsapp_url',
            'recommended_cars',
        ]);

        $this->assertDatabaseHas('sesi_chat_ai', [
            'user_id' => null,
            'pesan_pelanggan' => 'Halo MAZSEH',
            'status_sesi' => 'diselesaikan_ai',
        ]);
    }

    /**
     * Memastikan pengguna login mengirim pesan chat dan user_id tersimpan sesuai akunnya.
     */
    public function test_pengguna_login_mengirim_pesan_chat_dan_user_id_tersimpan(): void
    {
        $user = User::create([
            'name' => 'Chatbot User',
            'email' => 'chatbot_user@mazseh.test',
            'password' => Hash::make('Rahasia123'),
            'role' => 'pelanggan',
            'phone' => '081234567801',
        ]);

        $response = $this->actingAs($user)->postJson('/ai-chat', [
            'message' => 'Bagaimana syarat lepas kunci?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'diselesaikan_ai');

        $this->assertDatabaseHas('sesi_chat_ai', [
            'user_id' => $user->id,
            'pesan_pelanggan' => 'Bagaimana syarat lepas kunci?',
        ]);
    }

    /**
     * Memastikan fallback lokal mampu menjawab pertanyaan multi-mobil (Fortuner & Zenix) sekaligus.
     */
    public function test_fallback_lokal_menjawab_pertanyaan_multi_mobil_fortuner_dan_zenix(): void
    {
        $response = $this->postJson('/ai-chat', [
            'message' => 'Berapa tarif sewa Toyota Fortuner dan Innova Zenix?',
        ]);

        $response->assertStatus(200);
        $reply = (string) $response->json('reply');
        $this->assertStringContainsString('Fortuner', $reply);
        $this->assertStringContainsString('Zenix', $reply);
        $this->assertStringNotContainsStringIgnoringCase('klaten', $reply);

        $recommendedSlugs = collect($response->json('recommended_cars'))->pluck('slug')->all();
        $this->assertContains('fortuner', $recommendedSlugs);
        $this->assertContains('zenix', $recommendedSlugs);

        $recommendedUrls = collect($response->json('recommended_cars'))->pluck('url')->all();
        $this->assertContains(route('catalog.show', 'fortuner', false), $recommendedUrls);
        $this->assertContains(route('catalog.show', 'zenix', false), $recommendedUrls);
    }

    /**
     * Memastikan fallback lokal menginformasikan status maintenance pada Toyota Camry.
     */
    public function test_fallback_lokal_menginformasikan_status_maintenance_camry(): void
    {
        $response = $this->postJson('/ai-chat', [
            'message' => 'Apakah Toyota Camry tersedia?',
        ]);

        $response->assertStatus(200);
        $reply = strtolower((string) $response->json('reply'));
        $this->assertTrue(
            str_contains($reply, 'maintenance') || str_contains($reply, 'perawatan')
        );
    }

    /**
     * Memastikan fallback lokal menyaring mobil berdasarkan budget tanpa menyebut nama kota kecuali diminta.
     */
    public function test_fallback_lokal_filter_budget_dan_kota_mengembalikan_unit_tersedia(): void
    {
        // 1. Tanya budget tanpa menyebut kota -> tidak boleh menyebut "Klaten"
        $response = $this->postJson('/ai-chat', [
            'message' => 'Budget saya cuma 400 ribu, kira kira pake mobil apa ya?',
        ]);

        $response->assertStatus(200);
        $reply = (string) $response->json('reply');
        $this->assertStringContainsString('Agya', $reply);
        $this->assertStringContainsString('Avanza', $reply);
        $this->assertStringNotContainsStringIgnoringCase('klaten', $reply);

        $recommendedSlugs = collect($response->json('recommended_cars'))->pluck('slug')->all();
        $this->assertContains('agya', $recommendedSlugs);
        $this->assertContains('avanza', $recommendedSlugs);
        $this->assertNotContains('fortuner', $recommendedSlugs);

        // 2. Tanya lokasi secara eksplisit -> wajib menyebut "Klaten"
        $locationResponse = $this->postJson('/ai-chat', [
            'message' => 'Di kota mana lokasi pool rental MAZSEH berada?',
        ]);

        $locationResponse->assertStatus(200);
        $this->assertStringContainsStringIgnoringCase('klaten', (string) $locationResponse->json('reply'));
    }

    /**
     * Memastikan eskalasi WhatsApp mengembalikan status dialihkan_ke_wa dan nomor resmi 6285785180423.
     */
    public function test_eskalasi_whatsapp_mengembalikan_status_dialihkan_dan_nomor_resmi(): void
    {
        $response = $this->postJson('/ai-chat', [
            'message' => 'Hubungkan saya dengan admin via WhatsApp',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'dialihkan_ke_wa');
        $this->assertStringStartsWith(
            'https://wa.me/6285785180423?text=',
            (string) $response->json('whatsapp_url')
        );
    }

    /**
     * Memastikan integrasi Gemini Interactions API mengirim konteks armada & multi-turn history serta memparsing recommended_cars.
     */
    public function test_integrasi_gemini_api_sukses_dengan_konteks_dan_multi_turn_history(): void
    {
        config(['services.gemini.api_key' => 'fake-gemini-key']);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'id' => 'int_test_123',
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => json_encode([
                                    'reply' => 'Untuk perjalanan keluarga, Toyota Innova Zenix sangat nyaman dengan tarif Rp650.000/hari.',
                                    'recommended_slugs' => ['zenix', 'slug-fiktif-tidak-ada'],
                                    'needs_whatsapp' => false,
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/ai-chat', [
            'message' => 'Kalau yang paling nyaman untuk keluarga?',
            'history' => [
                ['role' => 'user', 'text' => 'Halo, saya mau sewa mobil.'],
                ['role' => 'model', 'text' => 'Halo! Ada rencana perjalanan ke mana?'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'diselesaikan_ai');
        $response->assertJsonPath('reply', 'Untuk perjalanan keluarga, Toyota Innova Zenix sangat nyaman dengan tarif Rp650.000/hari.');

        $recommended = $response->json('recommended_cars');
        $this->assertCount(1, $recommended);
        $this->assertSame('zenix', $recommended[0]['slug']);
        $this->assertSame(route('catalog.show', 'zenix', false), $recommended[0]['url']);

        Http::assertSent(function (HttpClientRequest $request): bool {
            $body = $request->data();
            $input = $body['input'] ?? [];
            $lastStep = $input !== [] ? $input[count($input) - 1] : null;

            return $request->hasHeader('x-goog-api-key', 'fake-gemini-key')
                && $request->hasHeader('Api-Revision', '2026-05-20')
                && ($body['model'] ?? null) === 'gemini-3.5-flash-lite'
                && ($body['store'] ?? null) === false
                && str_contains((string) ($body['system_instruction'] ?? ''), 'Toyota Innova Zenix')
                && count($input) === 3
                && ($lastStep['type'] ?? null) === 'user_input';
        });
    }

    /**
     * Memastikan sistem otomatis fallback ke mesin lokal ketika Gemini API mengembalikan error HTTP.
     */
    public function test_otomatis_fallback_ke_mesin_lokal_ketika_gemini_api_error(): void
    {
        config(['services.gemini.api_key' => 'fake-gemini-key']);

        Http::fake([
            '*' => Http::response(['error' => 'Service Unavailable'], 500),
        ]);

        $response = $this->postJson('/ai-chat', [
            'message' => 'Apakah Toyota Fortuner tersedia?',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('Fortuner', (string) $response->json('reply'));
        $recommendedSlugs = collect($response->json('recommended_cars'))->pluck('slug')->all();
        $this->assertContains('fortuner', $recommendedSlugs);
    }

    /**
     * Memastikan validasi input menolak pesan kosong dan format history yang tidak valid.
     */
    public function test_validasi_menolak_input_tidak_valid_pada_ai_chat(): void
    {
        $this->postJson('/ai-chat', [
            'message' => '',
            'history' => [
                ['role' => 'system', 'text' => 'Inject prompt'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['message', 'history.0.role']);
    }

    /**
     * Memastikan rate limiting (15 pesan/menit per IP) memblokir spam pada /ai-chat.
     */
    public function test_rate_limiting_memblokir_spam_pada_ai_chat(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->postJson('/ai-chat', [
                'message' => "Pertanyaan ke-{$i}",
            ])->assertStatus(200);
        }

        $this->postJson('/ai-chat', [
            'message' => 'Pertanyaan ke-16 harus diblokir',
        ])->assertStatus(429);
    }

    /**
     * Memastikan obrolan di luar topik rental mobil dan customer service ditolak dengan pesan standar.
     */
    public function test_obrolan_di_luar_konteks_rental_ditolak_dengan_pesan_standar(): void
    {
        $response = $this->postJson('/ai-chat', [
            'message' => 'bantu saya mengerjakan soal, ini soalnya 1+2+3+4+6+90+9-49+666',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath(
            'reply',
            'Maaf, sebagai asisten MAZSEH saya hanya dapat membantu Anda seputar informasi rental mobil kami.'
        );
        $response->assertJsonPath('status', 'diselesaikan_ai');
        $response->assertJsonPath('recommended_cars', []);
        $response->assertJsonPath('whatsapp_url', null);

        $mathResponse = $this->postJson('/ai-chat', [
            'message' => 'hitung 150000 + 250000',
        ]);

        $mathResponse->assertStatus(200);
        $mathResponse->assertJsonPath(
            'reply',
            'Maaf, sebagai asisten MAZSEH saya hanya dapat membantu Anda seputar informasi rental mobil kami.'
        );
    }

    /**
     * Memastikan Gemini Interactions API tool calling mendeteksi bentrok jadwal sewa pada tabel transaksi.
     */
    public function test_gemini_tool_calling_mendeteksi_bentrok_jadwal_sewa_per_tanggal(): void
    {
        config(['services.gemini.api_key' => 'fake-gemini-key']);

        $pelanggan = User::where('role', 'pelanggan')->firstOrFail();
        $fortuner = KatalogMobil::where('slug', 'fortuner')->firstOrFail();

        Transaksi::create([
            'user_id' => $pelanggan->id,
            'id_mobil' => $fortuner->id_mobil,
            'kode_booking' => 'MZS-TEST-CONFLICT',
            'tanggal_mulai' => '2026-12-10',
            'tanggal_selesai' => '2026-12-12',
            'waktu_ambil' => '09:00',
            'metode_pengambilan' => 'Ambil di pool MAZSEH',
            'opsi_pengemudi' => 'Lepas kunci',
            'total_harga' => 1700000.00,
            'status_pesanan' => 'dikonfirmasi',
            'titik_jemput' => 'Pool MAZSEH Klaten',
            'waktu_pesan' => now(),
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/interactions' => Http::sequence()
                ->push([
                    'id' => 'int_turn_1',
                    'steps' => [
                        [
                            'type' => 'thought',
                            'signature' => 'sig_test_1',
                            'summary' => 'Mengecek jadwal Fortuner untuk 2026-12-10 sampai 2026-12-12.',
                        ],
                        [
                            'type' => 'function_call',
                            'id' => 'call_avail_1',
                            'name' => 'check_car_availability',
                            'arguments' => [
                                'car_slug' => 'fortuner',
                                'start_date' => '2026-12-10',
                                'end_date' => '2026-12-12',
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'id' => 'int_turn_2',
                    'steps' => [
                        [
                            'type' => 'model_output',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => json_encode([
                                        'reply' => 'Mohon maaf, Toyota Fortuner sudah terpesan pada tanggal 2026-12-10 hingga 2026-12-12. Sebagai alternatif, Toyota Innova Zenix tersedia pada tanggal tersebut.',
                                        'recommended_slugs' => ['zenix'],
                                        'needs_whatsapp' => false,
                                    ], JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $response = $this->postJson('/ai-chat', [
            'message' => 'Apakah Toyota Fortuner tersedia untuk tanggal 2026-12-10 sampai 2026-12-12?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'diselesaikan_ai');
        $response->assertJsonPath(
            'reply',
            'Mohon maaf, Toyota Fortuner sudah terpesan pada tanggal 2026-12-10 hingga 2026-12-12. Sebagai alternatif, Toyota Innova Zenix tersedia pada tanggal tersebut.'
        );

        $recommendedSlugs = collect($response->json('recommended_cars'))->pluck('slug')->all();
        $this->assertSame(['zenix'], $recommendedSlugs);

        Http::assertSentCount(2);

        $recorded = Http::recorded();
        $firstBody = $recorded[0][0]->data();
        $secondBody = $recorded[1][0]->data();

        $this->assertSame('check_car_availability', $firstBody['tools'][0]['name'] ?? null);
        $this->assertStringContainsString(now()->format('Y-m-d'), (string) ($firstBody['system_instruction'] ?? ''));

        $secondInput = $secondBody['input'] ?? [];
        $thoughtStep = collect($secondInput)->firstWhere('type', 'thought');
        $functionCallStep = collect($secondInput)->firstWhere('type', 'function_call');
        $functionResultStep = collect($secondInput)->firstWhere('type', 'function_result');

        $this->assertSame('sig_test_1', $thoughtStep['signature'] ?? null);
        $this->assertSame('call_avail_1', $functionCallStep['id'] ?? null);
        $this->assertSame('call_avail_1', $functionResultStep['call_id'] ?? null);
        $this->assertSame('check_car_availability', $functionResultStep['name'] ?? null);

        $toolResultText = (string) ($functionResultStep['result'][0]['text'] ?? '');
        $this->assertStringNotContainsString('MZS-TEST-CONFLICT', $toolResultText);
        $this->assertStringNotContainsString($pelanggan->name, $toolResultText);

        $toolPayload = json_decode($toolResultText, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('2026-12-10', $toolPayload['checked_start_date']);
        $this->assertSame('2026-12-12', $toolPayload['checked_end_date']);
        $this->assertSame('fortuner', $toolPayload['results'][0]['slug']);
        $this->assertFalse($toolPayload['results'][0]['available']);
        $this->assertSame('bentrok_jadwal', $toolPayload['results'][0]['reason']);
        $this->assertSame('2026-12-10', $toolPayload['results'][0]['conflicting_bookings'][0]['tanggal_mulai']);
        $this->assertSame('2026-12-12', $toolPayload['results'][0]['conflicting_bookings'][0]['tanggal_selesai']);
        $this->assertContains('zenix', collect($toolPayload['alternative_available_cars'])->pluck('slug')->all());
    }

    /**
     * Memastikan tool calling menormalisasi end_date minimal H+1 untuk query 1 tanggal serta mengabaikan booking yang dibatalkan.
     */
    public function test_gemini_tool_calling_menormalisasi_satu_tanggal_dan_mengabaikan_transaksi_dibatalkan(): void
    {
        config(['services.gemini.api_key' => 'fake-gemini-key']);

        $pelanggan = User::where('role', 'pelanggan')->firstOrFail();
        $fortuner = KatalogMobil::where('slug', 'fortuner')->firstOrFail();

        Transaksi::create([
            'user_id' => $pelanggan->id,
            'id_mobil' => $fortuner->id_mobil,
            'kode_booking' => 'MZS-TEST-SINGLE-DAY',
            'tanggal_mulai' => '2026-12-15',
            'tanggal_selesai' => '2026-12-17',
            'waktu_ambil' => '09:00',
            'metode_pengambilan' => 'Ambil di pool MAZSEH',
            'opsi_pengemudi' => 'Lepas kunci',
            'total_harga' => 1700000.00,
            'status_pesanan' => 'menunggu_pembayaran',
            'titik_jemput' => 'Pool MAZSEH Klaten',
            'waktu_pesan' => now(),
        ]);

        Transaksi::create([
            'user_id' => $pelanggan->id,
            'id_mobil' => $fortuner->id_mobil,
            'kode_booking' => 'MZS-TEST-CANCELLED',
            'tanggal_mulai' => '2026-12-25',
            'tanggal_selesai' => '2026-12-27',
            'waktu_ambil' => '09:00',
            'metode_pengambilan' => 'Ambil di pool MAZSEH',
            'opsi_pengemudi' => 'Lepas kunci',
            'total_harga' => 1700000.00,
            'status_pesanan' => 'dibatalkan',
            'titik_jemput' => 'Pool MAZSEH Klaten',
            'waktu_pesan' => now(),
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/interactions' => Http::sequence()
                ->push([
                    'id' => 'int_single_1',
                    'steps' => [
                        [
                            'type' => 'function_call',
                            'id' => 'call_single_1',
                            'name' => 'check_car_availability',
                            'arguments' => [
                                'car_slug' => 'fortuner',
                                'start_date' => '2026-12-15',
                            ],
                        ],
                        [
                            'type' => 'function_call',
                            'id' => 'call_cancelled_2',
                            'name' => 'check_car_availability',
                            'arguments' => [
                                'car_slug' => 'fortuner',
                                'start_date' => '2026-12-25',
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'id' => 'int_single_2',
                    'steps' => [
                        [
                            'type' => 'model_output',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => json_encode([
                                        'reply' => 'Toyota Fortuner tidak tersedia pada 2026-12-15, namun tersedia pada 2026-12-25.',
                                        'recommended_slugs' => ['fortuner'],
                                        'needs_whatsapp' => false,
                                    ], JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $response = $this->postJson('/ai-chat', [
            'message' => 'Cek Fortuner tanggal 2026-12-15 dan 2026-12-25',
        ]);

        $response->assertStatus(200);
        Http::assertSentCount(2);

        $secondInput = Http::recorded()[1][0]->data()['input'] ?? [];
        $resultSteps = collect($secondInput)->where('type', 'function_result')->values();
        $this->assertCount(2, $resultSteps);

        $singlePayload = json_decode((string) $resultSteps[0]['result'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('2026-12-15', $singlePayload['checked_start_date']);
        $this->assertSame('2026-12-16', $singlePayload['checked_end_date']);
        $this->assertFalse($singlePayload['results'][0]['available']);
        $this->assertSame('bentrok_jadwal', $singlePayload['results'][0]['reason']);

        $cancelledPayload = json_decode((string) $resultSteps[1]['result'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('2026-12-25', $cancelledPayload['checked_start_date']);
        $this->assertSame('2026-12-26', $cancelledPayload['checked_end_date']);
        $this->assertTrue($cancelledPayload['results'][0]['available']);
        $this->assertSame('tersedia', $cancelledPayload['results'][0]['reason']);
    }

    /**
     * Memastikan fallback lokal mendeteksi bentrok jadwal sewa dan ketersediaan pada tanggal spesifik.
     */
    public function test_fallback_lokal_mendeteksi_bentrok_jadwal_sewa_pada_tanggal_spesifik(): void
    {
        config(['services.gemini.api_key' => '']);

        $pelanggan = User::where('role', 'pelanggan')->firstOrFail();
        $fortuner = KatalogMobil::where('slug', 'fortuner')->firstOrFail();

        Transaksi::create([
            'user_id' => $pelanggan->id,
            'id_mobil' => $fortuner->id_mobil,
            'kode_booking' => 'MZS-FALLBACK-BOOK',
            'tanggal_mulai' => '2026-12-20',
            'tanggal_selesai' => '2026-12-22',
            'waktu_ambil' => '09:00',
            'metode_pengambilan' => 'Ambil di pool MAZSEH',
            'opsi_pengemudi' => 'Lepas kunci',
            'total_harga' => 1700000.00,
            'status_pesanan' => 'menunggu_pembayaran',
            'titik_jemput' => 'Pool MAZSEH Klaten',
            'waktu_pesan' => now(),
        ]);

        // 1. Tanggal bentrok -> harus menginformasikan sudah terpesan & menyarankan alternatif
        $conflictResponse = $this->postJson('/ai-chat', [
            'message' => 'Apakah Fortuner tersedia tanggal 2026-12-20?',
        ]);

        $conflictResponse->assertStatus(200);
        $conflictReply = strtolower((string) $conflictResponse->json('reply'));
        $this->assertStringContainsString('sudah terpesan', $conflictReply);
        $this->assertStringContainsString('2026-12-20', $conflictReply);

        $conflictRecommendedSlugs = collect($conflictResponse->json('recommended_cars'))->pluck('slug')->all();
        $this->assertNotContains('fortuner', $conflictRecommendedSlugs);
        $this->assertNotEmpty($conflictRecommendedSlugs);

        // 2. Tanggal kosong -> harus menginformasikan tersedia
        $availableResponse = $this->postJson('/ai-chat', [
            'message' => 'Apakah Fortuner tersedia tanggal 2026-12-28?',
        ]);

        $availableResponse->assertStatus(200);
        $availableReply = strtolower((string) $availableResponse->json('reply'));
        $this->assertStringContainsString('tersedia untuk disewa', $availableReply);
        $this->assertStringContainsString('2026-12-28', $availableReply);

        $availableRecommendedSlugs = collect($availableResponse->json('recommended_cars'))->pluck('slug')->all();
        $this->assertContains('fortuner', $availableRecommendedSlugs);
    }
}
