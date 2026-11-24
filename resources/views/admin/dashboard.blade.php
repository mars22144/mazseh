<x-layouts.staff title="Ikhtisar" heading="Ikhtisar">
    <div class="page-title">
        <div>
            <h1>Ikhtisar bisnis</h1>
            <p>Pendapatan, armada, dan pengguna MAZSEH bulan {{ now()->translatedFormat('F Y') }}.</p>
        </div>
        <a href="{{ route('admin.cars.create') }}" class="btn btn-primary">
            <x-icon name="plus" />
            <span>Tambah unit</span>
        </a>
    </div>

    <section class="stats-grid" aria-label="Statistik bisnis">
        <div class="stat-card">
            <div class="stat-label"><span>Pendapatan bulan ini</span><x-icon name="wallet" /></div>
            <span class="stat-value">Rp{{ number_format($monthlyRevenue, 0, ',', '.') }}</span>
            <span class="stat-note">Pembayaran lunas</span>
        </div>
        <div class="stat-card">
            <div class="stat-label"><span>Pesanan aktif</span><x-icon name="calendar" /></div>
            <span class="stat-value">{{ $activeOrders }}</span>
            <span class="stat-note">{{ $awaitingPayments }} pembayaran menunggu kasir</span>
        </div>
        <div class="stat-card">
            <div class="stat-label"><span>Armada siap sewa</span><x-icon name="car" /></div>
            <span class="stat-value">{{ $fleetByStatus['tersedia'] ?? 0 }}/{{ $fleetTotal }}</span>
            <span class="stat-note">{{ $fleetByStatus['maintenance'] ?? 0 }} unit dalam perawatan</span>
        </div>
        <div class="stat-card">
            <div class="stat-label"><span>Pelanggan</span><x-icon name="users" /></div>
            <span class="stat-value">{{ $customerCount }}</span>
            <span class="stat-note">{{ $pendingDocuments }} dokumen menunggu verifikasi</span>
        </div>
    </section>

    <div class="dashboard-grid admin-grid">
        <section class="table-panel">
            <div class="panel-title">
                <div>
                    <h2>Pesanan terbaru</h2>
                    <p>Enam transaksi terakhir dari semua pelanggan.</p>
                </div>
                <a href="{{ route('kasir.payments.index', ['status' => 'semua']) }}" class="text-link"><span>Pembayaran</span><x-icon name="arrow" /></a>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Pesanan</th>
                            <th scope="col">Pelanggan</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr>
                                <td><strong class="od-nowrap">{{ $order->kode_booking }}</strong><small>{{ $order->mobil->merk_model ?? '-' }}</small></td>
                                <td><strong>{{ $order->user->name ?? '-' }}</strong><small>{{ $order->tanggal_mulai->translatedFormat('d M') }} &ndash; {{ $order->tanggal_selesai->translatedFormat('d M Y') }}</small></td>
                                <td><span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span></td>
                                <td class="text-right"><strong>{{ $order->formatted_total }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="table-empty">Belum ada pesanan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-title">
                <div>
                    <h2>Kondisi armada</h2>
                    <p>{{ $fleetTotal }} unit terdaftar.</p>
                </div>
            </div>
            @foreach(\App\Http\Controllers\Admin\CarController::STATUSES as $key => $label)
                <a href="{{ route('admin.cars.index', ['status' => $key]) }}" class="fleet-summary">
                    <span>{{ $label }}</span>
                    <strong>{{ $fleetByStatus[$key] ?? 0 }} unit</strong>
                </a>
            @endforeach
            <div class="quick-actions spaced">
                <a href="{{ route('admin.cars.index') }}" class="quick-action"><x-icon name="car" /><span>Kelola armada</span></a>
                <a href="{{ route('admin.users.index', ['dokumen' => 1]) }}" class="quick-action"><x-icon name="shield" /><span>Verifikasi dokumen</span></a>
            </div>
        </section>
    </div>
</x-layouts.staff>
