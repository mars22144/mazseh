<x-layouts.staff title="Proses Refund" heading="Proses Refund">
    @php
        $statusRefund = (string) request()->query('status_refund', '');
        $filterOptions = [
            '' => 'Semua',
            'menunggu_proses' => 'Menunggu Proses',
            'selesai' => 'Selesai',
        ];
    @endphp

    <div class="page-title">
        <div>
            <h1>Pengembalian Dana (Refund)</h1>
            <p>Proses pencairan refund untuk transaksi lunas yang dibatalkan.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="notice error error-summary" role="alert">
            <x-icon name="info" />
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <nav class="category-tabs" aria-label="Filter status refund">
        @foreach($filterOptions as $key => $label)
            <a href="{{ route('kasir.refund.index', array_filter(['status_refund' => $key])) }}"
               class="chip {{ $statusRefund === $key ? 'active' : '' }}"
               @if($statusRefund === $key) aria-current="page" @endif>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>

    <section class="table-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Pesanan</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col" class="text-right">Nominal Bayar</th>
                        <th scope="col">Status Refund</th>
                        <th scope="col">Proses Refund</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($refundList as $item)
                        @php($order = $item->transaksi)
                        <tr>
                            <td>
                                @if($order)
                                    <a href="{{ route('kasir.transaksi.show', $order->id_transaksi) }}" class="text-link" style="min-height:auto;">
                                        <strong class="od-nowrap">{{ $order->kode_booking }}</strong>
                                    </a>
                                    <small>{{ $order->mobil->merk_model ?? '-' }}</small>
                                    @if($order->alasan_batal)
                                        <small>Alasan: {{ $order->alasan_batal }}</small>
                                    @endif
                                @else
                                    <strong>#{{ $item->id_transaksi }}</strong>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $order?->user?->name ?? '-' }}</strong>
                                <small>{{ $order?->user?->phone ?? $order?->user?->email ?? '-' }}</small>
                            </td>
                            <td class="text-right">
                                <strong>{{ $item->formatted_amount }}</strong>
                                @if((float) $item->jumlah_refund > 0)
                                    <small>Direfund: Rp{{ number_format((float) $item->jumlah_refund, 0, ',', '.') }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $item->status_refund === 'selesai' ? 'green' : 'amber' }}">
                                    {{ $item->status_refund === 'selesai' ? 'Selesai' : 'Menunggu Proses' }}
                                </span>
                                @if($item->kasir)
                                    <small>Kasir: {{ $item->kasir->name }}</small>
                                @endif
                            </td>
                            <td>
                                @if($item->status_refund === 'menunggu_proses')
                                    <form action="{{ route('kasir.refund.process', $item->id_pembayaran) }}" method="POST" class="od-stack" style="--od-gap:6px; min-width:220px;">
                                        @csrf
                                        <input type="number" name="jumlah_refund" min="1" max="{{ (int) $item->jumlah_bayar }}" step="1" value="{{ (int) $item->jumlah_bayar }}" required style="min-height:36px; padding:6px 10px; font-size:12px;">
                                        <input type="text" name="catatan" maxlength="500" placeholder="Catatan refund (opsional)" style="min-height:36px; padding:6px 10px; font-size:12px;">
                                        <button type="submit" class="btn btn-primary btn-small">Selesaikan Refund</button>
                                    </form>
                                @else
                                    <span class="tiny muted">Dana telah dicairkan</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="table-empty">
                                <x-icon name="receipt" />
                                <span>Tidak ada pengajuan refund saat ini.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span>
                @if($refundList->total() > 0)
                    Menampilkan {{ $refundList->firstItem() }}&ndash;{{ $refundList->lastItem() }} dari {{ $refundList->total() }} refund
                @else
                    0 refund
                @endif
            </span>
            @if($refundList->hasPages())
                <div class="inline-actions">
                    @if(! $refundList->onFirstPage())
                        <a href="{{ $refundList->previousPageUrl() }}" class="btn btn-outline btn-small" rel="prev">
                            <x-icon name="back" />
                            <span>Sebelumnya</span>
                        </a>
                    @endif
                    @if($refundList->hasMorePages())
                        <a href="{{ $refundList->nextPageUrl() }}" class="btn btn-outline btn-small" rel="next">
                            <span>Berikutnya</span>
                            <x-icon name="arrow" />
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>
</x-layouts.staff>
