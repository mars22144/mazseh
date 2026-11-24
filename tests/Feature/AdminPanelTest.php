<?php

namespace Tests\Feature;

use App\Models\DocumentPelanggan;
use App\Models\KatalogMobil;
use App\Models\KategoriMobil;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Hanya admin yang boleh membuka panel admin; kasir & pelanggan ditolak.
     */
    public function test_only_admin_can_open_admin_panel(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        foreach (['pelanggan', 'kasir'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.dashboard'))
                ->assertForbidden();
        }

        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['admin.dashboard', 'admin.cars.index', 'admin.cars.create', 'admin.users.index', 'kasir.dashboard'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    /**
     * Login sebagai admin langsung menuju panel admin.
     */
    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-'.Str::lower(Str::random(6)).'@mazseh.test']);

        $this->post(route('login.post'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    /**
     * Admin dapat menambah lalu mengubah unit armada.
     */
    public function test_admin_can_create_and_update_car(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = KategoriMobil::create(['nama_kategori' => 'Uji Admin']);
        $plate = 'AD '.Str::upper(Str::random(5));
        $payload = [
            'merk_model' => 'Daihatsu Uji',
            'plat_nomor' => $plate,
            'id_kategori' => $category->id_kategori,
            'harga_sewa' => 300000,
            'status_ketersediaan' => 'tersedia',
            'tahun' => 2025,
            'transmisi' => 'Manual',
            'jumlah_kursi' => 7,
            'bahan_bakar' => 'Bensin',
            'kota' => 'Klaten',
            'fitur' => 'AC kabin, Audio & USB',
        ];

        $this->actingAs($admin)->post(route('admin.cars.store'), $payload)->assertRedirect(route('admin.cars.index'));

        $car = KatalogMobil::where('plat_nomor', $plate)->firstOrFail();
        $this->assertSame(['AC kabin', 'Audio & USB'], $car->fitur);
        $this->assertNotEmpty($car->slug);

        $this->actingAs($admin)->put(route('admin.cars.update', $car->id_mobil), [...$payload, 'harga_sewa' => 325000, 'status_ketersediaan' => 'maintenance'])
            ->assertRedirect(route('admin.cars.index'));

        $car->refresh();
        $this->assertSame(325000.0, (float) $car->harga_sewa);
        $this->assertSame('maintenance', $car->status_ketersediaan);

        $this->actingAs($admin)->post(route('admin.cars.store'), $payload)->assertSessionHasErrors('plat_nomor');
    }

    /**
     * Admin dapat mengubah peran pengguna lain, tetapi tidak perannya sendiri.
     */
    public function test_admin_can_change_roles_except_own(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'pelanggan']);

        $this->actingAs($admin)->patch(route('admin.users.role', $customer->id), ['role' => 'kasir']);
        $this->assertSame('kasir', $customer->fresh()->role);

        $this->actingAs($admin)->patch(route('admin.users.role', $admin->id), ['role' => 'pelanggan'])->assertSessionHas('error');
        $this->assertSame('admin', $admin->fresh()->role);
    }

    /**
     * Admin dapat menyetujui dokumen pelanggan yang menunggu validasi.
     */
    public function test_admin_can_verify_customer_document(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'pelanggan']);
        DocumentPelanggan::create(['user_id' => $customer->id, 'file_ktp' => '/storage/documents/ktp.jpg', 'file_sim' => '/storage/documents/sim.jpg', 'status_verifikasi' => 'menunggu_validasi']);

        $this->actingAs($admin)->get(route('admin.users.index', ['dokumen' => 1]))->assertOk()->assertSee($customer->email);

        $this->actingAs($admin)->patch(route('admin.users.document', $customer->id), ['status' => 'disetujui']);
        $this->assertSame('disetujui', $customer->dokumenPelanggan()->first()->status_verifikasi);
    }
}
