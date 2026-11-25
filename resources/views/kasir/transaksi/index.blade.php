<x-layouts.staff title="Manajemen Transaksi" heading="Transaksi">
    @php
        $status = (string) request()->query('status', '');
        $search = trim((string) request()->query('q', ''));
        $statusOptions = [
            '' => 'Semua',
            'menunggu_pembayaran' => 'Menunggu Pembayaran',
            'dikonfirmasi' => 'Dikonfirmasi',
            'sedang_disewa' => 'Sedang Disewa',
            'selesai' => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
        ];
    @endphp

    <div class="page-title">
        <div>
            <h1>Manajemen Transaksi</h1>
            <p>Kelola pesanan online dan walk-in, serah-terima unit, pengembalian, serta cetak kuitansi.</p>
        </div>
        <a href="{{ route('kasir.transaksi.create') }}" class="btn btn-primary">
            <x-icon name="plus" />
            <span>Buat Transaksi Walk-in</span>
        </a>
    </div>

    <nav class="category-tabs" aria-label="Filter status transaksi">
        @foreach($statusOptions as $key => $label)
            <a href="{{ route('kasir.transaksi.index', array_filter(['status' => $key, 'q' => $search])) }}"
               class="chip {{ $status === $key ? 'active' : '' }}"
               @if($status === $key) aria-current="page" @endif>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>

    <section class="table-panel">
        <form class="table-tools" action="{{ route('kasir.transaksi.index') }}" method="GET" role="search">
            @if($status !== '')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <div class="search-field">
                <x-icon name="search" />
                <label for="transaksi-search" class="sr-only">Cari kode booking, pelanggan, atau mobil</label>
                <input id="transaksi-search" name="q" type="search" value="{{ $search }}" placeholder="Kode booking, nama pelanggan, plat, atau merk mobil">
            </div>
            <div class="inline-actions">
                <button type="submit" class="btn btn-dark">
                    <x-icon name="search" />
                    <span>Cari</span>
                </button>
                @if($search !== '' || $status !== '')
                    <a href="{{ route('kasir.transaksi.index') }}" class="btn btn-ghost">Reset</a>
                @endif
            </div>
        </form>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Kode &amp; Unit</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Jadwal Sewa</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Total</th>
                        <th scope="col"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transaksiList as $item)
                        <tr>
                            <td>
                                <strong class="od-nowrap">{{ $item->kode_booking }}</strong>
                                <small>{{ $item->mobil->merk_model ?? '-' }} &bull; {{ $item->mobil->plat_nomor ?? '-' }}</small>
                            </td>
                            <td>
                                <strong>{{ $item->user->name ?? '-' }}</strong>
                                <small>{{ $item->user->phone ?? $item->user->email ?? '-' }}</small>
                            </td>
                            <td>
                                <strong>{{ $item->tanggal_mulai->translatedFormat('d M Y') }} &ndash; {{ $item->tanggal_selesai->translatedFormat('d M Y') }}</strong>
                                <small>{{ $item->duration_days }} hari &bull; {{ $item->waktu_ambil }} WIB &bull; {{ $item->opsi_pengemudi }}</small>
                            </td>
                            <td>
                                <span class="badge {{ $item->status_badge_class }}">{{ $item->status_label }}</span>
                                @if($item->pembayaran)
                                    <small>Bayar: {{ $item->pembayaran->status_label }} ({{ $item->pembayaran->metode_pembayaran }})</small>
                                @endif
                            </td>
                            <td class="text-right">
                                <strong>{{ $item->formatted_total }}</strong>
                            </td>
                            <td class="text-right">
                                <div class="inline-actions" style="justify-content:flex-end;">
                                    <a href="{{ route('kasir.transaksi.show', $item->id_transaksi) }}" class="btn btn-outline btn-small">Detail</a>
                                    <a href="{{ route('kasir.transaksi.receipt', $item->id_transaksi) }}" class="btn btn-ghost btn-small">Kuitansi</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="table-empty">
                                <x-icon name="car" />
                                <span>Belum ada transaksi yang sesuai filter.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span>
                @if($transaksiList->total() > 0)
                    Menampilkan {{ $transaksiList->firstItem() }}&ndash;{{ $transaksiList->lastItem() }} dari {{ $transaksiList->total() }} transaksi
                @else
                    0 transaksi
                @endif
            </span>
            @if($transaksiList->hasPages())
                <div class="inline-actions">
                    @if(! $transaksiList->onFirstPage())
                        <a href="{{ $transaksiList->previousPageUrl() }}" class="btn btn-outline btn-small" rel="prev">
                            <x-icon name="back" />
                            <span>Sebelumnya</span>
                        </a>
                    @endif
                    @if($transaksiList->hasMorePages())
                        <a href="{{ $transaksiList->nextPageUrl() }}" class="btn btn-outline btn-small" rel="next">
                            <span>Berikutnya</span>
                            <x-icon name="arrow" />
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>
</x-layouts.staff>
