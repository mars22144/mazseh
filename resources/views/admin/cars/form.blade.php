@php
    $isEditing = $car->exists;
    $value = fn (string $field, mixed $default = '') => old($field, $car->{$field} ?? $default);
@endphp

<x-layouts.staff :title="$isEditing ? 'Ubah ' . $car->merk_model : 'Tambah unit'" :heading="$isEditing ? 'Ubah unit' : 'Tambah unit'">
    <nav class="breadcrumbs" aria-label="Jejak halaman">
        <a href="{{ route('admin.cars.index') }}">Armada</a>
        <x-icon name="chevron" />
        <span>{{ $isEditing ? $car->merk_model : 'Unit baru' }}</span>
    </nav>

    <div class="page-title">
        <div>
            <h1>{{ $isEditing ? 'Ubah ' . $car->merk_model : 'Tambah unit baru' }}</h1>
            <p>Data ini langsung tampil di katalog dan dipakai untuk menghitung tagihan sewa.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="notice error error-summary" role="alert">
            <x-icon name="info" />
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form action="{{ $isEditing ? route('admin.cars.update', $car->id_mobil) : route('admin.cars.store') }}" method="POST" enctype="multipart/form-data" class="two-col">
        @csrf
        @if($isEditing) @method('PUT') @endif

        <section class="panel">
            <h2 class="panel-title" style="font-size:20px;">Data unit</h2>
            <div class="form-grid">
                <div class="field full">
                    <label for="merk_model">Merk & model *</label>
                    <input id="merk_model" name="merk_model" type="text" maxlength="100" required value="{{ $value('merk_model') }}" placeholder="Contoh: Toyota Avanza">
                </div>
                <div class="field">
                    <label for="plat_nomor">Plat nomor *</label>
                    <input id="plat_nomor" name="plat_nomor" type="text" maxlength="20" required value="{{ $value('plat_nomor') }}" placeholder="H 1234 AB">
                </div>
                <div class="field">
                    <label for="id_kategori">Kategori *</label>
                    <select id="id_kategori" name="id_kategori" required>
                        @foreach($categories as $category)
                            <option value="{{ $category->id_kategori }}" @selected((int) $value('id_kategori') === $category->id_kategori)>{{ $category->nama_kategori }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="tahun">Tahun *</label>
                    <input id="tahun" name="tahun" type="number" min="1990" max="{{ date('Y') + 1 }}" required value="{{ $value('tahun') }}">
                </div>
                <div class="field">
                    <label for="transmisi">Transmisi *</label>
                    <select id="transmisi" name="transmisi">
                        @foreach(['Otomatis', 'Manual'] as $transmission)
                            <option value="{{ $transmission }}" @selected($value('transmisi') === $transmission)>{{ $transmission }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="jumlah_kursi">Jumlah kursi *</label>
                    <input id="jumlah_kursi" name="jumlah_kursi" type="number" min="2" max="20" required value="{{ $value('jumlah_kursi') }}">
                </div>
                <div class="field">
                    <label for="bahan_bakar">Bahan bakar *</label>
                    <input id="bahan_bakar" name="bahan_bakar" type="text" maxlength="30" required value="{{ $value('bahan_bakar') }}" list="fuel-options">
                    <datalist id="fuel-options"><option value="Bensin"><option value="Diesel"><option value="Hybrid"><option value="Listrik"></datalist>
                </div>
                <div class="field">
                    <label for="kota">Kota unit *</label>
                    <input id="kota" name="kota" type="text" maxlength="50" required value="{{ $value('kota') }}">
                </div>
                <div class="field">
                    <label for="label">Label singkat</label>
                    <input id="label" name="label" type="text" maxlength="50" value="{{ $value('label') }}" placeholder="Contoh: Perjalanan keluarga">
                </div>
                <div class="field full">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" rows="3" maxlength="2000">{{ $value('deskripsi') }}</textarea>
                </div>
                <div class="field full">
                    <label for="fitur">Fitur</label>
                    <input id="fitur" name="fitur" type="text" maxlength="500" value="{{ old('fitur', implode(', ', $car->fitur ?? [])) }}" placeholder="AC kabin, Audio & USB, Kursi tiga baris">
                    <span class="hint">Pisahkan dengan koma.</span>
                </div>
            </div>
        </section>

        <aside class="panel sticky-panel">
            <h3>Tarif & status</h3>
            <div class="field spaced">
                <label for="harga_sewa">Tarif per hari (Rp) *</label>
                <input id="harga_sewa" name="harga_sewa" type="number" min="0" step="1000" required value="{{ old('harga_sewa', $car->harga_sewa !== null ? (int) $car->harga_sewa : '') }}">
            </div>
            <div class="field">
                <label for="status_ketersediaan">Status ketersediaan *</label>
                <select id="status_ketersediaan" name="status_ketersediaan">
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" @selected($value('status_ketersediaan') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="hint">Unit "Perawatan" tidak bisa dipesan pelanggan.</span>
            </div>
            <div class="field">
                <label for="foto">Foto unit</label>
                @if($car->foto_mobil)
                    <img src="{{ asset($car->foto_mobil) }}" alt="Foto {{ $car->merk_model }} saat ini" class="doc-preview-img" style="width:100%; object-fit:contain; background:var(--muted);">
                @endif
                <input id="foto" name="foto" type="file" accept="image/*">
                <span class="hint">JPG/PNG/WebP, maks 3MB. Kosongkan untuk mempertahankan foto.</span>
            </div>
            <button type="submit" class="btn btn-primary wfull">
                <x-icon name="check" />
                <span>{{ $isEditing ? 'Simpan perubahan' : 'Tambah unit' }}</span>
            </button>
            <a href="{{ route('admin.cars.index') }}" class="btn btn-ghost wfull" style="margin-top:8px;">Batal</a>
        </aside>
    </form>
</x-layouts.staff>
