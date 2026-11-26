<?php

namespace Tests\Feature;

use App\Models\KatalogMobil;
use App\Models\KategoriMobil;
use App\Models\Pembayaran;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class KasirPanelTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Tamu diarahkan ke halaman login saat membuka panel kasir.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('kasir.dashboard'))->assertRedirect(route('login'));
    }

    /**
     * Pelanggan tidak boleh membuka panel kasir.
     */
    public function test_customer_cannot_open_kasir_panel(): void
    {
        $customer = User::factory()->create(['role' => 'pelanggan']);

        $this->actingAs($customer)->get(route('kasir.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('kasir.payments.index'))->assertForbidden();
    }

    /**
     * Kasir dan admin dapat membuka ringkasan panel kasir.
     */
    public function test_kasir_and_admin_can_open_dashboard(): void
    {
        foreach (['kasir', 'admin'] as $role) {
            $staff = User::factory()->create(['role' => $role]);

            $this->actingAs($staff)->get(route('kasir.dashboard'))
                ->assertOk()
                ->assertSee('Antrean verifikasi')
                ->assertSee('Kas masuk 7 hari');
        }
    }

    /**
     * Login sebagai kasir langsung menuju panel kasir.
     */
    public function test_kasir_login_redirects_to_kasir_dashboard(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir', 'email' => 'kasir-'.Str::lower(Str::random(6)).'@mazseh.test']);

        $this->post(route('login.post'), ['email' => $kasir->email, 'password' => 'password'])
            ->assertRedirect(route('kasir.dashboard'));
    }

    /**
     * Konfirmasi pembayaran pelanggan masuk antrean dan pesanan belum aktif sebelum diverifikasi.
     */
    public function test_customer_payment_waits_for_kasir_verification(): void
    {
        [$customer, $order] = $this->createPendingOrder();

        $this->actingAs($customer)->post(route('orders.payment.submit', $order->id_transaksi), [
            'metode_pembayaran' => 'Tunai di kasir',
            'referensi_pembayaran' => '',
        ])->assertRedirect(route('orders.confirmed', $order->id_transaksi));

        $order->refresh();
        $this->assertSame('menunggu_pembayaran', $order->status_pesanan);
        $this->assertTrue($order->pembayaran->isAwaitingVerification());

        $this->actingAs($customer)->get(route('orders.confirmed', $order->id_transaksi))
            ->assertOk()
            ->assertSee('Konfirmasi Pembayaran Terkirim!');

        $this->actingAs($customer)->get(route('orders.receipt', $order->id_transaksi))
            ->assertRedirect(route('orders.show', $order->id_transaksi));

        $kasir = User::factory()->create(['role' => 'kasir']);
        $this->actingAs($kasir)->get(route('kasir.payments.index', ['q' => $order->kode_booking]))
            ->assertOk()
            ->assertSee($order->kode_booking)
            ->assertSee('Menunggu Verifikasi');
    }

    /**
     * Kasir menerima pembayaran: status lunas, kasir tercatat, pesanan dikonfirmasi.
     */
    public function test_kasir_can_approve_payment(): void
    {
        [$customer, $order] = $this->createPendingOrder(submitted: true);
        $kasir = User::factory()->create(['role' => 'kasir']);
        $payment = $order->pembayaran;

        $this->actingAs($kasir)->get(route('kasir.payments.show', $payment->id_pembayaran))
            ->assertOk()
            ->assertSee('Terima pembayaran');

        $this->actingAs($kasir)->post(route('kasir.payments.approve', $payment->id_pembayaran), [
            'metode_pembayaran' => 'Transfer bank',
            'referensi_pembayaran' => 'BCA-778899',
        ])->assertRedirect(route('kasir.payments.show', $payment->id_pembayaran));

        $payment->refresh();
        $order->refresh();
        $this->assertSame('lunas', $payment->status_pembayaran);
        $this->assertSame($kasir->id, $payment->kasir_id);
        $this->assertNotNull($payment->waktu_verifikasi);
        $this->assertSame('BCA-778899', $payment->referensi_pembayaran);
        $this->assertSame('dikonfirmasi', $order->status_pesanan);

        $this->actingAs($customer)->get(route('orders.receipt', $order->id_transaksi))
            ->assertOk()
            ->assertSee('Kuitansi Resmi Pembayaran');
    }

    /**
     * Kasir menolak bukti bayar dengan alasan, lalu pelanggan dapat mengirim ulang.
     */
    public function test_kasir_can_reject_payment_and_customer_can_resubmit(): void
    {
        [$customer, $order] = $this->createPendingOrder(submitted: true);
        $kasir = User::factory()->create(['role' => 'kasir']);
        $payment = $order->pembayaran;

        $this->actingAs($kasir)->post(route('kasir.payments.reject', $payment->id_pembayaran), [])
            ->assertSessionHasErrors('catatan_kasir');

        $this->actingAs($kasir)->post(route('kasir.payments.reject', $payment->id_pembayaran), [
            'catatan_kasir' => 'Nominal transfer tidak sesuai tagihan.',
        ])->assertRedirect(route('kasir.payments.show', $payment->id_pembayaran));

        $payment->refresh();
        $this->assertSame('gagal', $payment->status_pembayaran);
        $this->assertSame('menunggu_pembayaran', $order->fresh()->status_pesanan);

        $this->actingAs($kasir)->get(route('kasir.payments.show', $payment->id_pembayaran))
            ->assertOk()
            ->assertDontSee('Terima pembayaran')
            ->assertSee('Pembayaran telah ditolak.');

        $this->actingAs($kasir)->post(route('kasir.payments.approve', $payment->id_pembayaran), [
            'metode_pembayaran' => 'Transfer bank',
        ])->assertSessionHas('error');
        $this->assertSame('gagal', $payment->fresh()->status_pembayaran);

        $this->actingAs($customer)->get(route('orders.payment', $order->id_transaksi))
            ->assertOk()
            ->assertSee('Nominal transfer tidak sesuai tagihan.');

        $this->actingAs($customer)->post(route('orders.payment.submit', $order->id_transaksi), [
            'metode_pembayaran' => 'QRIS',
        ]);

        $payment->refresh();
        $this->assertTrue($payment->isAwaitingVerification());
        $this->assertNull($payment->catatan_kasir);
    }

    /**
     * Pembayaran untuk pesanan yang dibatalkan tidak dapat diterima kasir.
     */
    public function test_kasir_cannot_approve_cancelled_order(): void
    {
        [, $order] = $this->createPendingOrder(submitted: true);
        $order->update(['status_pesanan' => 'dibatalkan', 'alasan_batal' => 'Batal jadwal.']);
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($kasir)->post(route('kasir.payments.approve', $order->pembayaran->id_pembayaran), [
            'metode_pembayaran' => 'Tunai di kasir',
        ])->assertSessionHas('error');

        $this->assertSame('pending', $order->pembayaran->fresh()->status_pembayaran);
    }

    /**
     * Detail pesanan pelanggan tidak menghitung pembayaran pending sebagai sudah dibayar.
     */
    public function test_order_detail_shows_unpaid_amount_for_pending_payment(): void
    {
        [$customer, $order] = $this->createPendingOrder();

        $this->actingAs($customer)->get(route('orders.show', $order->id_transaksi))
            ->assertOk()
            ->assertSee('Pembayaran (Belum Dibayar)')
            ->assertSeeInOrder(['Telah Dibayar', 'Rp0', 'Sisa Tagihan', 'Rp700.000']);
    }

    /**
     * Buat pelanggan, unit, dan pesanan menunggu pembayaran.
     *
     * @return array{0: User, 1: Transaksi}
     */
    private function createPendingOrder(bool $submitted = false): array
    {
        $customer = User::factory()->create(['role' => 'pelanggan', 'phone' => '081200000000']);
        $category = KategoriMobil::create(['nama_kategori' => 'Uji Kasir']);
        $suffix = Str::upper(Str::random(5));
        $car = KatalogMobil::create([
            'id_kategori' => $category->id_kategori,
            'plat_nomor' => 'T '.$suffix,
            'merk_model' => 'Mobil Uji '.$suffix,
            'slug' => 'mobil-uji-'.Str::lower($suffix),
            'harga_sewa' => 350000,
            'status_ketersediaan' => 'tersedia',
        ]);

        $order = Transaksi::create([
            'user_id' => $customer->id,
            'id_mobil' => $car->id_mobil,
            'kode_booking' => 'MZS-T'.$suffix,
            'tanggal_mulai' => now()->addDays(5)->toDateString(),
            'tanggal_selesai' => now()->addDays(7)->toDateString(),
            'total_harga' => 700000,
            'status_pesanan' => 'menunggu_pembayaran',
            'titik_jemput' => 'Pool MAZSEH Klaten',
            'waktu_pesan' => now(),
        ]);

        Pembayaran::create([
            'id_transaksi' => $order->id_transaksi,
            'jumlah_bayar' => 700000,
            'status_pembayaran' => 'pending',
            'metode_pembayaran' => 'Transfer bank',
            'referensi_pembayaran' => $submitted ? 'TRX-'.$suffix : null,
            'waktu_bayar' => $submitted ? now()->subMinutes(10) : null,
        ]);

        return [$customer, $order->load('pembayaran')];
    }
}
