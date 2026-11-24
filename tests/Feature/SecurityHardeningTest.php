<?php

namespace Tests\Feature;

use App\Models\LogActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Gambar PNG 1x1 valid agar lolos aturan `image` tanpa ekstensi GD.
     */
    private const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /**
     * Setiap respons web membawa header keamanan standar.
     */
    public function test_responses_include_security_headers(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
    }

    /**
     * Halaman untuk pengguna yang login tidak boleh disimpan cache.
     */
    public function test_authenticated_pages_are_not_cached(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $cacheControl = $this->actingAs($kasir)->get(route('kasir.dashboard'))->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
    }

    /**
     * Kredensial akun demo tidak ditampilkan di luar lingkungan lokal.
     */
    public function test_demo_credentials_hidden_outside_local_environment(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('mazseh123');
    }

    /**
     * Peran tidak dapat diisi lewat mass assignment.
     */
    public function test_role_is_not_mass_assignable(): void
    {
        $user = User::create(['name' => 'Penyusup', 'email' => 'penyusup-'.uniqid().'@mazseh.test', 'password' => 'Rahasia123', 'role' => 'admin']);

        $this->assertSame('pelanggan', $user->fresh()->role);
    }

    /**
     * Unggahan KTP/SIM disimpan di disk privat dan hanya bisa dibuka pemilik atau staf.
     */
    public function test_identity_documents_are_private(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'pelanggan', 'phone' => '0811']);
        $url = route('files.document', [$owner->id, 'ktp']);
        $this->get($url)->assertRedirect(route('login'));

        $this->actingAs($owner)->put(route('profile.update'), [
            'name' => $owner->name,
            'email' => $owner->email,
            'phone' => '0811',
            'file_ktp' => UploadedFile::fake()->createWithContent('ktp.png', base64_decode(self::TINY_PNG)),
        ])->assertRedirect();

        $path = $owner->dokumenPelanggan()->first()->file_ktp;
        $this->assertStringStartsNotWith('/storage/', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->actingAs(User::factory()->create(['role' => 'pelanggan']))->get($url)->assertForbidden();
        $this->actingAs($owner)->get($url)->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'kasir']))->get($url)->assertOk();
    }

    /**
     * Pelanggan tidak dapat membuka panel pengaturan staf.
     */
    public function test_customer_cannot_open_staff_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pelanggan']))
            ->get(route('staff.settings.edit'))
            ->assertForbidden();
    }

    /**
     * Staf wajib memasukkan kata sandi lama yang benar dan kata sandi baru yang kuat.
     */
    public function test_staff_password_change_requires_current_password_and_is_audited(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($kasir)->get(route('staff.settings.edit'))->assertOk()->assertSee('Pengaturan akun');

        $this->actingAs($kasir)->put(route('staff.settings.password'), [
            'current_password' => 'salah-total',
            'password' => 'KataSandiBaru1',
            'password_confirmation' => 'KataSandiBaru1',
        ])->assertSessionHasErrors('current_password', null, 'password');

        $this->actingAs($kasir)->put(route('staff.settings.password'), [
            'current_password' => 'password',
            'password' => 'lemah',
            'password_confirmation' => 'lemah',
        ])->assertSessionHasErrors('password', null, 'password');

        $this->actingAs($kasir)->put(route('staff.settings.password'), [
            'current_password' => 'password',
            'password' => 'KataSandiBaru1',
            'password_confirmation' => 'KataSandiBaru1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('KataSandiBaru1', $kasir->fresh()->password));
        $this->assertTrue(LogActivity::where('user_id', $kasir->id)->where('keterangan', 'like', 'Mengganti kata sandi%')->exists());
    }

    /**
     * Aksi admin yang sensitif tercatat di log aktivitas.
     */
    public function test_admin_role_change_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'pelanggan']);

        $this->actingAs($admin)->patch(route('admin.users.role', $customer->id), ['role' => 'kasir']);

        $log = LogActivity::where('user_id', $admin->id)->latest('id_log')->first();
        $this->assertNotNull($log);
        $this->assertSame('users', $log->tabel_terdampak);
        $this->assertStringContainsString($customer->email, $log->keterangan);
    }
}
