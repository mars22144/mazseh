<x-layouts.app :title="'Konfirmasi Pesanan ' . $transaksi->kode_booking . ' | MAZSEH'">
    @php
        $payment = $transaksi->pembayaran;
        $isPaid = (bool) $payment?->isPaid();
        $isAwaitingVerification = (bool) $payment?->isAwaitingVerification();
    @endphp

    <div class="container">
        <div class="panel success-page text-center">
            <div class="success-symbol {{ $isAwaitingVerification ? 'is-pending' : '' }}">
                <x-icon name="{{ $isAwaitingVerification ? 'clock' : 'check' }}" />
            </div>

            @if($isPaid)
                <h1 tabindex="-1">Pembayaran Anda Telah Diterima!</h1>
                <p class="intro">Pesanan sudah dikonfirmasi. Tunjukkan kode booking saat serah-terima unit.</p>
            @elseif($isAwaitingVerification)
                <h1 tabindex="-1">Konfirmasi Pembayaran Terkirim!</h1>
                <p class="intro">Kasir MAZSEH sedang memverifikasi pembayaran Anda. Status pesanan akan berubah menjadi Dikonfirmasi setelah pembayaran diterima.</p>
            @else
                <h1 tabindex="-1">Pesanan Anda Berhasil Dibuat!</h1>
                <p class="intro">Detail pesanan telah kami catat. Selesaikan pembayaran untuk mengonfirmasi jadwal sewa.</p>
            @endif

            <span class="badge {{ $payment->status_badge_class ?? $transaksi->status_badge_class }}" style="font-size:13px; padding:6px 14px;">
                Pembayaran: {{ $payment->status_label ?? 'Belum Dibayar' }}
            </span>

            <div style="max-width:440px; margin:24px auto; text-align:left;">
                <div class="summary-line">
                    <span>Nomor Pesanan</span>
                    <strong>{{ $transaksi->kode_booking }}</strong>
                </div>
                <div class="summary-line">
                    <span>Mobil</span>
                    <strong>{{ $transaksi->mobil->merk_model }}</strong>
                </div>
                <div class="summary-line">
                    <span>Jadwal Sewa</span>
                    <strong>{{ $transaksi->tanggal_mulai->translatedFormat('d M Y') }} &ndash; {{ $transaksi->tanggal_selesai->translatedFormat('d M Y') }} ({{ $transaksi->duration_days }} hari)</strong>
                </div>
                <div class="summary-line">
                    <span>Titik Penjemputan</span>
                    <strong>{{ $transaksi->titik_jemput }}</strong>
                </div>
                <div class="summary-line summary-total">
                    <span>Total Biaya</span>
                    <strong>{{ $transaksi->formatted_total }}</strong>
                </div>
            </div>

            <div class="button-row" style="justify-content:center; margin-top:32px;">
                <a href="{{ route('orders.show', $transaksi->id_transaksi) }}" class="btn btn-primary" style="padding-inline:24px;">Lihat Detail Pesanan</a>
                @if($transaksi->status_pesanan === 'menunggu_pembayaran' && ! $isAwaitingVerification)
                    <a href="{{ route('orders.payment', $transaksi->id_transaksi) }}" class="btn btn-dark">Bayar Sekarang</a>
                @endif
                <a href="{{ route('home') }}" class="btn btn-outline">Kembali ke Beranda</a>
            </div>
        </div>
    </div>
</x-layouts.app>
