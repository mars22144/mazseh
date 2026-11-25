<x-layouts.staff :title="'Transaksi ' . $order->kode_booking" heading="Detail Transaksi">
    @php
        $car = $order->mobil;
        $customer = $order->user;
        $payment = $order->pembayaran;
        $document = $customer?->dokumenPelanggan;
        $canHandover = $order->status_pesanan === 'dikonfirmasi' && $payment?->isPaid();
        $canReturn = $order->status_pesanan === 'sedang_disewa';
        $canCancel = ! in_array($order->status_pesanan, ['selesai', 'dibatalkan'], true);
    @endphp

    <nav class="breadcrumbs" aria-label="Jejak halaman">
        <a href="{{ route('kasir.transaksi.index') }}">Transaksi</a>
        <x-icon name="chevron" />
        <span>{{ $order->kode_booking }}</span>
    </nav>

    <div class="page-title">
        <div>
            <h1>Transaksi {{ $order->kode_booking }}</h1>
            <p>{{ $customer->name ?? '-' }} &bull; {{ $car->merk_model ?? '-' }} &bull; {{ $order->tanggal_mulai->translatedFormat('d M Y') }} &ndash; {{ $order->tanggal_selesai->translatedFormat('d M Y') }}</p>
        </div>
        <div class="inline-actions">
            <span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
            @if($payment)
                <span class="badge {{ $payment->status_badge_class }}">Bayar: {{ $payment->status_label }}</span>
            @endif
        </div>
    </div>

    @if($errors->any())
        <div class="notice error error-summary" role="alert">
            <x-icon name="info" />
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    @if($credentials = session('walkin_credentials'))
        <div class="notice warning" style="margin-bottom:24px; align-items:flex-start;" role="status">
            <x-icon name="key" />
            <div class="grow">
                <strong style="display:block; margin-bottom:4px;">Akun Pelanggan Baru Dibuat (Hanya Ditampilkan Sekali)</strong>
                <p class="small" style="color:inherit; margin-bottom:8px;">
                    Catat dan sampaikan kredensial login berikut kepada <strong>{{ $credentials['name'] }}</strong>, lalu sarankan untuk segera mengganti kata sandi setelah masuk.
                </p>
                <div class="inline-actions" style="gap:12px;">
                    <span>Email: <strong>{{ $credentials['email'] }}</strong></span>
                    <span>Password sementara: <code id="walkin-temp-password" style="padding:2px 8px; border-radius:4px; background:rgba(15,23,42,.08); font-weight:700; letter-spacing:.03em;">{{ $credentials['password'] }}</code></span>
                    <button type="button"
                            class="btn btn-outline btn-small"
                            id="btn-copy-walkin-credentials"
                            data-email="{{ $credentials['email'] }}"
                            data-password="{{ $credentials['password'] }}">
                        Salin kredensial
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="two-col">
        <div>
            <section class="panel">
                <div class="panel-title">
                    <h2>Armada &amp; Jadwal Sewa</h2>
                    <span class="badge">{{ $order->duration_days }} hari</span>
                </div>

                @if($car)
                    <div class="mini-car">
                        <img src="{{ asset($car->foto_mobil ?? 'assets/avanza.png') }}" width="600" height="400" alt="{{ $car->merk_model }}">
                        <div>
                            <h3>{{ $car->merk_model }}</h3>
                            <p>{{ $car->plat_nomor }} &bull; {{ $car->kategori->nama_kategori ?? 'Armada' }} &bull; {{ $order->opsi_pengemudi }}</p>
                        </div>
                    </div>
                @endif

                <div class="summary-line">
                    <span>Tanggal Mulai</span>
                    <strong>{{ $order->tanggal_mulai->translatedFormat('d M Y') }}, {{ $order->waktu_ambil }} WIB</strong>
                </div>
                <div class="summary-line">
                    <span>Tanggal Selesai</span>
                    <strong>{{ $order->tanggal_selesai->translatedFormat('d M Y') }}</strong>
                </div>
                <div class="summary-line">
                    <span>Metode Pengambilan</span>
                    <strong>{{ $order->metode_pengambilan }}</strong>
                </div>
                <div class="summary-line">
                    <span>Titik Jemput</span>
                    <strong>{{ $order->titik_jemput ?? '-' }}</strong>
                </div>
                @if($order->catatan)
                    <div class="summary-line">
                        <span>Catatan</span>
                        <strong>{{ $order->catatan }}</strong>
                    </div>
                @endif
                @if($order->alasan_batal)
                    <div class="notice error spaced">
                        <x-icon name="info" />
                        <span><strong>Alasan Pembatalan:</strong> {{ $order->alasan_batal }}</span>
                    </div>
                @endif
            </section>

            <section class="panel">
                <div class="panel-title">
                    <h2>Data Penyewa &amp; Dokumen</h2>
                    @if($document)
                        <span class="badge {{ $document->status_badge_class }}">KTP/SIM: {{ $document->status_label }}</span>
                    @else
                        <span class="badge">KTP/SIM: Belum upload</span>
                    @endif
                </div>

                <div class="summary-line">
                    <span>Nama</span>
                    <strong>{{ $customer->name ?? '-' }}</strong>
                </div>
                <div class="summary-line">
                    <span>Email</span>
                    <strong>{{ $customer->email ?? '-' }}</strong>
                </div>
                <div class="summary-line">
                    <span>Telepon</span>
                    <strong>{{ $customer->phone ?? '-' }}</strong>
                </div>
                <div class="summary-line">
                    <span>Nomor SIM</span>
                    <strong>{{ $customer->sim_number ?? '-' }}</strong>
                </div>
                @if($document && ($document->file_ktp || $document->file_sim))
                    <div class="inline-actions spaced">
                        @if($document->file_ktp)
                            <a href="{{ route('files.document', [$customer->id, 'ktp']) }}" target="_blank" rel="noopener" class="btn btn-outline btn-small">Lihat KTP</a>
                        @endif
                        @if($document->file_sim)
                            <a href="{{ route('files.document', [$customer->id, 'sim']) }}" target="_blank" rel="noopener" class="btn btn-outline btn-small">Lihat SIM</a>
                        @endif
                    </div>
                @endif
            </section>
        </div>

        <aside class="panel sticky-panel">
            <h3>Ringkasan &amp; Aksi Operasional</h3>
            <div class="summary-line spaced">
                <span>Total Sewa</span>
                <strong>{{ $order->formatted_total }}</strong>
            </div>
            @if($payment)
                <div class="summary-line">
                    <span>Metode Bayar</span>
                    <strong>{{ $payment->metode_pembayaran }}</strong>
                </div>
                <div class="summary-line">
                    <span>Referensi</span>
                    <strong>{{ $payment->referensi_pembayaran ?? '-' }}</strong>
                </div>
                <div class="summary-line">
                    <span>Status Pembayaran</span>
                    <strong>{{ $payment->status_label }}</strong>
                </div>
                @if($payment->status_refund !== 'tidak_ada')
                    <div class="summary-line">
                        <span>Status Refund</span>
                        <strong>{{ ucfirst(str_replace('_', ' ', $payment->status_refund)) }}</strong>
                    </div>
                @endif
            @endif

            <hr class="rule">

            <div class="od-stack" style="--od-gap:10px;">
                @if($canHandover)
                    @if(! $document || $document->status_verifikasi !== 'disetujui')
                        <div class="notice warning" role="note">
                            <x-icon name="shield" />
                            <span>Dokumen KTP/SIM penyewa berstatus <strong>{{ $document?->status_label ?? 'Belum Upload' }}</strong>. Pastikan identitas fisik sudah diperiksa sebelum serah-terima unit.</span>
                        </div>
                    @endif
                    <form action="{{ route('kasir.transaksi.handover', $order->id_transaksi) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary wfull">
                            <x-icon name="key" />
                            <span>Serah-Terima Unit (Mulai Sewa)</span>
                        </button>
                    </form>
                @endif

                @if($canReturn)
                    <form action="{{ route('kasir.transaksi.return', $order->id_transaksi) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-blue wfull">
                            <x-icon name="check" />
                            <span>Catat Pengembalian Unit</span>
                        </button>
                    </form>
                @endif

                <a href="{{ route('kasir.transaksi.receipt', $order->id_transaksi) }}" class="btn btn-outline wfull">
                    <x-icon name="print" />
                    <span>Lihat / Cetak Kuitansi</span>
                </a>

                @if($payment && $payment->status_pembayaran === 'pending' && $order->status_pesanan === 'menunggu_pembayaran')
                    <form action="{{ route('kasir.pembayaran.verify', $payment->id_pembayaran) }}" method="POST" class="od-stack" style="--od-gap:8px; margin-top:8px;">
                        @csrf
                        <input type="text" name="catatan" maxlength="500" placeholder="Catatan verifikasi (opsional)">
                        <div class="inline-actions">
                            <button type="submit" name="keputusan" value="terima" class="btn btn-primary wfull">Verifikasi &amp; Terima Bayar</button>
                        </div>
                    </form>
                @endif

                @if($canCancel)
                    <hr class="rule">
                    <form action="{{ route('kasir.transaksi.cancel', $order->id_transaksi) }}" method="POST">
                        @csrf
                        <div class="field">
                            <label for="alasan_batal">Batalkan Pesanan</label>
                            <textarea id="alasan_batal" name="alasan_batal" rows="2" maxlength="500" required placeholder="Tuliskan alasan pembatalan...">{{ old('alasan_batal') }}</textarea>
                            @error('alasan_batal')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn btn-danger wfull">
                            <x-icon name="close" />
                            <span>Batalkan Pesanan</span>
                        </button>
                    </form>
                @endif

                <a href="{{ route('kasir.transaksi.index') }}" class="btn btn-ghost wfull">
                    <x-icon name="back" />
                    <span>Kembali ke Daftar Transaksi</span>
                </a>
            </div>
        </aside>
    </div>

    @if(session('walkin_credentials'))
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const copyBtn = document.getElementById('btn-copy-walkin-credentials');
                if (!copyBtn || !navigator.clipboard) return;
                copyBtn.addEventListener('click', function () {
                    const text = 'Email: ' + copyBtn.dataset.email + '\nPassword sementara: ' + copyBtn.dataset.password;
                    navigator.clipboard.writeText(text).then(function () {
                        copyBtn.textContent = 'Tersalin!';
                    });
                });
            });
        </script>
        @endpush
    @endif
</x-layouts.staff>
