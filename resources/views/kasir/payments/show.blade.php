<x-layouts.staff :title="'Pembayaran ' . $order->kode_booking" heading="Detail pembayaran">
    @php
        $car = $order->mobil;
        $customer = $order->user;
        $document = $customer->dokumenPelanggan;
        $isOrderOpen = $order->status_pesanan === 'menunggu_pembayaran';
        $canApprove = $isOrderOpen && ! $payment->isPaid();
        $canReject = $isOrderOpen && $payment->isAwaitingVerification();
        $driverTotal = $order->opsi_pengemudi === 'Dengan sopir' ? 200000 * $order->duration_days : 0;
        $rentalTotal = max(0, (float) $order->total_harga - $driverTotal);
    @endphp

    <nav class="breadcrumbs" aria-label="Jejak halaman">
        <a href="{{ route('kasir.payments.index') }}">Pembayaran</a>
        <x-icon name="chevron" />
        <span>{{ $order->kode_booking }}</span>
    </nav>

    <div class="page-title">
        <div>
            <h1>Pembayaran {{ $order->kode_booking }}</h1>
            <p>{{ $customer->name }} &bull; {{ $car->merk_model }} &bull; {{ $order->tanggal_mulai->translatedFormat('d M Y') }} &ndash; {{ $order->tanggal_selesai->translatedFormat('d M Y') }}</p>
        </div>
        <div class="inline-actions">
            <span class="badge {{ $payment->status_badge_class }}">{{ $payment->status_label }}</span>
            <span class="badge {{ $order->status_badge_class }}">Pesanan: {{ $order->status_label }}</span>
        </div>
    </div>

    <div class="two-col">
        <div>
            <section class="panel">
                <div class="panel-title">
                    <h2>Bukti pembayaran</h2>
                    @if($payment->waktu_bayar)
                        <span class="tiny muted">Dikirim {{ $payment->waktu_bayar->translatedFormat('d M Y, H:i') }} WIB</span>
                    @endif
                </div>

                @if($payment->bukti_bayar)
                    <a href="{{ $payment->bukti_bayar }}" target="_blank" rel="noopener" class="proof-preview">
                        <img src="{{ $payment->bukti_bayar }}" alt="Bukti pembayaran {{ $order->kode_booking }}">
                        <span class="text-link"><span>Buka ukuran penuh</span><x-icon name="arrow" /></span>
                    </a>
                @else
                    <div class="proof-empty">
                        <x-icon name="receipt" />
                        <div>
                            <strong>Tidak ada foto bukti</strong>
                            <p class="small">
                                @if($payment->metode_pembayaran === 'Tunai di kasir')
                                    Pelanggan memilih bayar tunai. Terima uang di loket, lalu konfirmasi di panel ini.
                                @else
                                    Cocokkan nomor referensi dan nominal dengan mutasi rekening / dashboard QRIS.
                                @endif
                            </p>
                        </div>
                    </div>
                @endif

                <div class="summary-line spaced">
                    <span>Metode</span>
                    <strong>{{ $payment->metode_pembayaran }}</strong>
                </div>
                <div class="summary-line">
                    <span>Nomor referensi</span>
                    <strong>{{ $payment->referensi_pembayaran ?? '-' }}</strong>
                </div>
                <div class="summary-line">
                    <span>Nominal tagihan</span>
                    <strong>{{ $order->formatted_total }}</strong>
                </div>

                @if($payment->catatan_kasir)
                    <div class="notice {{ $payment->isRejected() ? 'error' : '' }} spaced">
                        <x-icon name="info" />
                        <span><strong>Catatan kasir:</strong> {{ $payment->catatan_kasir }}</span>
                    </div>
                @endif

                @if($payment->waktu_verifikasi)
                    <p class="tiny muted spaced">
                        {{ $payment->isPaid() ? 'Diterima' : 'Ditolak' }} oleh {{ $payment->kasir->name ?? 'staf' }}
                        pada {{ $payment->waktu_verifikasi->translatedFormat('d M Y, H:i') }} WIB.
                    </p>
                @endif
            </section>

            <section class="panel">
                <div class="panel-title">
                    <h2>Penyewa &amp; unit</h2>
                    @if($document)
                        <span class="badge {{ $document->status_badge_class }}">
                            <x-icon name="shield" />
                            KTP/SIM: {{ $document->status_label }}
                        </span>
                    @else
                        <span class="badge">
                            <x-icon name="shield" />
                            KTP/SIM: Belum upload
                        </span>
                    @endif
                </div>

                <div class="mini-car">
                    <img src="{{ asset($car->foto_mobil ?? 'assets/avanza.png') }}" width="600" height="400" alt="{{ $car->merk_model }}">
                    <div>
                        <h3>{{ $car->merk_model }}</h3>
                        <p>{{ $car->plat_nomor }} &bull; {{ $car->kategori->nama_kategori ?? 'Armada' }} &bull; {{ $order->opsi_pengemudi }}</p>
                    </div>
                </div>

                <div class="summary-line">
                    <span>Nama</span>
                    <strong>{{ $customer->name }}</strong>
                </div>
                <div class="summary-line">
                    <span>Kontak</span>
                    <strong>{{ $customer->phone ?? '-' }}<br><span class="tiny muted">{{ $customer->email }}</span></strong>
                </div>
                <div class="summary-line">
                    <span>Nomor SIM</span>
                    <strong>{{ $customer->sim_number ?? '-' }}</strong>
                </div>
                <div class="summary-line">
                    <span>Serah-terima</span>
                    <strong>{{ $order->tanggal_mulai->translatedFormat('d M Y') }}, {{ $order->waktu_ambil }} WIB<br><span class="tiny muted">{{ $order->metode_pengambilan }}</span></strong>
                </div>
                <div class="summary-line">
                    <span>Titik jemput</span>
                    <strong>{{ $order->titik_jemput ?? '-' }}</strong>
                </div>
                @if($order->catatan)
                    <div class="summary-line">
                        <span>Catatan pelanggan</span>
                        <strong>{{ $order->catatan }}</strong>
                    </div>
                @endif
                @if($order->alasan_batal)
                    <div class="notice error spaced">
                        <x-icon name="info" />
                        <span><strong>Alasan pembatalan:</strong> {{ $order->alasan_batal }}</span>
                    </div>
                @endif
            </section>
        </div>

        <aside class="panel sticky-panel">
            <h3>Rincian tagihan</h3>
            <div class="summary-line spaced">
                <span>Sewa {{ $order->duration_days }} hari</span>
                <strong>Rp{{ number_format($rentalTotal, 0, ',', '.') }}</strong>
            </div>
            <div class="summary-line">
                <span>Layanan sopir</span>
                <strong>{{ $driverTotal > 0 ? 'Rp' . number_format($driverTotal, 0, ',', '.') : 'Tidak dipilih' }}</strong>
            </div>
            <div class="summary-line summary-total">
                <span>Total tagihan</span>
                <strong>{{ $order->formatted_total }}</strong>
            </div>
            <div class="summary-line">
                <span>Sudah diterima</span>
                <strong>Rp{{ number_format($payment->amount_received, 0, ',', '.') }}</strong>
            </div>

            <hr class="rule">

            @if($canApprove)
                <form action="{{ route('kasir.payments.approve', $payment->id_pembayaran) }}" method="POST">
                    @csrf
                    <div class="field">
                        <label for="approve-method">Diterima melalui</label>
                        <select id="approve-method" name="metode_pembayaran">
                            @foreach(\App\Models\Pembayaran::PAYMENT_METHODS as $paymentMethod)
                                <option value="{{ $paymentMethod }}" @selected(old('metode_pembayaran', $payment->metode_pembayaran) === $paymentMethod)>{{ $paymentMethod }}</option>
                            @endforeach
                        </select>
                        @error('metode_pembayaran')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="approve-reference">Nomor referensi / struk</label>
                        <input id="approve-reference" name="referensi_pembayaran" type="text" maxlength="100" value="{{ old('referensi_pembayaran', $payment->referensi_pembayaran) }}" placeholder="Opsional">
                        <span class="hint">Kosongkan untuk tunai, sistem membuat nomor struk otomatis.</span>
                    </div>
                    <button type="submit" class="btn btn-primary wfull">
                        <x-icon name="check" />
                        <span>Terima pembayaran {{ $order->formatted_total }}</span>
                    </button>
                </form>

                @if($canReject)
                    <button type="button" class="btn btn-outline wfull" id="btn-open-reject" style="margin-top:12px; color:var(--red);">
                        <x-icon name="close" />
                        <span>Tolak bukti pembayaran</span>
                    </button>
                @endif
            @elseif($payment->isPaid())
                <div class="notice success">
                    <x-icon name="check" />
                    <span>Pembayaran lunas. Unit siap diserahterimakan sesuai jadwal.</span>
                </div>
            @else
                <div class="notice warning">
                    <x-icon name="info" />
                    <span>Pesanan berstatus <strong>{{ $order->status_label }}</strong>, pembayaran tidak dapat diproses.</span>
                </div>
            @endif

            <a href="{{ route('kasir.payments.index') }}" class="btn btn-ghost wfull" style="margin-top:12px;">
                <x-icon name="back" />
                <span>Kembali ke daftar</span>
            </a>
        </aside>
    </div>

    @if($canReject)
        <dialog id="reject-modal" aria-labelledby="reject-title">
            <div class="dialog-header">
                <h2 id="reject-title">Tolak bukti pembayaran</h2>
                <button type="button" class="icon-btn" data-close-dialog aria-label="Tutup dialog">
                    <x-icon name="close" />
                </button>
            </div>
            <form action="{{ route('kasir.payments.reject', $payment->id_pembayaran) }}" method="POST">
                @csrf
                <div class="dialog-body">
                    <p>Pesanan <strong>{{ $order->kode_booking }}</strong> tetap aktif dan pelanggan diminta mengirim ulang bukti yang benar. Alasan ini ditampilkan ke pelanggan.</p>
                    <div class="field">
                        <label for="catatan_kasir">Alasan penolakan *</label>
                        <textarea id="catatan_kasir" name="catatan_kasir" rows="3" maxlength="500" required placeholder="Contoh: Nominal transfer tidak sesuai tagihan.">{{ old('catatan_kasir') }}</textarea>
                        @error('catatan_kasir')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="button-row">
                        <button type="button" class="btn btn-outline" data-close-dialog>Batal</button>
                        <button type="submit" class="btn btn-danger">Tolak pembayaran</button>
                    </div>
                </div>
            </form>
        </dialog>

        @push('scripts')
        <script>
            (function () {
                const modal = document.getElementById('reject-modal');
                const openButton = document.getElementById('btn-open-reject');
                if (!modal || !openButton) return;
                openButton.addEventListener('click', () => modal.showModal());
                modal.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => modal.close()));
                @if($errors->has('catatan_kasir'))
                    modal.showModal();
                @endif
            })();
        </script>
        @endpush
    @endif
</x-layouts.staff>
