<?php

namespace App\Services;

use App\Models\KatalogMobil;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiChatbotService
{
    protected const MAX_TOOL_ITERATIONS = 3;

    /**
     * Menghasilkan balasan asisten AI (Gemini API dengan auto-fallback mesin lokal).
     *
     * @param  array<int, array{role: string, text: string}>  $history
     * @return array{
     *     reply: string,
     *     needs_whatsapp: bool,
     *     recommended_cars: array<int, array{slug: string, name: string, price: string, status: string, url: string}>,
     *     source: string
     * }
     */
    public function respond(string $message, array $history = []): array
    {
        $cars = KatalogMobil::with('kategori')
            ->orderBy('harga_sewa')
            ->get();

        $aiResult = $this->askGemini($message, $history, $cars);

        if ($aiResult !== null) {
            $recommendedCars = $this->resolveRecommendedCars(
                $aiResult['recommended_slugs'],
                $aiResult['reply'],
                $cars,
                true
            );

            return [
                'reply' => $aiResult['reply'],
                'needs_whatsapp' => $aiResult['needs_whatsapp'],
                'recommended_cars' => $recommendedCars,
                'source' => 'gemini',
            ];
        }

        $fallback = $this->generateLocalFallback($message, $history, $cars);
        $recommendedCars = $this->resolveRecommendedCars(
            $fallback['recommended_slugs'],
            $fallback['reply'],
            $cars,
            false
        );

        return [
            'reply' => $fallback['reply'],
            'needs_whatsapp' => $fallback['needs_whatsapp'],
            'recommended_cars' => $recommendedCars,
            'source' => 'fallback',
        ];
    }

    /**
     * Memanggil Gemini Interactions API secara stateless dengan konteks katalog MAZSEH.
     *
     * @param  array<int, array{role: string, text: string}>  $history
     * @param  Collection<int, KatalogMobil>  $cars
     * @return array{reply: string, recommended_slugs: array<int, string>, needs_whatsapp: bool}|null
     */
    protected function askGemini(string $message, array $history, Collection $cars): ?array
    {
        $apiKey = trim((string) config('services.gemini.api_key'));
        if ($apiKey === '') {
            return null;
        }

        $inputSteps = [];
        foreach ($history as $turn) {
            $stepType = $turn['role'] === 'model' ? 'model_output' : 'user_input';
            $inputSteps[] = [
                'type' => $stepType,
                'content' => [
                    ['type' => 'text', 'text' => $turn['text']],
                ],
            ];
        }

        // Pastikan turn terakhir selalu user_input sesuai aturan Interactions API
        $inputSteps[] = [
            'type' => 'user_input',
            'content' => [
                ['type' => 'text', 'text' => $message],
            ],
        ];

        $payload = [
            'model' => (string) config('services.gemini.model', 'gemini-3.5-flash-lite'),
            'system_instruction' => $this->buildSystemInstruction($cars),
            'input' => $inputSteps,
            'tools' => $this->getToolsDeclaration(),
            'store' => false,
            'response_format' => [
                'type' => 'text',
                'mime_type' => 'application/json',
                'schema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'reply' => [
                            'type' => 'STRING',
                            'description' => 'Balasan ramah dan ringkas dalam Bahasa Indonesia.',
                        ],
                        'recommended_slugs' => [
                            'type' => 'ARRAY',
                            'description' => 'Daftar slug mobil dari katalog yang direkomendasikan atau dibahas pada balasan.',
                            'items' => ['type' => 'STRING'],
                        ],
                        'needs_whatsapp' => [
                            'type' => 'BOOLEAN',
                            'description' => 'True jika pengguna meminta bicara dengan admin/CS/WhatsApp atau membutuhkan bantuan staf manusia.',
                        ],
                    ],
                    'required' => ['reply', 'recommended_slugs', 'needs_whatsapp'],
                ],
            ],
        ];

        try {
            for ($iteration = 0; $iteration < self::MAX_TOOL_ITERATIONS; $iteration++) {
                $payload['input'] = $inputSteps;

                $response = Http::timeout((int) config('services.gemini.timeout', 12))
                    ->withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'Api-Revision' => (string) config('services.gemini.api_revision', '2026-05-20'),
                    ])
                    ->acceptJson()
                    ->post((string) config('services.gemini.endpoint'), $payload);

                if (! $response->successful()) {
                    Log::warning('Gemini API mengembalikan status gagal', [
                        'status' => $response->status(),
                    ]);

                    return null;
                }

                $steps = $response->json('steps', []);
                if (! is_array($steps)) {
                    return null;
                }

                $functionCalls = [];
                foreach ($steps as $step) {
                    if (is_array($step) && ($step['type'] ?? null) === 'function_call') {
                        $functionCalls[] = $step;
                    }
                }

                if ($functionCalls !== []) {
                    // Dalam mode stateless (store: false), teruskan step model (thought & function_call) verbatim
                    foreach ($steps as $step) {
                        if (is_array($step) && in_array($step['type'] ?? null, ['thought', 'function_call'], true)) {
                            $inputSteps[] = $step;
                        }
                    }

                    foreach ($functionCalls as $call) {
                        $callId = (string) ($call['id'] ?? '');
                        $funcName = (string) ($call['name'] ?? '');
                        $args = is_array($call['arguments'] ?? null) ? $call['arguments'] : [];

                        $toolOutput = $funcName === 'check_car_availability'
                            ? $this->executeCheckCarAvailability($args, $cars)
                            : ['error' => "Tool {$funcName} tidak dikenali."];

                        $inputSteps[] = [
                            'type' => 'function_result',
                            'name' => $funcName,
                            'call_id' => $callId,
                            'result' => [
                                [
                                    'type' => 'text',
                                    'text' => (string) json_encode($toolOutput, JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ];
                    }

                    continue;
                }

                $rawText = null;
                foreach (array_reverse($steps) as $step) {
                    if (($step['type'] ?? null) === 'model_output' && is_array($step['content'] ?? null)) {
                        foreach ($step['content'] as $contentItem) {
                            if (($contentItem['type'] ?? null) === 'text' && is_string($contentItem['text'] ?? null)) {
                                $rawText = $contentItem['text'];
                                break 2;
                            }
                        }
                    }
                }

                if ($rawText === null || trim($rawText) === '') {
                    return null;
                }

                $decoded = json_decode($rawText, true);
                if (! is_array($decoded) || ! is_string($decoded['reply'] ?? null) || trim($decoded['reply']) === '') {
                    return null;
                }

                $slugs = is_array($decoded['recommended_slugs'] ?? null)
                    ? array_values(array_filter($decoded['recommended_slugs'], 'is_string'))
                    : [];

                return [
                    'reply' => trim($decoded['reply']),
                    'recommended_slugs' => $slugs,
                    'needs_whatsapp' => (bool) ($decoded['needs_whatsapp'] ?? false),
                ];
            }

            Log::warning('Gemini API melebihi batas maksimum iterasi tool calling');

            return null;
        } catch (Throwable $exception) {
            Log::warning('Gagal menghubungi Gemini API, beralih ke fallback lokal', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Mendefinisikan daftar tool (Function Calling) untuk Gemini Interactions API.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getToolsDeclaration(): array
    {
        return [
            [
                'type' => 'function',
                'name' => 'check_car_availability',
                'description' => 'Mengecek ketersediaan jadwal sewa mobil MAZSEH pada rentang tanggal tertentu berdasarkan database transaksi real-time. Panggil tool ini setiap kali pelanggan menanyakan ketersediaan mobil pada tanggal atau hari tertentu (misal: hari ini, besok, lusa, atau tanggal spesifik).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Tanggal mulai sewa dalam format YYYY-MM-DD.',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'Tanggal selesai sewa dalam format YYYY-MM-DD. Jika pelanggan hanya menyebut 1 tanggal, isi dengan H+1 dari start_date.',
                        ],
                        'car_slug' => [
                            'type' => 'string',
                            'description' => 'Opsional. Slug mobil spesifik yang ingin dicek (contoh: avanza, zenix, fortuner, agya, alphard, camry). Kosongkan jika ingin mengecek semua mobil pada tanggal tersebut.',
                        ],
                    ],
                    'required' => ['start_date'],
                ],
            ],
        ];
    }

    /**
     * Mengeksekusi pengecekan ketersediaan jadwal mobil berdasarkan rentang tanggal di database transaksi.
     *
     * @param  array<string, mixed>  $arguments
     * @param  Collection<int, KatalogMobil>  $cars
     * @return array<string, mixed>
     */
    protected function executeCheckCarAvailability(array $arguments, Collection $cars): array
    {
        $rawStart = trim((string) ($arguments['start_date'] ?? ''));
        $rawEnd = trim((string) ($arguments['end_date'] ?? ''));
        $carSlug = strtolower(trim((string) ($arguments['car_slug'] ?? '')));

        if ($rawStart === '') {
            return [
                'error' => 'Parameter start_date wajib diisi dalam format YYYY-MM-DD.',
            ];
        }

        try {
            $startDate = Carbon::parse($rawStart)->startOfDay();
        } catch (Throwable) {
            return [
                'error' => 'Format start_date tidak valid. Gunakan format YYYY-MM-DD.',
            ];
        }

        if ($rawEnd !== '') {
            try {
                $endDate = Carbon::parse($rawEnd)->startOfDay();
            } catch (Throwable) {
                $endDate = $startDate->copy()->addDay();
            }
        } else {
            $endDate = $startDate->copy()->addDay();
        }

        if ($endDate->lessThanOrEqualTo($startDate)) {
            $endDate = $startDate->copy()->addDay();
        }

        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');

        $targetCars = $carSlug !== ''
            ? $cars->filter(fn (KatalogMobil $car): bool => strtolower($car->slug) === $carSlug)->values()
            : $cars->values();

        if ($targetCars->isEmpty()) {
            $targetCars = $cars->values();
        }

        $results = [];
        foreach ($targetCars as $car) {
            if ($car->status_ketersediaan !== 'tersedia') {
                $results[] = [
                    'slug' => $car->slug,
                    'name' => $car->merk_model,
                    'price_per_day' => $car->formatted_price,
                    'available' => false,
                    'reason' => 'sedang_maintenance',
                    'conflicting_bookings' => [],
                ];

                continue;
            }

            $isAvailable = $car->isAvailableForDates($startStr, $endStr);
            $conflicts = [];

            if (! $isAvailable) {
                $conflicts = $car->transaksi()
                    ->whereNotIn('status_pesanan', ['dibatalkan'])
                    ->where('tanggal_mulai', '<', $endStr)
                    ->where('tanggal_selesai', '>', $startStr)
                    ->orderBy('tanggal_mulai')
                    ->get(['tanggal_mulai', 'tanggal_selesai'])
                    ->map(static fn ($trx): array => [
                        'tanggal_mulai' => Carbon::parse($trx->tanggal_mulai)->format('Y-m-d'),
                        'tanggal_selesai' => Carbon::parse($trx->tanggal_selesai)->format('Y-m-d'),
                    ])
                    ->all();
            }

            $results[] = [
                'slug' => $car->slug,
                'name' => $car->merk_model,
                'price_per_day' => $car->formatted_price,
                'available' => $isAvailable,
                'reason' => $isAvailable ? 'tersedia' : 'bentrok_jadwal',
                'conflicting_bookings' => $conflicts,
            ];
        }

        $alternativeAvailable = [];
        if ($carSlug !== '') {
            $alternativeAvailable = $cars
                ->filter(fn (KatalogMobil $car): bool => strtolower($car->slug) !== $carSlug && $car->isAvailableForDates($startStr, $endStr))
                ->map(static fn (KatalogMobil $car): array => [
                    'slug' => $car->slug,
                    'name' => $car->merk_model,
                    'price_per_day' => $car->formatted_price,
                ])
                ->values()
                ->all();
        }

        return [
            'checked_start_date' => $startStr,
            'checked_end_date' => $endStr,
            'results' => $results,
            'alternative_available_cars' => $alternativeAvailable,
        ];
    }

    /**
     * Menyusun System Instruction berisi fakta armada MAZSEH real-time dan aturan layanan.
     *
     * @param  Collection<int, KatalogMobil>  $cars
     */
    protected function buildSystemInstruction(Collection $cars): string
    {
        $catalogLines = [];
        foreach ($cars as $car) {
            $category = $car->kategori?->nama_kategori ?? 'Umum';
            $features = is_array($car->fitur) ? implode(', ', $car->fitur) : '-';
            $statusNote = $car->status_ketersediaan === 'tersedia'
                ? 'TERSEDIA'
                : 'SEDANG MAINTENANCE (tidak dapat disewa sementara)';

            $catalogLines[] = sprintf(
                '- slug: "%s" | Unit: %s (%d) | Kategori: %s | Transmisi: %s | Kursi: %d | BBM: %s | Kota: %s | Tarif: %s/hari | Status: %s | Fitur: %s',
                $car->slug,
                $car->merk_model,
                $car->tahun,
                $category,
                $car->transmisi,
                $car->jumlah_kursi,
                $car->bahan_bakar,
                $car->kota,
                $car->formatted_price,
                $statusNote,
                $features
            );
        }

        $catalogText = implode("\n", $catalogLines);
        $today = now()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');
        $dayAfterTomorrow = now()->addDays(2)->format('Y-m-d');

        return <<<PROMPT
Anda adalah Asisten Virtual resmi MAZSEH (layanan sewa/rental mobil terpercaya).
Jawablah selalu dalam Bahasa Indonesia yang ramah, ringkas, sopan, dan akurat.

WAKTU REFERENSI SERVER:
- Tanggal hari ini: {$today} (besok: {$tomorrow}, lusa: {$dayAfterTomorrow}).
- Gunakan tanggal referensi ini ketika pelanggan menyebut waktu relatif seperti "hari ini", "besok", "lusa", atau tanggal tanpa tahun.

DAFTAR ARMADA REAL-TIME MAZSEH:
{$catalogText}

ATURAN LAYANAN MAZSEH:
- Lokasi operasional: Seluruh armada MAZSEH beroperasi di Klaten, Jawa Tengah (Pool Utama: Jl. Pemuda No. 88, Klaten).
- Opsi pengemudi: Lepas Kunci atau Dengan Sopir (tambahan tarif sopir Rp200.000/hari, belum termasuk BBM, tol, dan parkir).
- Syarat sewa lepas kunci: Wajib menyiapkan KTP asli dan SIM A aktif (diunggah pada halaman checkout atau menu profil akun).
- Metode pengambilan: Ambil langsung di pool MAZSEH atau antar ke lokasi pelanggan (tersedia pemilihan titik jemput GPS di checkout).
- Pembayaran: Mendukung Transfer Bank dan QRIS di halaman pembayaran setelah checkout.

ATURAN PENGGUNAAN TOOL:
- Jika pelanggan menanyakan ketersediaan mobil pada tanggal/hari tertentu, WAJIB panggil tool `check_car_availability` dengan `start_date` (dan `end_date` jika ada) dalam format YYYY-MM-DD sebelum menjawab.
- Jika hasil tool menunjukkan unit sedang dibooking (`bentrok_jadwal`) pada tanggal tersebut, beritahu pelanggan secara jelas rentang tanggal yang sudah terisi dan rekomendasikan unit alternatif yang berstatus tersedia (`available: true`) dari hasil tool.

BATASAN KETAT:
1. HANYA sebutkan mobil, harga, spesifikasi, dan status yang ada di DAFTAR ARMADA di atas. Jangan pernah mengarang unit atau harga fiktif.
2. JANGAN menyebutkan nama kota atau lokasi ("di Klaten", "lokasi Klaten", dsb.) saat merekomendasikan mobil atau menjawab harga/budget, KECUALI pelanggan secara eksplisit bertanya mengenai lokasi, kota, daerah operasional, atau alamat pool.
3. Jika suatu mobil berstatus MAINTENANCE (misalnya tidak tersedia sementara), beritahu pelanggan dengan jujur bahwa unit tersebut sedang dalam perawatan dan sarankan alternatif yang statusnya TERSEDIA.
4. Jangan meminta data sensitif (password, OTP, nomor kartu) dan jangan membahas status transaksi pribadi pelanggan (arahkan ke menu "Pesanan Saya" atau WhatsApp Admin jika menanyakan status booking spesifik).
5. Isi field `recommended_slugs` hanya dengan slug mobil dari daftar di atas yang relevan dengan jawaban Anda.
6. Set `needs_whatsapp` menjadi `true` jika pengguna meminta dihubungkan dengan admin/CS/WhatsApp/manusia atau menanyakan hal operasional khusus di luar informasi umum.
7. JIKA pengguna bertanya atau mengajak mengobrol DI LUAR topik rental mobil, armada, harga sewa, layanan MAZSEH, atau customer service (misalnya soal matematika, tugas sekolah, coding, politik, resep, curhat, dll.), JANGAN menjawab pertanyaan tersebut; cukup balas persis dengan kalimat: "Maaf, sebagai asisten MAZSEH saya hanya dapat membantu Anda seputar informasi rental mobil kami." dengan recommended_slugs = [] dan needs_whatsapp = false.
PROMPT;
    }

    /**
     * Mesin fallback lokal berbasis query database (mendukung multi-mobil, filter kota/budget/kursi, dan konteks sederhana).
     *
     * @param  array<int, array{role: string, text: string}>  $history
     * @param  Collection<int, KatalogMobil>  $cars
     * @return array{reply: string, recommended_slugs: array<int, string>, needs_whatsapp: bool}
     */
    protected function generateLocalFallback(string $message, array $history, Collection $cars): array
    {
        $lower = strtolower(trim($message));
        $asksLocation = $this->containsWholeWord($lower, ['kota', 'lokasi', 'cabang', 'daerah', 'alamat', 'posisi', 'tempat', 'pool', 'klaten'])
            || $this->containsAny($lower, ['dimana', 'di mana', 'area mana']);

        // 1. Deteksi kebutuhan eskalasi ke WhatsApp Admin (hindari false positive seperti "sewa" atau "bawah")
        if (
            $this->containsWholeWord($lower, ['wa', 'cs', 'admin', 'staf', 'staff', 'operator'])
            || $this->containsAny($lower, ['whatsapp', 'customer service', 'manusia', 'telepon', 'telp', 'hubung'])
        ) {
            return [
                'reply' => 'Tentu! Anda dapat langsung terhubung dengan tim customer service MAZSEH melalui WhatsApp untuk bantuan cepat.',
                'recommended_slugs' => [],
                'needs_whatsapp' => true,
            ];
        }

        // 2. Deteksi seluruh mobil yang disebut dalam pesan (mendukung multi-mobil sekaligus, misal Fortuner & Zenix)
        $dateRange = $this->extractRequestedDateRange($lower);
        $matchedCars = $this->detectMentionedCars($lower, $cars);
        if ($matchedCars->isNotEmpty()) {
            $descriptions = [];
            $slugs = [];
            $hasDateConflict = false;

            foreach ($matchedCars as $car) {
                $locationPart = $asksLocation ? ", lokasi {$car->kota}" : '';

                if ($car->status_ketersediaan !== 'tersedia') {
                    $slugs[] = $car->slug;
                    $statusText = 'Mohon maaf, saat ini unit sedang dalam perawatan (maintenance) sehingga belum dapat disewa sementara.';
                } elseif ($dateRange !== null) {
                    if ($car->isAvailableForDates($dateRange['start'], $dateRange['end'])) {
                        $slugs[] = $car->slug;
                        $statusText = "Saat ini tersedia untuk disewa pada tanggal {$dateRange['start']} s/d {$dateRange['end']}.";
                    } else {
                        $hasDateConflict = true;
                        $statusText = "Mohon maaf, unit ini sudah terpesan (tidak tersedia) pada tanggal {$dateRange['start']} s/d {$dateRange['end']}.";
                    }
                } else {
                    $slugs[] = $car->slug;
                    $statusText = 'Saat ini berstatus tersedia untuk dipesan.';
                }

                $descriptions[] = sprintf(
                    '%s (%d, %s, %d kursi, %s%s) bertarif mulai %s/hari. %s',
                    $car->merk_model,
                    $car->tahun,
                    $car->transmisi,
                    $car->jumlah_kursi,
                    $car->bahan_bakar,
                    $locationPart,
                    $car->formatted_price,
                    $statusText
                );
            }

            if ($hasDateConflict && $dateRange !== null) {
                $matchedSlugs = $matchedCars->pluck('slug')->all();
                $alternatives = $cars
                    ->filter(fn (KatalogMobil $car): bool => ! in_array($car->slug, $matchedSlugs, true) && $car->isAvailableForDates($dateRange['start'], $dateRange['end']))
                    ->take(3)
                    ->values();

                if ($alternatives->isNotEmpty()) {
                    $altLines = $alternatives->map(
                        static fn (KatalogMobil $car): string => "- {$car->merk_model} ({$car->formatted_price}/hari)"
                    )->implode("\n");
                    $descriptions[] = "Sebagai alternatif yang tersedia pada tanggal {$dateRange['start']} s/d {$dateRange['end']}:\n{$altLines}";

                    foreach ($alternatives as $alt) {
                        $slugs[] = $alt->slug;
                    }
                }
            }

            return [
                'reply' => implode("\n\n", $descriptions),
                'recommended_slugs' => array_values(array_unique($slugs)),
                'needs_whatsapp' => false,
            ];
        }

        // 3. Deteksi filter kota, kursi, tanggal, atau budget pada armada yang tersedia
        $availableCars = $cars->where('status_ketersediaan', 'tersedia')->values();
        if ($dateRange !== null) {
            $availableCars = $availableCars
                ->filter(fn (KatalogMobil $car): bool => $car->isAvailableForDates($dateRange['start'], $dateRange['end']))
                ->values();
        }

        $filtered = $availableCars;
        $hasFilter = false;
        $hasRentalDateIntent = $dateRange !== null && (
            $this->containsWholeWord($lower, ['mobil', 'unit', 'armada', 'rental', 'sewa', 'tersedia', 'kosong', 'ready', 'booking', 'pesan'])
            || $this->containsAny($lower, ['tersedia', 'kosong'])
        );

        if ($hasRentalDateIntent) {
            $hasFilter = true;
        }

        // Filter kota berdasarkan kota yang ada di database
        $cities = $availableCars->pluck('kota')->unique()->filter();
        foreach ($cities as $city) {
            if (str_contains($lower, strtolower((string) $city))) {
                $filtered = $filtered->filter(fn (KatalogMobil $car): bool => strtolower($car->kota) === strtolower((string) $city));
                $hasFilter = true;
                break;
            }
        }

        // Filter kapasitas / tipe penggunaan
        if ($this->containsAny($lower, ['keluarga', 'rombongan', '7 kursi', '7 orang', 'tujuh'])) {
            $filtered = $filtered->filter(fn (KatalogMobil $car): bool => $car->jumlah_kursi >= 7);
            $hasFilter = true;
        } elseif ($this->containsAny($lower, ['5 kursi', '5 orang', 'lima', 'sendiri', 'berdua', 'kecil', 'city car', 'hatchback'])) {
            $filtered = $filtered->filter(fn (KatalogMobil $car): bool => $car->jumlah_kursi <= 5);
            $hasFilter = true;
        }

        // Filter kategori atau jenis BBM
        foreach (['suv', 'mpv', 'hatchback', 'sedan'] as $categoryName) {
            if ($this->containsWholeWord($lower, [$categoryName])) {
                $filtered = $filtered->filter(
                    fn (KatalogMobil $car): bool => strtolower($car->kategori?->nama_kategori ?? '') === $categoryName
                );
                $hasFilter = true;
                break;
            }
        }

        if ($this->containsWholeWord($lower, ['hybrid', 'diesel', 'bensin'])) {
            foreach (['hybrid', 'diesel', 'bensin'] as $fuel) {
                if ($this->containsWholeWord($lower, [$fuel])) {
                    $filtered = $filtered->filter(fn (KatalogMobil $car): bool => strtolower($car->bahan_bakar) === $fuel);
                    $hasFilter = true;
                    break;
                }
            }
        }

        // Filter hemat / murah / batas budget angka (misal: "di bawah 500rb" atau "maksimal 700000")
        $maxBudget = $this->extractMaxBudget($lower);
        if ($maxBudget !== null) {
            $filtered = $filtered->filter(fn (KatalogMobil $car): bool => (float) $car->harga_sewa <= $maxBudget);
            $hasFilter = true;
        } elseif ($this->containsAny($lower, ['hemat', 'murah', 'ekonomis', 'termurah'])) {
            $filtered = $filtered->sortBy('harga_sewa')->take(2);
            $hasFilter = true;
        }

        if ($hasFilter) {
            $filtered = $filtered->values();
            if ($filtered->isEmpty()) {
                return [
                    'reply' => 'Maaf, saat ini belum ada unit tersedia yang cocok persis dengan kriteria tersebut. Anda dapat melihat seluruh katalog atau menghubungi Admin via WhatsApp untuk bantuan penjadwalan.',
                    'recommended_slugs' => [],
                    'needs_whatsapp' => true,
                ];
            }

            $topCars = $filtered->take(3);
            $listLines = $topCars->map(function (KatalogMobil $car) use ($asksLocation): string {
                $meta = $asksLocation
                    ? "{$car->kota}, {$car->jumlah_kursi} kursi"
                    : "{$car->jumlah_kursi} kursi, {$car->transmisi}";

                return "- {$car->merk_model} ({$meta}) — {$car->formatted_price}/hari";
            })->implode("\n");

            $headerText = $dateRange !== null
                ? "Berikut rekomendasi unit MAZSEH yang tersedia pada tanggal {$dateRange['start']} s/d {$dateRange['end']}:"
                : 'Berikut rekomendasi unit MAZSEH yang tersedia sesuai kebutuhan Anda:';

            return [
                'reply' => "{$headerText}\n{$listLines}",
                'recommended_slugs' => $topCars->pluck('slug')->all(),
                'needs_whatsapp' => false,
            ];
        }

        // 4. Deteksi follow-up singkat berbasis riwayat sebelumnya
        if ($history !== [] && $this->containsAny($lower, ['harganya', 'berapa', 'lebih murah', 'paling murah'])) {
            $cheapest = $availableCars->sortBy('harga_sewa')->first();
            if ($cheapest instanceof KatalogMobil) {
                $locationSuffix = $asksLocation ? " di {$cheapest->kota}" : '';

                return [
                    'reply' => "Pilihan tersedia dengan tarif paling terjangkau saat ini adalah {$cheapest->merk_model}{$locationSuffix} mulai dari {$cheapest->formatted_price}/hari.",
                    'recommended_slugs' => [$cheapest->slug],
                    'needs_whatsapp' => false,
                ];
            }
        }

        // 4b. Deteksi pertanyaan khusus lokasi/kota/alamat pool
        if ($asksLocation) {
            return [
                'reply' => 'Seluruh armada MAZSEH beroperasi di Klaten (Pool Utama: Jl. Pemuda No. 88, Klaten, Jawa Tengah). Kami melayani pengambilan langsung di pool maupun pengantaran ke lokasi Anda.',
                'recommended_slugs' => [],
                'needs_whatsapp' => false,
            ];
        }

        // 5. Deteksi pertanyaan aturan sewa, syarat, sopir, dan pembayaran
        if (
            $this->containsWholeWord($lower, ['ktp', 'sim'])
            || $this->containsAny($lower, ['syarat', 'dokumen', 'persyaratan'])
        ) {
            return [
                'reply' => 'Syarat utama sewa lepas kunci di MAZSEH adalah menyiapkan foto KTP asli dan SIM A aktif. Anda dapat langsung mengunggahnya pada formulir checkout atau melalui menu profil akun Anda.',
                'recommended_slugs' => [],
                'needs_whatsapp' => false,
            ];
        }

        if ($this->containsAny($lower, ['sopir', 'driver', 'lepas kunci'])) {
            return [
                'reply' => "Kami menyediakan opsi 'Lepas Kunci' maupun 'Dengan Sopir'. Layanan sopir profesional bertarif tambahan Rp200.000/hari (di luar biaya BBM, tol, dan parkir).",
                'recommended_slugs' => [],
                'needs_whatsapp' => false,
            ];
        }

        if ($this->containsAny($lower, ['bayar', 'pembayaran', 'qris', 'transfer', 'checkout', 'pesan', 'booking'])) {
            return [
                'reply' => 'Pemesanan dapat dilakukan langsung dari halaman detail unit mobil. Setelah checkout dan memilih lokasi jemput GPS, Anda dapat membayar melalui Transfer Bank atau QRIS.',
                'recommended_slugs' => [],
                'needs_whatsapp' => false,
            ];
        }

        // 6. Sapaan murni atau pertanyaan umum seputar layanan rental MAZSEH
        $isPureGreeting = preg_match('/^(halo|hai|hi|hello|hey|p|permisi|assalamualaikum|selamat\s+(pagi|siang|sore|malam))[\s!?.]*$/u', $lower) === 1;
        $isRentalTopic = $this->containsWholeWord($lower, ['mobil', 'unit', 'armada', 'rental', 'sewa', 'mazseh', 'katalog', 'daftar', 'harga', 'tarif', 'biaya', 'budget', 'kursi', 'penumpang', 'matic', 'otomatis', 'manual', 'bensin', 'diesel', 'hybrid', 'promo', 'diskon'])
            || $this->containsAny($lower, ['rekomendasi', 'rekomendasikan', 'lepas kunci', 'tersedia']);

        if ($isPureGreeting || $isRentalTopic) {
            $availableSummary = $availableCars->take(5)->pluck('merk_model')->implode(', ');

            return [
                'reply' => "Halo! MAZSEH menyediakan berbagai unit tersedia seperti {$availableSummary} dengan opsi lepas kunci atau dengan sopir. Silakan sebutkan tipe mobil, jumlah penumpang, atau kisaran budget Anda.",
                'recommended_slugs' => [],
                'needs_whatsapp' => false,
            ];
        }

        // 7. Penolakan sopan untuk topik di luar rental mobil MAZSEH & customer service
        return [
            'reply' => 'Maaf, sebagai asisten MAZSEH saya hanya dapat membantu Anda seputar informasi rental mobil kami.',
            'recommended_slugs' => [],
            'needs_whatsapp' => false,
        ];
    }

    /**
     * Mencocokkan slug/nama mobil dari database secara dinamis tanpa if-elseif hardcoded.
     *
     * @param  Collection<int, KatalogMobil>  $cars
     * @return Collection<int, KatalogMobil>
     */
    protected function detectMentionedCars(string $lowerMessage, Collection $cars): Collection
    {
        return $cars->filter(function (KatalogMobil $car) use ($lowerMessage): bool {
            if (str_contains($lowerMessage, strtolower($car->slug))) {
                return true;
            }

            if (str_contains($lowerMessage, strtolower($car->merk_model))) {
                return true;
            }

            // Alias umum untuk kata kunci nama model selain kata merek umum ("toyota")
            $words = preg_split('/\s+/', strtolower($car->merk_model)) ?: [];
            foreach ($words as $word) {
                if (strlen($word) >= 4 && ! in_array($word, ['toyota', 'mobil', 'hybrid', 'sport'], true)) {
                    if ($this->containsWholeWord($lowerMessage, [$word])) {
                        return true;
                    }
                }
            }

            return false;
        })->values();
    }

    /**
     * Ekstrak angka maksimal budget harian bila pengguna menyebut nominal (misal 500rb, 500.000, 1jt).
     */
    protected function extractMaxBudget(string $lowerMessage): ?float
    {
        if (preg_match('/\b(\d+(?:[.,]\d+)?)\s*(jt|juta)\b/', $lowerMessage, $matches)) {
            $value = (float) str_replace(',', '.', $matches[1]);

            return $value * 1000000;
        }

        if (preg_match('/\b(\d+(?:[.,]\d+)?)\s*(rb|ribu|k)\b/', $lowerMessage, $matches)) {
            $value = (float) str_replace(['.', ','], ['', '.'], $matches[1]);

            return $value * 1000;
        }

        $hasBudgetCue = $this->containsAny($lowerMessage, ['budget', 'harga', 'tarif', 'sewa', 'mobil', 'bawah', 'maks', 'rp', 'dana', 'hemat', 'murah']);
        if ($hasBudgetCue && preg_match('/\b(\d{6,7})\b/', str_replace('.', '', $lowerMessage), $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Ekstrak rentang tanggal sewa sederhana dari pesan pengguna (YYYY-MM-DD, hari ini, besok, lusa).
     *
     * @return array{start: string, end: string}|null
     */
    protected function extractRequestedDateRange(string $lowerMessage): ?array
    {
        if (preg_match_all('/\b(\d{4}-\d{2}-\d{2})\b/', $lowerMessage, $matches) && $matches[1] !== []) {
            try {
                $startDate = Carbon::parse($matches[1][0])->startOfDay();
                $endDate = isset($matches[1][1])
                    ? Carbon::parse($matches[1][1])->startOfDay()
                    : $startDate->copy()->addDay();

                if ($endDate->lessThanOrEqualTo($startDate)) {
                    $endDate = $startDate->copy()->addDay();
                }

                return [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ];
            } catch (Throwable) {
                return null;
            }
        }

        if (str_contains($lowerMessage, 'hari ini')) {
            return [
                'start' => now()->format('Y-m-d'),
                'end' => now()->addDay()->format('Y-m-d'),
            ];
        }

        if ($this->containsWholeWord($lowerMessage, ['besok'])) {
            return [
                'start' => now()->addDay()->format('Y-m-d'),
                'end' => now()->addDays(2)->format('Y-m-d'),
            ];
        }

        if ($this->containsWholeWord($lowerMessage, ['lusa'])) {
            return [
                'start' => now()->addDays(2)->format('Y-m-d'),
                'end' => now()->addDays(3)->format('Y-m-d'),
            ];
        }

        return null;
    }

    /**
     * Memvalidasi daftar slug mobil terhadap koleksi KatalogMobil di database
     * agar tidak pernah menghasilkan link unit fiktif.
     *
     * @param  array<int, string>  $slugs
     * @param  Collection<int, KatalogMobil>  $cars
     * @return array<int, array{slug: string, name: string, price: string, status: string, url: string}>
     */
    protected function resolveRecommendedCars(array $slugs, string $reply, Collection $cars, bool $allowReplyFallback): array
    {
        $normalizedSlugs = array_map(static fn (string $slug): string => strtolower(trim($slug)), $slugs);

        $matched = $cars->filter(
            fn (KatalogMobil $car): bool => in_array(strtolower($car->slug), $normalizedSlugs, true)
        );

        if ($matched->isEmpty() && $allowReplyFallback) {
            $matched = $this->detectMentionedCars(strtolower($reply), $cars)->take(3);
        }

        return $matched->values()->map(static function (KatalogMobil $car): array {
            return [
                'slug' => $car->slug,
                'name' => $car->merk_model,
                'price' => $car->formatted_price,
                'status' => $car->status_ketersediaan,
                'url' => route('catalog.show', $car->slug, false),
            ];
        })->all();
    }

    /**
     * Helper pengecekan kemunculan salah satu kata kunci.
     *
     * @param  array<int, string>  $needles
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper pengecekan kata utuh (word-boundary) agar kata pendek seperti "wa" atau "sim"
     * tidak bentrok dengan kata lain seperti "sewa", "bawah", atau "maksimal".
     *
     * @param  array<int, string>  $words
     */
    protected function containsWholeWord(string $haystack, array $words): bool
    {
        $escaped = array_map(static fn (string $word): string => preg_quote($word, '/'), $words);
        $pattern = '/\b('.implode('|', $escaped).')\b/u';

        return preg_match($pattern, $haystack) === 1;
    }
}
