<x-layouts.kasir title="Pembayaran" heading="Pembayaran">
    <div class="page-title">
        <div>
            <h1>Pembayaran</h1>
            <p>Periksa bukti bayar pelanggan, terima pembayaran tunai di loket, dan pantau riwayat verifikasi.</p>
        </div>
    </div>

    <nav class="category-tabs" aria-label="Filter status pembayaran">
        @foreach($statusFilters as $key => $label)
            <a href="{{ route('kasir.payments.index', array_filter(['status' => $key, 'metode' => $method, 'q' => $search])) }}"
               class="chip {{ $status === $key ? 'active' : '' }}"
               @if($status === $key) aria-current="page" @endif>
                <span>{{ $label }}</span>
                <span class="chip-count">{{ $statusCounts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <section class="table-panel">
        <form class="table-tools" action="{{ route('kasir.payments.index') }}" method="GET" role="search">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="search-field">
                <x-icon name="search" />
                <label for="payment-search" class="sr-only">Cari kode booking atau pelanggan</label>
                <input id="payment-search" name="q" type="search" value="{{ $search }}" placeholder="Kode booking, nama, atau email">
            </div>
            <div class="inline-actions">
                <label for="payment-method" class="sr-only">Metode pembayaran</label>
                <select id="payment-method" name="metode">
                    <option value="">Semua metode</option>
                    @foreach(\App\Models\Pembayaran::PAYMENT_METHODS as $paymentMethod)
                        <option value="{{ $paymentMethod }}" @selected($method === $paymentMethod)>{{ $paymentMethod }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-dark">
                    <x-icon name="search" />
                    <span>Terapkan</span>
                </button>
                @if($search !== '' || $method)
                    <a href="{{ route('kasir.payments.index', ['status' => $status]) }}" class="btn btn-ghost">Reset</a>
                @endif
            </div>
        </form>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Pesanan</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Metode</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Jumlah</th>
                        <th scope="col"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        @php($order = $payment->transaksi)
                        <tr>
                            <td>
                                <strong class="od-nowrap">{{ $order->kode_booking }}</strong>
                                <small>{{ $order->mobil->merk_model }} &bull; {{ $order->duration_days }} hari</small>
                            </td>
                            <td>
                                <strong>{{ $order->user->name }}</strong>
                                <small>{{ $order->user->phone ?? $order->user->email }}</small>
                            </td>
                            <td>
                                <strong>{{ $payment->metode_pembayaran }}</strong>
                                <small>{{ $payment->referensi_pembayaran ?? 'Belum ada referensi' }}</small>
                            </td>
                            <td>
                                <span class="badge {{ $payment->status_badge_class }}">{{ $payment->status_label }}</span>
                                @if($order->status_pesanan === 'dibatalkan')
                                    <small>Pesanan dibatalkan</small>
                                @elseif($payment->waktu_verifikasi)
                                    <small class="nowrap">{{ $payment->waktu_verifikasi->translatedFormat('d M, H:i') }}{{ $payment->kasir ? ' · ' . Str::words($payment->kasir->name, 1, '') : '' }}</small>
                                @elseif($payment->waktu_bayar)
                                    <small class="nowrap">Dikirim {{ $payment->waktu_bayar->diffForHumans() }}</small>
                                @else
                                    <small class="nowrap">Dipesan {{ $order->waktu_pesan?->translatedFormat('d M, H:i') ?? '-' }}</small>
                                @endif
                            </td>
                            <td class="text-right"><strong>{{ $payment->formatted_amount }}</strong></td>
                            <td class="text-right">
                                <a href="{{ route('kasir.payments.show', $payment->id_pembayaran) }}" class="btn {{ $payment->isAwaitingVerification() && $order->status_pesanan === 'menunggu_pembayaran' ? 'btn-primary' : 'btn-outline' }} btn-small">
                                    {{ $payment->isAwaitingVerification() && $order->status_pesanan === 'menunggu_pembayaran' ? 'Verifikasi' : 'Detail' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="table-empty">
                                <x-icon name="search" />
                                <span>
                                    @if($search !== '' || $method)
                                        Tidak ada pembayaran yang cocok dengan pencarian Anda.
                                    @elseif($status === 'menunggu_verifikasi')
                                        Antrean kosong. Semua pembayaran sudah diverifikasi.
                                    @else
                                        Belum ada pembayaran dengan status ini.
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span>
                @if($payments->total() > 0)
                    Menampilkan {{ $payments->firstItem() }}–{{ $payments->lastItem() }} dari {{ $payments->total() }} pembayaran
                @else
                    0 pembayaran
                @endif
            </span>
            @if($payments->hasPages())
                <div class="inline-actions">
                    @if($payments->onFirstPage())
                        <span class="btn btn-outline btn-small" aria-disabled="true" style="opacity:.5;">
                            <x-icon name="back" />
                            <span>Sebelumnya</span>
                        </span>
                    @else
                        <a href="{{ $payments->previousPageUrl() }}" class="btn btn-outline btn-small" rel="prev">
                            <x-icon name="back" />
                            <span>Sebelumnya</span>
                        </a>
                    @endif
                    @if($payments->hasMorePages())
                        <a href="{{ $payments->nextPageUrl() }}" class="btn btn-outline btn-small" rel="next">
                            <span>Berikutnya</span>
                            <x-icon name="arrow" />
                        </a>
                    @else
                        <span class="btn btn-outline btn-small" aria-disabled="true" style="opacity:.5;">
                            <span>Berikutnya</span>
                            <x-icon name="arrow" />
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </section>
</x-layouts.kasir>
