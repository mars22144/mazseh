<x-layouts.staff title="Ringkasan" heading="Ringkasan">
    @php
        $maxDailyIncome = max(1, $dailyIncome->max('total'));
        $formatRupiah = fn (float $amount): string => 'Rp' . number_format($amount, 0, ',', '.');
        $formatShort = function (float $amount): string {
            if ($amount >= 1000000) {
                return rtrim(rtrim(number_format($amount / 1000000, 1, ',', '.'), '0'), ',') . ' jt';
            }

            return $amount > 0 ? number_format($amount / 1000, 0, ',', '.') . ' rb' : '0';
        };
    @endphp

    <div class="page-title">
        <div>
            <h1>Halo, {{ Str::words(Auth::user()->name, 1, '') }}</h1>
            <p>Verifikasi pembayaran masuk dan siapkan serah-terima unit hari ini.</p>
        </div>
        <a href="{{ route('kasir.payments.index') }}" class="btn btn-primary">
            <x-icon name="wallet" />
            <span>Buka antrean verifikasi</span>
        </a>
    </div>

    <section class="stats-grid" aria-label="Statistik kasir">
        <div class="stat-card">
            <div class="stat-label">
                <span>Menunggu verifikasi</span>
                <x-icon name="clock" />
            </div>
            <span class="stat-value">{{ $awaitingCount }}</span>
            <span class="stat-note">Bukti bayar dari pelanggan</span>
        </div>
        <div class="stat-card">
            <div class="stat-label">
                <span>Kas masuk hari ini</span>
                <x-icon name="wallet" />
            </div>
            <span class="stat-value">{{ $formatRupiah($receivedTodayTotal) }}</span>
            <span class="stat-note">{{ $receivedTodayCount }} pembayaran diverifikasi</span>
        </div>
        <div class="stat-card">
            <div class="stat-label">
                <span>Belum dibayar</span>
                <x-icon name="receipt" />
            </div>
            <span class="stat-value">{{ $unpaidCount }}</span>
            <span class="stat-note">Pesanan belum kirim pembayaran</span>
        </div>
        <div class="stat-card">
            <div class="stat-label">
                <span>Serah-terima hari ini</span>
                <x-icon name="key" />
            </div>
            <span class="stat-value">{{ $handoversTodayCount }}</span>
            <span class="stat-note">Unit lunas siap diserahkan</span>
        </div>
    </section>

    <div class="dashboard-grid">
        <section class="panel">
            <div class="panel-title">
                <div>
                    <h2>Kas masuk 7 hari</h2>
                    <p>Total pembayaran lunas per hari.</p>
                </div>
                <span class="badge blue">{{ $formatRupiah((float) $dailyIncome->sum('total')) }}</span>
            </div>
            <div class="chart-bars chart-bars-7" role="img" aria-label="Grafik kas masuk tujuh hari terakhir">
                @foreach($dailyIncome as $day)
                    <div class="chart-column">
                        <span class="chart-value">{{ $formatShort($day['total']) }}</span>
                        <span class="chart-bar {{ $day['is_today'] ? 'is-today' : '' }}" style="height:{{ max(2, round($day['total'] / $maxDailyIncome * 100)) }}%"></span>
                        <small>{{ $day['is_today'] ? 'Hari ini' : $day['label'] }}</small>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="panel">
            <div class="panel-title">
                <div>
                    <h2>Jadwal serah-terima</h2>
                    <p>Pesanan lunas untuk hari ini dan besok.</p>
                </div>
            </div>
            @forelse($handovers as $handover)
                <div class="fleet-summary">
                    <div>
                        <strong class="small" style="display:block;">{{ $handover->mobil->merk_model }}</strong>
                        <span class="tiny muted">{{ $handover->kode_booking }} &bull; {{ $handover->user->name }}</span>
                    </div>
                    <span class="badge {{ $handover->tanggal_mulai->isToday() ? 'green' : '' }}">
                        <x-icon name="clock" />
                        {{ $handover->tanggal_mulai->isToday() ? 'Hari ini' : 'Besok' }}, {{ $handover->waktu_ambil }}
                    </span>
                </div>
            @empty
                <div class="table-empty">
                    <p class="small">Belum ada serah-terima unit untuk hari ini dan besok.</p>
                </div>
            @endforelse
        </section>
    </div>

    <section class="table-panel">
        <div class="panel-title">
            <div>
                <h2>Antrean verifikasi</h2>
                <p>Diurutkan dari konfirmasi pembayaran paling lama.</p>
            </div>
            <a href="{{ route('kasir.payments.index') }}" class="text-link">
                <span>Lihat semua</span>
                <x-icon name="arrow" />
            </a>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Pesanan</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Metode</th>
                        <th scope="col">Dikirim</th>
                        <th scope="col" class="text-right">Jumlah</th>
                        <th scope="col"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($queue as $payment)
                        <tr>
                            <td>
                                <strong class="od-nowrap">{{ $payment->transaksi->kode_booking }}</strong>
                                <small>{{ $payment->transaksi->mobil->merk_model }}</small>
                            </td>
                            <td>
                                <strong>{{ $payment->transaksi->user->name }}</strong>
                                <small>{{ $payment->transaksi->user->phone ?? $payment->transaksi->user->email }}</small>
                            </td>
                            <td>{{ $payment->metode_pembayaran }}</td>
                            <td>
                                <strong>{{ $payment->waktu_bayar->diffForHumans() }}</strong>
                                <small>{{ $payment->waktu_bayar->translatedFormat('d M Y, H:i') }} WIB</small>
                            </td>
                            <td class="text-right"><strong>{{ $payment->formatted_amount }}</strong></td>
                            <td class="text-right">
                                <a href="{{ route('kasir.payments.show', $payment->id_pembayaran) }}" class="btn btn-outline btn-small">Periksa</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="table-empty">
                                <x-icon name="check" style="color:var(--green);" />
                                <span>Semua pembayaran sudah diverifikasi.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.staff>
