<x-layouts.staff title="Transaksi Walk-in" heading="Buat Transaksi Walk-in">
    <nav class="breadcrumbs" aria-label="Jejak halaman">
        <a href="{{ route('kasir.transaksi.index') }}">Transaksi</a>
        <x-icon name="chevron" />
        <span>Buat Transaksi Walk-in</span>
    </nav>

    <div class="page-title">
        <div>
            <h1>Buat Transaksi Walk-in</h1>
            <p>Catat penyewaan langsung di loket untuk pelanggan terdaftar maupun pelanggan baru.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="notice error error-summary" role="alert">
            <x-icon name="info" />
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form action="{{ route('kasir.transaksi.store') }}" method="POST" enctype="multipart/form-data" class="two-col">
        @csrf
        <input type="hidden" id="versi" name="versi" value="{{ old('versi') }}">

        <div>
            <section class="panel">
                <div class="panel-title">
                    <div>
                        <h2>Data Pelanggan</h2>
                        <p>Pilih pelanggan terdaftar atau kosongkan untuk mendaftarkan pelanggan baru secara otomatis.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field full">
                        <label for="user_id">Pelanggan Terdaftar (Opsional)</label>
                        <select id="user_id" name="user_id">
                            <option value="">-- Buat Pelanggan Baru --</option>
                            @foreach($customers as $customer)
                                @php($doc = $customer->dokumenPelanggan)
                                <option value="{{ $customer->id }}"
                                        data-sim="{{ $customer->sim_number ?? '' }}"
                                        data-doc-status="{{ $doc?->status_verifikasi ?? 'belum_upload' }}"
                                        data-doc-label="{{ $doc?->status_label ?? 'Belum Upload' }}"
                                        @selected((string) old('user_id') === (string) $customer->id)>
                                    {{ $customer->name }} ({{ $customer->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="customer_name">Nama Pelanggan Baru</label>
                        <input id="customer_name" name="customer_name" type="text" maxlength="100" value="{{ old('customer_name') }}" placeholder="Wajib jika pelanggan baru">
                        @error('customer_name')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="customer_email">Email Pelanggan Baru</label>
                        <input id="customer_email" name="customer_email" type="email" maxlength="255" value="{{ old('customer_email') }}" placeholder="pelanggan@contoh.com">
                        @error('customer_email')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="customer_phone">Nomor Telepon / WA</label>
                        <input id="customer_phone" name="customer_phone" type="tel" maxlength="30" value="{{ old('customer_phone') }}" placeholder="0812xxxxxxxx">
                        @error('customer_phone')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="sim_number">Nomor SIM A *</label>
                        <input id="sim_number" name="sim_number" type="text" maxlength="50" required value="{{ old('sim_number') }}" placeholder="SIM-A-xxxxxxxx">
                        @error('sim_number')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <hr class="rule">

                <div style="margin-bottom:16px;">
                    <strong class="small" style="display:block;">Dokumen Identitas KTP &amp; SIM (Opsional)</strong>
                    <p class="tiny muted" style="margin-top:4px;">Unggah foto KTP dan SIM A agar arsip dokumen pelanggan tercatat aman di sistem.</p>
                </div>

                <div id="customer-doc-notice" class="notice" style="margin-bottom:16px;" hidden>
                    <x-icon name="shield" />
                    <span id="customer-doc-notice-text"></span>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="file_ktp">Foto KTP Asli</label>
                        <input id="file_ktp" name="file_ktp" type="file" accept="image/*">
                        <span class="hint">JPG, PNG, atau WebP (maks 3MB).</span>
                        @error('file_ktp')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="file_sim">Foto SIM A Asli</label>
                        <input id="file_sim" name="file_sim" type="file" accept="image/*">
                        <span class="hint">JPG, PNG, atau WebP (maks 3MB).</span>
                        @error('file_sim')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <label class="check-label" style="margin-bottom:0;">
                    <input type="checkbox" name="verifikasi_dokumen" value="1" @checked(old('verifikasi_dokumen', true))>
                    <span>KTP &amp; SIM fisik sudah diperiksa di loket &mdash; langsung tandai <strong>Disetujui</strong> bila kedua foto lengkap</span>
                </label>
            </section>

            <section class="panel">
                <div class="panel-title">
                    <div>
                        <h2>Jadwal &amp; Armada</h2>
                        <p>Tentukan unit armada yang tersedia, tanggal sewa, dan lokasi serah-terima.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field full">
                        <label for="id_mobil">Pilih Armada *</label>
                        <select id="id_mobil" name="id_mobil" required>
                            <option value="">-- Pilih Unit Tersedia --</option>
                            @foreach($cars as $car)
                                <option value="{{ $car->id_mobil }}"
                                        data-versi="{{ $car->versi }}"
                                        @selected((string) old('id_mobil') === (string) $car->id_mobil)>
                                    {{ $car->merk_model }} ({{ $car->plat_nomor }}) &mdash; {{ $car->formatted_price }}/hari
                                </option>
                            @endforeach
                        </select>
                        @error('id_mobil')<p class="form-error">{{ $message }}</p>@enderror
                        @error('mobil')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="start">Tanggal Mulai *</label>
                        <input id="start" name="start" type="date" min="{{ now()->format('Y-m-d') }}" required value="{{ old('start', now()->format('Y-m-d')) }}">
                        @error('start')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="end">Tanggal Selesai *</label>
                        <input id="end" name="end" type="date" min="{{ now()->addDay()->format('Y-m-d') }}" required value="{{ old('end', now()->addDays(2)->format('Y-m-d')) }}">
                        @error('end')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="waktu_ambil">Jam Ambil *</label>
                        <input id="waktu_ambil" name="waktu_ambil" type="time" required value="{{ old('waktu_ambil', '09:00') }}">
                        @error('waktu_ambil')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="opsi_pengemudi">Opsi Pengemudi *</label>
                        <select id="opsi_pengemudi" name="opsi_pengemudi" required>
                            <option value="Lepas kunci" @selected(old('opsi_pengemudi', 'Lepas kunci') === 'Lepas kunci')>Lepas kunci</option>
                            <option value="Dengan sopir" @selected(old('opsi_pengemudi') === 'Dengan sopir')>Dengan sopir (+Rp200.000/hari)</option>
                        </select>
                        @error('opsi_pengemudi')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field full">
                        <label for="metode_pengambilan">Metode Pengambilan *</label>
                        <select id="metode_pengambilan" name="metode_pengambilan" required>
                            <option value="Ambil di pool MAZSEH" @selected(old('metode_pengambilan', 'Ambil di pool MAZSEH') === 'Ambil di pool MAZSEH')>Ambil di pool MAZSEH</option>
                            <option value="Antar ke lokasi saya" @selected(old('metode_pengambilan') === 'Antar ke lokasi saya')>Antar ke lokasi pelanggan</option>
                        </select>
                        @error('metode_pengambilan')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field full">
                        <label for="titik_jemput">Titik Jemput / Lokasi Serah-Terima *</label>
                        <textarea id="titik_jemput" name="titik_jemput" rows="2" maxlength="500" required>{{ old('titik_jemput', 'Pool MAZSEH Klaten') }}</textarea>
                        @error('titik_jemput')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field full">
                        <label for="catatan">Catatan Tambahan</label>
                        <textarea id="catatan" name="catatan" rows="2" maxlength="500" placeholder="Opsional">{{ old('catatan') }}</textarea>
                        @error('catatan')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>
        </div>

        <aside class="panel sticky-panel">
            <h3>Pembayaran Loket</h3>
            <div class="field spaced">
                <label for="metode_pembayaran">Metode Pembayaran *</label>
                <select id="metode_pembayaran" name="metode_pembayaran" required>
                    <option value="Tunai di kasir" @selected(old('metode_pembayaran', 'Tunai di kasir') === 'Tunai di kasir')>Tunai di kasir</option>
                    <option value="QRIS" @selected(old('metode_pembayaran') === 'QRIS')>QRIS</option>
                    <option value="Transfer bank" @selected(old('metode_pembayaran') === 'Transfer bank')>Transfer bank</option>
                </select>
                @error('metode_pembayaran')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="referensi_pembayaran">Nomor Referensi / Struk</label>
                <input id="referensi_pembayaran" name="referensi_pembayaran" type="text" maxlength="100" value="{{ old('referensi_pembayaran') }}" placeholder="Opsional">
                @error('referensi_pembayaran')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <label class="check-label">
                <input type="checkbox" name="langsung_lunas" value="1" @checked(old('langsung_lunas', true))>
                <span>Tandai pembayaran langsung <strong>Lunas</strong> &amp; konfirmasi pesanan sekarang</span>
            </label>

            <button type="submit" class="btn btn-primary wfull">
                <x-icon name="check" />
                <span>Simpan Transaksi Walk-in</span>
            </button>
            <a href="{{ route('kasir.transaksi.index') }}" class="btn btn-ghost wfull" style="margin-top:8px;">Batal</a>
        </aside>
    </form>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const carSelect = document.getElementById('id_mobil');
            const versiInput = document.getElementById('versi');
            const customerSelect = document.getElementById('user_id');
            const simInput = document.getElementById('sim_number');
            const docNotice = document.getElementById('customer-doc-notice');
            const docNoticeText = document.getElementById('customer-doc-notice-text');

            function syncVersion() {
                const selected = carSelect?.options[carSelect.selectedIndex];
                if (versiInput && selected) {
                    versiInput.value = selected.dataset.versi ?? '';
                }
            }

            function syncCustomerDocNotice() {
                if (!customerSelect || !docNotice || !docNoticeText) return;
                const selected = customerSelect.options[customerSelect.selectedIndex];

                if (!customerSelect.value || !selected) {
                    docNotice.hidden = true;
                    return;
                }

                const status = selected.dataset.docStatus || 'belum_upload';
                const label = selected.dataset.docLabel || 'Belum Upload';

                docNotice.className = 'notice ' + (status === 'disetujui' ? 'success' : 'warning');
                docNoticeText.textContent = status === 'disetujui'
                    ? 'Dokumen KTP & SIM pelanggan ini sudah Disetujui. Kosongkan unggahan di bawah kecuali ingin memperbarui.'
                    : 'Status dokumen KTP & SIM pelanggan ini: ' + label + '. Disarankan mengunggah foto KTP & SIM di loket.';
                docNotice.hidden = false;
            }

            carSelect?.addEventListener('change', syncVersion);
            syncVersion();

            customerSelect?.addEventListener('change', function () {
                const selected = customerSelect.options[customerSelect.selectedIndex];
                if (simInput && selected?.dataset.sim && !simInput.value) {
                    simInput.value = selected.dataset.sim;
                }
                syncCustomerDocNotice();
            });
            syncCustomerDocNotice();
        });
    </script>
    @endpush
</x-layouts.staff>
