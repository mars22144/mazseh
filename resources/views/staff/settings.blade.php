<x-layouts.staff title="Pengaturan" heading="Pengaturan">
    <div class="page-title">
        <div>
            <h1>Pengaturan akun</h1>
            <p>Kelola profil, kata sandi, dan sesi login akun {{ strtolower($user->role_label) }} Anda.</p>
        </div>
    </div>

    <div class="two-col">
        <div>
            <section class="panel">
                <div class="panel-title"><h2>Profil</h2></div>
                <form action="{{ route('staff.settings.profile') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="form-grid">
                        <div class="field">
                            <label for="name">Nama lengkap *</label>
                            <input id="name" name="name" type="text" maxlength="80" required autocomplete="name" value="{{ old('name', $user->name) }}">
                            @error('name')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="phone">Nomor telepon</label>
                            <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="{{ old('phone', $user->phone) }}">
                        </div>
                        <div class="field full">
                            <label for="email">Email *</label>
                            <input id="email" name="email" type="email" maxlength="255" required autocomplete="email" value="{{ old('email', $user->email) }}">
                            @error('email')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="button-row"><button type="submit" class="btn btn-primary">Simpan profil</button></div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-title">
                    <div>
                        <h2>Kata sandi</h2>
                        <p>Minimal 8 karakter dengan huruf besar, huruf kecil, dan angka. Sesi di perangkat lain akan dikeluarkan.</p>
                    </div>
                </div>
                <form action="{{ route('staff.settings.password') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="current_password">Kata sandi saat ini *</label>
                        <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
                        @error('current_password', 'password')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-grid">
                        <div class="field">
                            <label for="password">Kata sandi baru *</label>
                            <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
                            @error('password', 'password')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="password_confirmation">Ulangi kata sandi baru *</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password">
                        </div>
                    </div>
                    <div class="button-row"><button type="submit" class="btn btn-dark"><x-icon name="key" /><span>Ganti kata sandi</span></button></div>
                </form>
            </section>
        </div>

        <aside>
            <section class="panel">
                <h3>Sesi login</h3>
                @if($otherSessionCount === null)
                    <p class="small spaced">Daftar sesi hanya tersedia bila sesi disimpan di database.</p>
                @else
                    <p class="small spaced">
                        Akun ini aktif di <strong>{{ $otherSessionCount }}</strong> perangkat lain selain perangkat ini.
                    </p>
                    <form action="{{ route('staff.settings.sessions') }}" method="POST" class="spaced">
                        @csrf
                        @method('DELETE')
                        <div class="field">
                            <label for="current_password_sessions">Konfirmasi kata sandi *</label>
                            <input id="current_password_sessions" name="current_password_sessions" type="password" required autocomplete="current-password">
                            @error('current_password_sessions', 'sessions')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn btn-outline wfull" @disabled($otherSessionCount === 0)>
                            <x-icon name="logout" />
                            <span>Keluarkan perangkat lain</span>
                        </button>
                    </form>
                @endif
            </section>

            <section class="panel">
                <h3>Aktivitas terakhir Anda</h3>
                @forelse($activities as $activity)
                    <div class="notification-item">
                        <strong>{{ $activity->keterangan }}</strong>
                        <span>{{ $activity->waktu_kejadian?->translatedFormat('d M Y, H:i') }} &bull; IP {{ $activity->ip_address ?? '-' }}</span>
                    </div>
                @empty
                    <p class="small spaced">Belum ada aktivitas tercatat.</p>
                @endforelse
            </section>
        </aside>
    </div>
</x-layouts.staff>
