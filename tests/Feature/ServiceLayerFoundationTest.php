<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleMiddleware;
use App\Models\DocumentPelanggan;
use App\Models\KatalogMobil;
use App\Models\KategoriMobil;
use App\Models\LogActivity;
use App\Models\Pembayaran;
use App\Models\Pemeliharaan;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\LaporanService;
use App\Services\PemeliharaanService;
use App\Services\TransaksiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ServiceLayerFoundationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * RoleMiddleware mengarahkan tamu ke login, menolak role tidak cocok dengan 403, dan meloloskan role valid.
     */
    public function test_role_middleware_enforces_authentication_and_allowed_roles(): void
    {
        $middleware = new RoleMiddleware;
        $next = static fn (Request $request): Response => new Response('OK', 200);

        $guestRequest = Request::create('/admin', 'GET');
        $guestResponse = $middleware->handle($guestRequest, $next, 'admin');
        $this->assertSame(302, $guestResponse->getStatusCode());
        $this->assertSame(route('login'), $guestResponse->headers->get('Location'));

        $pelanggan = User::factory()->create(['role' => 'pelanggan']);
        $customerRequest = Request::create('/admin', 'GET');
        $customerRequest->setUserResolver(static fn (): User => $pelanggan);

        try {
            $middleware->handle($customerRequest, $next, 'admin');
            $this->fail('Seharusnya melempar HttpException 403 untuk pelanggan.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $adminRequest = Request::create('/admin', 'GET');
        $adminRequest->setUserResolver(static fn (): User => $admin);
        $allowedResponse = $middleware->handle($adminRequest, $next, 'kasir', 'admin');
        $this->assertSame(200, $allowedResponse->getStatusCode());
    }

    /**
     * Helper role pada User, atribut status Transaksi, catatan DocumentPelanggan, dan ketersediaan KatalogMobil bekerja konsisten.
     */
    public function test_model_helpers_and_availability_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kasir = User::factory()->create(['role' => 'kasir']);
        $pelanggan = User::factory()->create(['role' => 'pelanggan']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isKasir());
        $this->assertTrue($kasir->isKasir());
        $this->assertTrue($pelanggan->isPelanggan());

        $document = DocumentPelanggan::create([
            'user_id' => $pelanggan->id,
            'file_ktp' => 'documents/ktp.jpg',
            'file_sim' => 'documents/sim.jpg',
            'status_verifikasi' => 'ditolak',
            'catatan_verifikasi' => 'Foto SIM buram, mohon unggah ulang.',
        ]);
        $this->assertSame('Foto SIM buram, mohon unggah ulang.', $document->fresh()->catatan_verifikasi);

        $car = $this->createCar();

        $finishedOrder = Transaksi::create([
            'user_id' => $pelanggan->id,
            'id_mobil' => $car->id_mobil,
            'kode_booking' => 'MZS-FIN'.Str::upper(Str::random(4)),
            'tanggal_mulai' => '2026-12-01',
            'tanggal_selesai' => '2026-12-03',
            'total_harga' => 700000,
            'status_pesanan' => 'selesai',
            'titik_jemput' => 'Pool MAZSEH',
            'waktu_pesan' => now(),
        ]);

        $this->assertSame('Selesai', $finishedOrder->status_label);
        $this->assertSame('green', $finishedOrder->status_badge_class);
        $this->assertTrue($car->isAvailableForDates('2026-12-01', '2026-12-03'));

        $rentedOrder = new Transaksi(['status_pesanan' => 'sedang_disewa']);
        $this->assertSame('Sedang Disewa', $rentedOrder->status_label);
        $this->assertSame('blue', $rentedOrder->status_badge_class);

        $car->update(['status_ketersediaan' => 'maintenance']);
        $this->assertFalse($car->fresh()->isAvailableForDates('2026-12-10', '2026-12-12'));
    }

    /**
     * TransaksiService menerapkan Optimistic Locking, siklus penuh serah-terima/pengembalian, serta audit log.
     */
    public function test_transaksi_service_optimistic_locking_and_full_rental_lifecycle(): void
    {
        $service = app(TransaksiService::class);
        $pelanggan = User::factory()->create(['role' => 'pelanggan']);
        $kasir = User::factory()->create(['role' => 'kasir']);
        $car = $this->createCar(['harga_sewa' => 400000, 'versi' => 3]);

        // Optimistic Locking gagal jika versi usang
        try {
            $service->createBooking($pelanggan, $car, [
                'start' => '2026-12-10',
                'end' => '2026-12-12',
                'versi' => 2,
            ]);
            $this->fail('Seharusnya melempar ValidationException saat versi mobil sudah berubah.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('mobil', $exception->errors());
        }

        // Booking berhasil & menaikkan versi
        $transaksi = $service->createBooking($pelanggan, $car, [
            'start' => '2026-12-10',
            'end' => '2026-12-12',
            'opsi_pengemudi' => 'Dengan sopir',
            'versi' => 3,
        ]);

        $this->assertSame(4, $car->fresh()->versi);
        $this->assertSame(1200000.0, (float) $transaksi->total_harga);
        $this->assertSame('menunggu_pembayaran', $transaksi->status_pesanan);
        $this->assertSame('pending', $transaksi->pembayaran->status_pembayaran);

        // Tidak boleh serah-terima sebelum lunas & dikonfirmasi
        try {
            $service->handoverUnit($transaksi, $kasir);
            $this->fail('Seharusnya gagal serah-terima sebelum pembayaran lunas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('transaksi', $exception->errors());
        }

        // Verifikasi pembayaran -> lunas & dikonfirmasi
        $payment = $service->verifyPayment($transaksi->pembayaran, $kasir, true, 'Transfer BCA cocok');
        $this->assertSame('lunas', $payment->status_pembayaran);
        $this->assertSame($kasir->id, $payment->kasir_id);
        $this->assertSame('dikonfirmasi', $transaksi->fresh()->status_pesanan);
        $this->assertTrue($kasir->pembayaranDivalidasi()->where('id_pembayaran', $payment->id_pembayaran)->exists());

        // Serah-terima kunci -> sedang_disewa & mobil disewakan
        $service->handoverUnit($transaksi, $kasir);
        $this->assertSame('sedang_disewa', $transaksi->fresh()->status_pesanan);
        $this->assertSame('disewakan', $car->fresh()->status_ketersediaan);
        $this->assertSame(5, $car->fresh()->versi);

        // Pengembalian unit -> selesai & mobil tersedia
        $service->returnUnit($transaksi, $kasir);
        $this->assertSame('selesai', $transaksi->fresh()->status_pesanan);
        $this->assertSame('tersedia', $car->fresh()->status_ketersediaan);
        $this->assertSame(6, $car->fresh()->versi);

        $this->assertTrue($kasir->logActivity()->where('tabel_terdampak', 'transaksi')->exists());
        $this->assertTrue($kasir->logActivity()->where('tabel_terdampak', 'pembayaran')->exists());
    }

    /**
     * Pembatalan transaksi yang sudah lunas otomatis mengaktifkan status refund dan refund dapat diproses kasir.
     */
    public function test_cancel_paid_order_triggers_refund_and_process_refund_completes_it(): void
    {
        $service = app(TransaksiService::class);
        $pelanggan = User::factory()->create(['role' => 'pelanggan']);
        $kasir = User::factory()->create(['role' => 'kasir']);
        $car = $this->createCar(['harga_sewa' => 500000]);

        $transaksi = $service->createBooking($pelanggan, $car, [
            'start' => '2026-12-15',
            'end' => '2026-12-17',
            'langsung_lunas' => true,
            'metode_pembayaran' => 'Tunai di kasir',
        ], $kasir->id);

        $this->assertSame('dikonfirmasi', $transaksi->status_pesanan);
        $this->assertSame('lunas', $transaksi->pembayaran->status_pembayaran);

        // Batalkan lewat endpoint OrderController::cancel agar integrasi controller ikut teruji
        $this->actingAs($pelanggan)
            ->post(route('orders.cancel', $transaksi->id_transaksi), [
                'alasan_batal' => 'Jadwal perjalanan dibatalkan mendadak.',
            ])
            ->assertRedirect(route('orders.show', $transaksi->id_transaksi));

        $transaksi->refresh();
        $pembayaran = $transaksi->pembayaran->fresh();
        $this->assertSame('dibatalkan', $transaksi->status_pesanan);
        $this->assertSame('menunggu_proses', $pembayaran->status_refund);

        // Refund melebihi nominal bayar harus ditolak
        try {
            $service->processRefund($pembayaran, 1500000.0, $kasir);
            $this->fail('Seharusnya menolak refund yang melebihi jumlah bayar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('jumlah_refund', $exception->errors());
        }

        // Proses refund valid
        $refunded = $service->processRefund($pembayaran, 800000.0, $kasir);
        $this->assertSame('selesai', $refunded->status_refund);
        $this->assertSame(800000.0, (float) $refunded->jumlah_refund);
        $this->assertSame($kasir->id, $refunded->kasir_id);
    }

    /**
     * PemeliharaanService menolak mobil sedang disewa dan menyinkronkan status maintenance <-> tersedia.
     */
    public function test_pemeliharaan_service_syncs_fleet_availability(): void
    {
        $service = app(PemeliharaanService::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $rentedCar = $this->createCar(['status_ketersediaan' => 'disewakan']);
        try {
            $service->createMaintenance([
                'id_mobil' => $rentedCar->id_mobil,
                'tanggal_masuk' => '2026-11-24',
                'jenis_perawatan' => 'Ganti Oli',
                'total_biaya' => 350000,
            ], $admin);
            $this->fail('Seharusnya menolak perawatan pada unit yang sedang disewakan.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('id_mobil', $exception->errors());
        }

        $car = $this->createCar(['status_ketersediaan' => 'tersedia', 'versi' => 1]);
        $record1 = $service->createMaintenance([
            'id_mobil' => $car->id_mobil,
            'tanggal_masuk' => '2026-11-24',
            'jenis_perawatan' => 'Servis Berkala',
            'total_biaya' => 500000,
            'keterangan_bengkel' => 'Cek rem dan oli.',
        ], $admin);

        $this->assertSame('maintenance', $car->fresh()->status_ketersediaan);
        $this->assertSame(2, $car->fresh()->versi);
        $this->assertFalse($record1->isCompleted());
        $this->assertSame('Rp500.000', $record1->formatted_cost);

        $record2 = $service->createMaintenance([
            'id_mobil' => $car->id_mobil,
            'tanggal_masuk' => '2026-11-24',
            'jenis_perawatan' => 'Spooring Balancing',
            'total_biaya' => 250000,
        ], $admin);

        // Selesaikan record pertama: masih ada record2 terbuka, status tetap maintenance
        $service->completeMaintenance($record1, '2026-11-25', 550000.0, 'Selesai ganti oli.', $admin);
        $this->assertTrue($record1->fresh()->isCompleted());
        $this->assertSame('maintenance', $car->fresh()->status_ketersediaan);

        // Selesaikan record kedua: tidak ada servis aktif tersisa, mobil kembali tersedia
        $service->completeMaintenance($record2, '2026-11-25', null, null, $admin);
        $this->assertSame('tersedia', $car->fresh()->status_ketersediaan);
        $this->assertSame(2, $car->pemeliharaan()->count());
        $this->assertTrue(LogActivity::where('tabel_terdampak', 'pemeliharaan')->exists());
    }

    /**
     * LaporanService menghitung ringkasan keuangan dengan akurat dan menghasilkan respons unduhan CSV.
     */
    public function test_laporan_service_computes_summary_and_streams_csv(): void
    {
        $laporanService = app(LaporanService::class);
        $pelanggan = User::factory()->create(['role' => 'pelanggan', 'name' => 'Pelanggan Laporan']);
        $car = $this->createCar(['merk_model' => 'Toyota Laporan']);

        $targetDate = '2035-06-15';

        $order = Transaksi::create([
            'user_id' => $pelanggan->id,
            'id_mobil' => $car->id_mobil,
            'kode_booking' => 'MZS-RPT'.Str::upper(Str::random(4)),
            'tanggal_mulai' => '2035-06-16',
            'tanggal_selesai' => '2035-06-18',
            'total_harga' => 1000000,
            'status_pesanan' => 'dikonfirmasi',
            'titik_jemput' => 'Pool MAZSEH',
            'waktu_pesan' => $targetDate.' 10:00:00',
        ]);

        Pembayaran::create([
            'id_transaksi' => $order->id_transaksi,
            'jumlah_bayar' => 1000000,
            'status_pembayaran' => 'lunas',
            'jumlah_refund' => 200000,
            'status_refund' => 'selesai',
            'metode_pembayaran' => 'Transfer bank',
            'waktu_bayar' => $targetDate.' 11:00:00',
        ]);

        Pemeliharaan::create([
            'id_mobil' => $car->id_mobil,
            'tanggal_masuk' => $targetDate,
            'tanggal_selesai' => $targetDate,
            'jenis_perawatan' => 'Tune Up',
            'total_biaya' => 150000,
        ]);

        $summary = $laporanService->getFinancialSummary('2035-06-01', '2035-06-30');

        $this->assertSame(1000000.0, $summary['pendapatan_kotor']);
        $this->assertSame(200000.0, $summary['total_refund']);
        $this->assertSame(800000.0, $summary['pendapatan_bersih']);
        $this->assertSame(150000.0, $summary['biaya_pemeliharaan']);
        $this->assertSame(650000.0, $summary['laba_bersih']);
        $this->assertSame(1, $summary['total_transaksi']);
        $this->assertSame(1, $summary['transaksi_selesai']);
        $this->assertSame(0, $summary['transaksi_dibatalkan']);

        $csvResponse = $laporanService->streamCsvReport('2035-06-01', '2035-06-30');
        $this->assertStringContainsString('laporan-keuangan-mazseh-', (string) $csvResponse->headers->get('Content-Disposition'));

        ob_start();
        $csvResponse->sendContent();
        $csvOutput = (string) ob_get_clean();

        $this->assertStringContainsString('Kode Booking', $csvOutput);
        $this->assertStringContainsString($order->kode_booking, $csvOutput);
        $this->assertStringContainsString('800000.00', $csvOutput);
    }

    /**
     * ActivityLogService mendeteksi pola SQL Injection pada input mencurigakan dan meloloskan email normal.
     */
    public function test_activity_log_service_detects_sqli_patterns(): void
    {
        $activityLog = app(ActivityLogService::class);

        $this->assertTrue($activityLog->containsSqlInjectionPattern("' OR '1'='1' --"));
        $this->assertTrue($activityLog->containsSqlInjectionPattern("admin'or'1'='1@mazseh.test"));
        $this->assertTrue($activityLog->containsSqlInjectionPattern('test UNION SELECT password FROM users'));
        $this->assertFalse($activityLog->containsSqlInjectionPattern('pelanggan@mazseh.demo'));
    }

    /**
     * Buat unit armada uji beserta kategorinya.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createCar(array $overrides = []): KatalogMobil
    {
        $category = KategoriMobil::create(['nama_kategori' => 'Uji Service '.Str::random(4)]);
        $suffix = Str::upper(Str::random(5));

        return KatalogMobil::create(array_merge([
            'id_kategori' => $category->id_kategori,
            'plat_nomor' => 'AD '.$suffix,
            'merk_model' => 'Unit Uji '.$suffix,
            'slug' => 'unit-uji-'.Str::lower($suffix),
            'harga_sewa' => 350000,
            'status_ketersediaan' => 'tersedia',
            'versi' => 0,
        ], $overrides));
    }
}
