<x-layouts.staff title="Armada" heading="Armada">
    <div class="page-title">
        <div>
            <h1>Armada</h1>
            <p>Atur unit, tarif harian, dan status ketersediaan yang tampil di katalog pelanggan.</p>
        </div>
        <a href="{{ route('admin.cars.create') }}" class="btn btn-primary">
            <x-icon name="plus" />
            <span>Tambah unit</span>
        </a>
    </div>

    <section class="table-panel">
        <form class="table-tools" action="{{ route('admin.cars.index') }}" method="GET" role="search">
            <div class="search-field">
                <x-icon name="search" />
                <label for="car-search" class="sr-only">Cari unit</label>
                <input id="car-search" name="q" type="search" value="{{ $search }}" placeholder="Nama mobil atau plat nomor">
            </div>
            <div class="inline-actions">
                <label for="car-status" class="sr-only">Status unit</label>
                <select id="car-status" name="status">
                    <option value="">Semua status</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-dark"><x-icon name="search" /><span>Terapkan</span></button>
            </div>
        </form>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Unit</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Tarif /hari</th>
                        <th scope="col"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cars as $car)
                        <tr>
                            <td>
                                <div class="table-car">
                                    <img src="{{ asset($car->foto_mobil ?? 'assets/avanza.png') }}" width="72" height="48" alt="" loading="lazy">
                                    <div>
                                        <strong>{{ $car->merk_model }}</strong>
                                        <small>{{ $car->plat_nomor }} &bull; {{ $car->tahun }} &bull; {{ $car->kota }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong>{{ $car->kategori->nama_kategori ?? '-' }}</strong>
                                <small>{{ $car->transmisi }} &bull; {{ $car->jumlah_kursi }} kursi</small>
                            </td>
                            <td>
                                <span class="badge {{ ['tersedia' => 'green', 'disewakan' => 'blue', 'maintenance' => 'amber'][$car->status_ketersediaan] ?? '' }}">{{ $statuses[$car->status_ketersediaan] ?? $car->status_ketersediaan }}</span>
                                <small>{{ $car->active_orders_count }} pesanan aktif</small>
                            </td>
                            <td class="text-right"><strong>{{ $car->formatted_price }}</strong></td>
                            <td class="text-right">
                                <a href="{{ route('admin.cars.edit', $car->id_mobil) }}" class="btn btn-outline btn-small"><x-icon name="edit" /><span>Ubah</span></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">Tidak ada unit yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer"><span>{{ $cars->count() }} unit</span></div>
    </section>
</x-layouts.staff>
