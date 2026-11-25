<?php

namespace Tests\Feature;

use App\Models\DocumentPelanggan;
use App\Models\KatalogMobil;
use App\Models\KategoriMobil;
use App\Models\LogActivity;
use App\Models\Pembayaran;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class KasirOperasionalTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Tamu diarahkan ke login dan pelanggan mendapat 403 di seluruh endpoint panel kasir.
     */
    public function test_tamu_dan_pelanggan_ditolak_mengakses_endpoint_panel_kasir(): void
    {
        [$pelanggan, $order] = $this->createOrderWithPayment();
        $dokumen = DocumentPelanggan::create([
            'user_id' => $pelanggan->id,
            'file_ktp' => 'documents/ktp.png',
            'file_sim' => 'documents/sim.png',
            'status_verifikasi' => 'menunggu_validasi',
        ]);

        $getRoutes = [
            route('kasir.dashboard'),
            route('kasir.pembayaran.index'),
            route('kasir.transaksi.index'),
            route('kasir.transaksi.create'),
            route('kasir.transaksi.show', $order->id_transaksi),
            route('kasir.transaksi.receipt', $order->id_transaksi),
            route('kasir.refund.index'),
            route('kasir.dokumen.index'),
        ];

        foreach ($getRoutes as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        foreach ($getRoutes as $url) {
            $this->actingAs($pelanggan)->get($url)->assertForbidden();
        }

        $postEndpoints = [
            [route('kasir.pembayaran.verify', $order->pembayaran->id_pembayaran), ['keputusan' => 'terima']],
            [route('kasir.transaksi.store'), []],
            [route('kasir.transaksi.handover', $order->id_transaksi), []],
            [route('kasir.transaksi.return', $order->id_transaksi), []],
            [route('kasir.transaksi.cancel', $order->id_transaksi), ['alasan_batal' => 'Batal']],
            [route('kasir.refund.process', $order->pembayaran->id_pembayaran), ['jumlah_refund' => 100000]],
            [route('kasir.dokumen.verify', $dokumen->id_dokumen), ['status_verifikasi' => 'disetujui']],
        ];

        foreach ($postEndpoints as [$url, $payload]) {
            $this->actingAs($pelanggan)->post($url, $payload)->assertForbidden();
        }
    }

    /**
     * Kasir dan admin dapat membuka seluruh halaman panel kasir beserta kontrak datanya.
     */
    public function test_kasir_dan_admin_dapat_mengakses_seluruh_halaman_panel_kasir(): void
    {
        [, $order] = $this->createOrderWithPayment();

        foreach (['kasir', 'admin'] as $role) {
            $staff = User::factory()->create(['role' => $role]);

            $dashboard = $this->actingAs($staff)->get(route('kasir.dashboard'))->assertOk();
            $dashboard->assertViewHasAll(['stats', 'pendingPayments', 'activeRentals']);
            $stats = $dashboard->viewData('stats');
            $this->assertArrayHasKey('pembayaran_pending', $stats);
            $this->assertArrayHasKey('dokumen_pending', $stats);
            $this->assertArrayHasKey('refund_pending', $stats);
            $this->assertArrayHasKey('sedang_disewa', $stats);
            $this->assertArrayHasKey('jadwal_ambil_hari_ini', $stats);
            $this->assertArrayHasKey('jadwal_kembali_hari_ini', $stats);

            $this->actingAs($staff)->get(route('kasir.pembayaran.index'))
                ->assertOk()
                ->assertViewHas('pembayaranList');

            $this->actingAs($staff)->get(route('kasir.transaksi.index'))
                ->assertOk()
                ->assertViewHas('transaksiList');

            $this->actingAs($staff)->get(route('kasir.transaksi.create'))
                ->assertOk()
                ->assertViewHasAll(['cars', 'customers']);

            $this->actingAs($staff)->get(route('kasir.transaksi.show', $order->id_transaksi))
                ->assertOk()
                ->assertViewHas('order');

            $this->actingAs($staff)->get(route('kasir.transaksi.receipt', $order->id_transaksi))
                ->assertOk()
                ->assertSee('Kuitansi Resmi Pembayaran')
                ->assertSee($order->kode_booking);

            $this->actingAs($staff)->get(route('kasir.refund.index'))
                ->assertOk()
                ->assertViewHas('refundList');

            $this->actingAs($staff)->get(route('kasir.dokumen.index'))
                ->assertOk()
                ->assertViewHas('dokumenList');
        }
    }

    /**
     * Kasir dapat memfilter daftar pembayaran dan memverifikasi pembayaran (terima & tolak).
     */
    public function test_kasir_dapat_filter_dan_verifikasi_pembayaran(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        [, $orderApprove] = $this->createOrderWithPayment();
        [, $orderReject] = $this->createOrderWithPayment();

        $this->actingAs($kasir)->get(route('kasir.pembayaran.index', [
            'status' => 'pending',
            'metode' => 'Transfer bank',
            'q' => $orderApprove->kode_booking,
        ]))
            ->assertOk()
            ->assertSee($orderApprove->kode_booking)
            ->assertDontSee($orderReject->kode_booking);

        $this->actingAs($kasir)->post(route('kasir.pembayaran.verify', $orderApprove->pembayaran->id_pembayaran), [
            'keputusan' => 'tidak_valid',
        ])->assertSessionHasErrors('keputusan');

        $this->actingAs($kasir)->post(route('kasir.pembayaran.verify', $orderApprove->pembayaran->id_pembayaran), [
            'keputusan' => 'terima',
            'catatan' => 'Mutasi rekening cocok',
        ])->assertRedirect()->assertSessionHas('success');

        $orderApprove->refresh();
        $this->assertSame('dikonfirmasi', $orderApprove->status_pesanan);
        $this->assertSame('lunas', $orderApprove->pembayaran->status_pembayaran);
        $this->assertSame($kasir->id, $orderApprove->pembayaran->kasir_id);
        $this->assertSame('Mutasi rekening cocok', $orderApprove->pembayaran->catatan_kasir);
        $this->assertTrue(LogActivity::where('user_id', $kasir->id)->where('tabel_terdampak', 'pembayaran')->exists());

        $this->actingAs($kasir)->post(route('kasir.pembayaran.verify', $orderReject->pembayaran->id_pembayaran), [
            'keputusan' => 'tolak',
            'catatan' => 'Bukti transfer tidak terbaca',
        ])->assertRedirect()->assertSessionHas('success');

        $orderReject->refresh();
        $this->assertSame('menunggu_pembayaran', $orderReject->status_pesanan);
        $this->assertSame('gagal', $orderReject->pembayaran->status_pembayaran);
        $this->assertSame('Bukti transfer tidak terbaca', $orderReject->pembayaran->catatan_kasir);
    }

    /**
     * Kasir dapat membuat transaksi walk-in untuk pelanggan baru (langsung lunas) dan pelanggan terdaftar.
     */
    public function test_kasir_dapat_membuat_transaksi_walk_in_pelanggan_baru_dan_terdaftar(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $car = $this->createCar(['harga_sewa' => 400000, 'versi' => 2]);
        $newEmail = 'walkin-'.Str::lower(Str::random(6)).'@mazseh.test';

        // Validasi menolak input kosong tanpa user_id maupun data pelanggan baru
        $this->actingAs($kasir)->post(route('kasir.transaksi.store'), [
            'id_mobil' => $car->id_mobil,
        ])->assertSessionHasErrors(['customer_name', 'customer_email', 'customer_phone', 'sim_number']);

        // Optimistic locking menolak versi usang
        $this->actingAs($kasir)->post(route('kasir.transaksi.store'), [
            'customer_name' => 'Walk-in Stale',
            'customer_email' => $newEmail,
            'customer_phone' => '081234560000',
            'sim_number' => 'SIM-A-990011',
            'id_mobil' => $car->id_mobil,
            'start' => now()->addDays(2)->toDateString(),
            'end' => now()->addDays(4)->toDateString(),
            'waktu_ambil' => '09:00',
            'opsi_pengemudi' => 'Lepas kunci',
            'metode_pengambilan' => 'Ambil di pool MAZSEH',
            'titik_jemput' => 'Pool MAZSEH Klaten',
            'metode_pembayaran' => 'Tunai di kasir',
            'langsung_lunas' => 1,
            'versi' => 1,
        ])->assertSessionHasErrors('mobil');

        $this->assertDatabaseMissing('users', ['email' => $newEmail]);

        // Pelanggan baru dengan langsung lunas
        $response = $this->actingAs($kasir)->post(route('kasir.transaksi.store'), [
            'customer_name' => 'Rina Walkin',
            'customer_email' => strtoupper($newEmail),
            'customer_phone' => '081234560001',
            'sim_number' => 'SIM-A-990012',
            'id_mobil' => $car->id_mobil,
            'start' => now()->addDays(2)->toDateString(),
            'end' => now()->addDays(4)->toDateString(),
            'waktu_ambil' => '09:00',
            'opsi_pengemudi' => 'Dengan sopir',
            'metode_pengambilan' => 'Ambil di pool MAZSEH',
            'titik_jemput' => 'Pool MAZSEH Klaten',
            'metode_pembayaran' => 'Tunai di kasir',
            'referensi_pembayaran' => 'TUNAI-001',
            'langsung_lunas' => 1,
            'catatan' => 'Pelanggan datang langsung ke loket',
            'versi' => 2,
        ]);

        $createdCustomer = User::where('email', $newEmail)->first();
        $this->assertNotNull($createdCustomer);
        $this->assertSame('pelanggan', $createdCustomer->role);
        $this->assertSame('SIM-A-990012', $createdCustomer->sim_number);

        $walkInOrder = Transaksi::with('pembayaran')->where('user_id', $createdCustomer->id)->firstOrFail();
        $response->assertRedirect(route('kasir.transaksi.show', $walkInOrder->id_transaksi));
        $this->assertSame('dikonfirmasi', $walkInOrder->status_pesanan);
        $this->assertSame('lunas', $walkInOrder->pembayaran->status_pembayaran);
        $this->assertSame($kasir->id, $walkInOrder->pembayaran->kasir_id);
        $this->assertSame(1200000.0, (float) $walkInOrder->total_harga);
        $this->assertSame(3, $car->fresh()->versi);

        // Pelanggan terdaftar yang belum memiliki sim_number, tanpa langsung lunas
        $existingCustomer = User::factory()->create(['role' => 'pelanggan', 'sim_number' => null]);
        $secondResponse = $this->actingAs($kasir)->post(route('kasir.transaksi.store'), [
            'user_id' => $existingCustomer->id,
            'sim_number' => 'SIM-A-771122',
            'id_mobil' => $car->id_mobil,
            'start' => now()->addDays(10)->toDateString(),
            'end' => now()->addDays(12)->toDateString(),
            'waktu_ambil' => '10:00',
            'opsi_pengemudi' => 'Lepas kunci',
            'metode_pengambilan' => 'Ambil di pool MAZSEH',
            'titik_jemput' => 'Pool MAZSEH Klaten',
            'metode_pembayaran' => 'Transfer bank',
            'langsung_lunas' => 0,
        ]);

        $existingOrder = Transaksi::with('pembayaran')->where('user_id', $existingCustomer->id)->firstOrFail();
        $secondResponse->assertRedirect(route('kasir.transaksi.show', $existingOrder->id_transaksi));
        $this->assertSame('SIM-A-771122', $existingCustomer->fresh()->sim_number);
        $this->assertSame('menunggu_pembayaran', $existingOrder->status_pesanan);
        $this->assertSame('pending', $existingOrder->pembayaran->status_pembayaran);
    }

    /**
     * Kasir dapat menjalankan siklus serah-terima, pengembalian, pembatalan, dan refund.
     */
    public function test_kasir_dapat_menjalankan_handover_return_cancel_dan_process_refund(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        [, $orderLifecycle] = $this->createOrderWithPayment(statusPesanan: 'dikonfirmasi', statusPembayaran: 'lunas');
        [, $orderCancel] = $this->createOrderWithPayment(statusPesanan: 'dikonfirmasi', statusPembayaran: 'lunas');

        // Filter daftar transaksi
        $this->actingAs($kasir)->get(route('kasir.transaksi.index', [
            'status' => 'dikonfirmasi',
            'q' => $orderLifecycle->kode_booking,
        ]))
            ->assertOk()
            ->assertSee($orderLifecycle->kode_booking)
            ->assertDontSee($orderCancel->kode_booking);

        // Serah-terima unit -> sedang_disewa & mobil disewakan
        $this->actingAs($kasir)->post(route('kasir.transaksi.handover', $orderLifecycle->id_transaksi))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('sedang_disewa', $orderLifecycle->fresh()->status_pesanan);
        $this->assertSame('disewakan', $orderLifecycle->mobil->fresh()->status_ketersediaan);

        // Pengembalian unit -> selesai & mobil tersedia
        $this->actingAs($kasir)->post(route('kasir.transaksi.return', $orderLifecycle->id_transaksi))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('selesai', $orderLifecycle->fresh()->status_pesanan);
        $this->assertSame('tersedia', $orderLifecycle->mobil->fresh()->status_ketersediaan);

        // Pembatalan wajib menyertakan alasan
        $this->actingAs($kasir)->post(route('kasir.transaksi.cancel', $orderCancel->id_transaksi), [])
            ->assertSessionHasErrors('alasan_batal');

        // Pembatalan pesanan lunas memicu status refund menunggu_proses
        $this->actingAs($kasir)->post(route('kasir.transaksi.cancel', $orderCancel->id_transaksi), [
            'alasan_batal' => 'Pelanggan membatalkan perjalanan mendadak.',
        ])->assertRedirect()->assertSessionHas('success');

        $orderCancel->refresh();
        $pembayaranRefund = $orderCancel->pembayaran->fresh();
        $this->assertSame('dibatalkan', $orderCancel->status_pesanan);
        $this->assertSame('menunggu_proses', $pembayaranRefund->status_refund);

        // Daftar refund menampilkan transaksi menunggu_proses
        $this->actingAs($kasir)->get(route('kasir.refund.index', ['status_refund' => 'menunggu_proses']))
            ->assertOk()
            ->assertSee($orderCancel->kode_booking);

        // Nominal refund tidak valid ditolak
        $this->actingAs($kasir)->post(route('kasir.refund.process', $pembayaranRefund->id_pembayaran), [
            'jumlah_refund' => 0,
        ])->assertSessionHasErrors('jumlah_refund');

        // Pencairan refund berhasil
        $this->actingAs($kasir)->post(route('kasir.refund.process', $pembayaranRefund->id_pembayaran), [
            'jumlah_refund' => 500000,
            'catatan' => 'Transfer refund ke rekening BCA pelanggan',
        ])->assertRedirect()->assertSessionHas('success');

        $pembayaranRefund->refresh();
        $this->assertSame('selesai', $pembayaranRefund->status_refund);
        $this->assertSame(500000.0, (float) $pembayaranRefund->jumlah_refund);
        $this->assertSame($kasir->id, $pembayaranRefund->kasir_id);
        $this->assertSame('Transfer refund ke rekening BCA pelanggan', $pembayaranRefund->catatan_kasir);
    }

    /**
     * Kasir dapat memfilter dan memverifikasi dokumen KTP & SIM pelanggan serta mencatat log aktivitas.
     */
    public function test_kasir_dapat_memverifikasi_dokumen_pelanggan_dan_mencatat_audit_log(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $customer = User::factory()->create([
            'role' => 'pelanggan',
            'name' => 'Dina Verifikasi '.Str::upper(Str::random(4)),
            'sim_number' => 'SIM-DINA-'.Str::upper(Str::random(5)),
        ]);

        $dokumen = DocumentPelanggan::create([
            'user_id' => $customer->id,
            'file_ktp' => 'documents/ktp-dina.png',
            'file_sim' => 'documents/sim-dina.png',
            'status_verifikasi' => 'menunggu_validasi',
        ]);

        $this->actingAs($kasir)->get(route('kasir.dokumen.index', [
            'status' => 'menunggu_validasi',
            'q' => $customer->sim_number,
        ]))
            ->assertOk()
            ->assertSee($customer->name);

        // Penolakan wajib disertai catatan_verifikasi
        $this->actingAs($kasir)->post(route('kasir.dokumen.verify', $dokumen->id_dokumen), [
            'status_verifikasi' => 'ditolak',
            'catatan_verifikasi' => '',
        ])->assertSessionHasErrors('catatan_verifikasi');

        // Tolak dengan catatan
        $this->actingAs($kasir)->post(route('kasir.dokumen.verify', $dokumen->id_dokumen), [
            'status_verifikasi' => 'ditolak',
            'catatan_verifikasi' => 'Foto KTP terpotong.',
        ])->assertRedirect()->assertSessionHas('success');

        $dokumen->refresh();
        $this->assertSame('ditolak', $dokumen->status_verifikasi);
        $this->assertSame('Foto KTP terpotong.', $dokumen->catatan_verifikasi);

        // Setujui dokumen
        $this->actingAs($kasir)->post(route('kasir.dokumen.verify', $dokumen->id_dokumen), [
            'status_verifikasi' => 'disetujui',
            'catatan_verifikasi' => 'Dokumen valid dan jelas.',
        ])->assertRedirect()->assertSessionHas('success');

        $dokumen->refresh();
        $this->assertSame('disetujui', $dokumen->status_verifikasi);
        $this->assertSame('Dokumen valid dan jelas.', $dokumen->catatan_verifikasi);

        $this->assertTrue(
            LogActivity::where('user_id', $kasir->id)
                ->where('tabel_terdampak', 'document_pelanggan')
                ->where('jenis_aksi', 'UPDATE')
                ->where('keterangan', "Verifikasi dokumen pelanggan {$customer->name}: disetujui")
                ->exists()
        );
    }

    /**
     * Buat unit armada uji beserta kategorinya.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createCar(array $overrides = []): KatalogMobil
    {
        $category = KategoriMobil::create(['nama_kategori' => 'Uji Operasional '.Str::random(4)]);
        $suffix = Str::upper(Str::random(5));

        return KatalogMobil::create(array_merge([
            'id_kategori' => $category->id_kategori,
            'plat_nomor' => 'AD '.$suffix,
            'merk_model' => 'Mobil Kasir '.$suffix,
            'slug' => 'mobil-kasir-'.Str::lower($suffix),
            'harga_sewa' => 350000,
            'status_ketersediaan' => 'tersedia',
            'versi' => 0,
        ], $overrides));
    }

    /**
     * Buat pelanggan, unit armada, transaksi, dan pembayaran awal untuk pengujian.
     *
     * @return array{0: User, 1: Transaksi}
     */
    private function createOrderWithPayment(
        string $statusPesanan = 'menunggu_pembayaran',
        string $statusPembayaran = 'pending'
    ): array {
        $customer = User::factory()->create(['role' => 'pelanggan', 'phone' => '081200001111']);
        $car = $this->createCar();
        $suffix = Str::upper(Str::random(5));

        $order = Transaksi::create([
            'user_id' => $customer->id,
            'id_mobil' => $car->id_mobil,
            'kode_booking' => 'MZS-K'.$suffix,
            'tanggal_mulai' => now()->addDays(3)->toDateString(),
            'tanggal_selesai' => now()->addDays(5)->toDateString(),
            'waktu_ambil' => '09:00',
            'metode_pengambilan' => 'Ambil di pool MAZSEH',
            'opsi_pengemudi' => 'Lepas kunci',
            'total_harga' => 700000,
            'status_pesanan' => $statusPesanan,
            'titik_jemput' => 'Pool MAZSEH Klaten',
            'waktu_pesan' => now(),
        ]);

        Pembayaran::create([
            'id_transaksi' => $order->id_transaksi,
            'jumlah_bayar' => 700000,
            'status_pembayaran' => $statusPembayaran,
            'metode_pembayaran' => 'Transfer bank',
            'referensi_pembayaran' => 'REF-'.$suffix,
            'waktu_bayar' => now()->subMinutes(15),
        ]);

        return [$customer, $order->load(['pembayaran', 'mobil', 'user'])];
    }
}
