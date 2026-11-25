<x-layouts.staff title="Verifikasi Pembayaran" heading="Verifikasi Pembayaran">
    @php
        $status = (string) request()->query('status', '');
        $metode = (string) request()->query('metode', '');
        $search = trim((string) request()->query('q', ''));
        $statusOptions = [
            '' => 'Semua Status',
            'pending' => 'Pending',
            'lunas' => 'Lunas',
            'gagal' => 'Gagal / Ditolak',
        ];
    @endphp

    <div class="page-title">
        <div>
            <h1>Verifikasi Pembayaran</h1>
            <p>Periksa bukti pembayaran masuk, lalu setujui atau tolak langsung dari daftar ini.</p>
        </div>
        <a href="{{ route('kasir.transaksi.create') }}" class="btn btn-primary">
            <x-icon name="plus" />
            <span>Transaksi Walk-in</span>
        </a>
    </div>

    @if($errors->any())
        <div class="notice error error-summary" role="alert">
            <x-icon name="info" />
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <nav class="category-tabs" aria-label="Filter status pembayaran">
        @foreach($statusOptions as $key => $label)
            <a href="{{ route('kasir.pembayaran.index', array_filter(['status' => $key, 'metode' => $metode, 'q' => $search])) }}"
               class="chip {{ $status === $key ? 'active' : '' }}"
               @if($status === $key) aria-current="page" @endif>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>

    <section class="table-panel">
        <form class="table-tools" action="{{ route('kasir.pembayaran.index') }}" method="GET" role="search">
            @if($status !== '')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <div class="search-field">
                <x-icon name="search" />
                <label for="pembayaran-search" class="sr-only">Cari referensi, kode booking, atau nama pelanggan</label>
                <input id="pembayaran-search" name="q" type="search" value="{{ $search }}" placeholder="Kode booking, referensi, atau nama">
            </div>
            <div class="inline-actions">
                <label for="pembayaran-metode" class="sr-only">Metode pembayaran</label>
                <select id="pembayaran-metode" name="metode">
                    <option value="">Semua metode</option>
                    @foreach(\App\Models\Pembayaran::PAYMENT_METHODS as $paymentMethod)
                        <option value="{{ $paymentMethod }}" @selected($metode === $paymentMethod)>{{ $paymentMethod }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-dark">
                    <x-icon name="search" />
                    <span>Terapkan</span>
                </button>
                @if($search !== '' || $metode !== '' || $status !== '')
                    <a href="{{ route('kasir.pembayaran.index') }}" class="btn btn-ghost">Reset</a>
                @endif
            </div>
        </form>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Pesanan</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Metode &amp; Referensi</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Jumlah</th>
                        <th scope="col">Verifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pembayaranList as $item)
                        @php($order = $item->transaksi)
                        <tr>
                            <td>
                                @if($order)
                                    <a href="{{ route('kasir.transaksi.show', $order->id_transaksi) }}" class="text-link" style="min-height:auto;">
                                        <strong class="od-nowrap">{{ $order->kode_booking }}</strong>
                                    </a>
                                    <small>{{ $order->mobil->merk_model ?? '-' }} &bull; {{ $order->status_label }}</small>
                                @else
                                    <strong>#{{ $item->id_transaksi }}</strong>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $order?->user?->name ?? '-' }}</strong>
                                <small>{{ $order?->user?->phone ?? $order?->user?->email ?? '-' }}</small>
                            </td>
                            <td>
                                <strong>{{ $item->metode_pembayaran }}</strong>
                                <small>{{ $item->referensi_pembayaran ?? 'Belum ada referensi' }}</small>
                                @if($item->bukti_bayar)
                                    <small>
                                        <a href="{{ route('files.payment', $item->id_pembayaran) }}" target="_blank" rel="noopener" class="text-link" style="min-height:auto;">Lihat bukti</a>
                                    </small>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $item->status_badge_class }}">{{ $item->status_label }}</span>
                                @if($item->waktu_verifikasi)
                                    <small>{{ $item->waktu_verifikasi->translatedFormat('d M Y, H:i') }}{{ $item->kasir ? ' · ' . Str::words($item->kasir->name, 1, '') : '' }}</small>
                                @elseif($item->waktu_bayar)
                                    <small>Dikirim {{ $item->waktu_bayar->translatedFormat('d M Y, H:i') }}</small>
                                @endif
                                @if($item->catatan_kasir)
                                    <small>{{ $item->catatan_kasir }}</small>
                                @endif
                            </td>
                            <td class="text-right">
                                <strong>{{ $item->formatted_amount }}</strong>
                                @if($item->status_refund !== 'tidak_ada')
                                    <small>Refund: {{ str_replace('_', ' ', $item->status_refund) }}</small>
                                @endif
                            </td>
                            <td>
                                @if($item->status_pembayaran === 'pending' && $order && $order->status_pesanan === 'menunggu_pembayaran')
                                    <form action="{{ route('kasir.pembayaran.verify', $item->id_pembayaran) }}" method="POST" class="od-stack" style="--od-gap:6px; min-width:210px;">
                                        @csrf
                                        <input type="text" name="catatan" maxlength="500" placeholder="Catatan verifikasi (opsional)" style="min-height:36px; padding:6px 10px; font-size:12px;">
                                        <div class="inline-actions">
                                            <button type="submit" name="keputusan" value="terima" class="btn btn-primary btn-small">Terima</button>
                                            <button type="submit" name="keputusan" value="tolak" class="btn btn-outline btn-small" style="color:var(--red);">Tolak</button>
                                            <a href="{{ route('kasir.payments.show', $item->id_pembayaran) }}" class="btn btn-ghost btn-small">Detail</a>
                                        </div>
                                    </form>
                                @else
                                    <a href="{{ route('kasir.payments.show', $item->id_pembayaran) }}" class="btn btn-outline btn-small">Detail</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="table-empty">
                                <x-icon name="wallet" />
                                <span>Tidak ada data pembayaran yang sesuai filter.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span>
                @if($pembayaranList->total() > 0)
                    Menampilkan {{ $pembayaranList->firstItem() }}&ndash;{{ $pembayaranList->lastItem() }} dari {{ $pembayaranList->total() }} pembayaran
                @else
                    0 pembayaran
                @endif
            </span>
            @if($pembayaranList->hasPages())
                <div class="inline-actions">
                    @if(! $pembayaranList->onFirstPage())
                        <a href="{{ $pembayaranList->previousPageUrl() }}" class="btn btn-outline btn-small" rel="prev">
                            <x-icon name="back" />
                            <span>Sebelumnya</span>
                        </a>
                    @endif
                    @if($pembayaranList->hasMorePages())
                        <a href="{{ $pembayaranList->nextPageUrl() }}" class="btn btn-outline btn-small" rel="next">
                            <span>Berikutnya</span>
                            <x-icon name="arrow" />
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>
</x-layouts.staff>
