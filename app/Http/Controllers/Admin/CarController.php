<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KatalogMobil;
use App\Models\KategoriMobil;
use App\Models\LogActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CarController extends Controller
{
    /**
     * Status ketersediaan unit yang dikenal sistem.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'tersedia' => 'Tersedia',
        'disewakan' => 'Disewakan',
        'maintenance' => 'Perawatan',
    ];

    /**
     * Daftar armada dengan pencarian dan filter status.
     */
    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), self::STATUSES) ? (string) $request->query('status') : null;
        $search = trim((string) $request->query('q', ''));

        $cars = KatalogMobil::with('kategori')
            ->withCount(['transaksi as active_orders_count' => fn ($query) => $query->where('status_pesanan', 'dikonfirmasi')->whereDate('tanggal_selesai', '>=', today())])
            ->when($status, fn ($query, string $status) => $query->where('status_ketersediaan', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('merk_model', 'like', "%{$search}%")->orWhere('plat_nomor', 'like', "%{$search}%")))
            ->orderBy('merk_model')
            ->get();

        return view('admin.cars.index', [
            'cars' => $cars,
            'status' => $status,
            'search' => $search,
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * Form tambah unit baru.
     */
    public function create(): View
    {
        return view('admin.cars.form', $this->formData(new KatalogMobil([
            'tahun' => (int) date('Y'),
            'transmisi' => 'Otomatis',
            'jumlah_kursi' => 7,
            'bahan_bakar' => 'Bensin',
            'kota' => 'Klaten',
            'status_ketersediaan' => 'tersedia',
        ])));
    }

    /**
     * Simpan unit baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $car = new KatalogMobil;
        $this->fillCar($car, $request);
        $car->slug = $this->uniqueSlug($car->merk_model);
        $car->save();
        LogActivity::record('katalog_mobil', 'INSERT', "Menambah unit {$car->merk_model} ({$car->plat_nomor}).");

        return redirect()->route('admin.cars.index')->with('success', "Unit {$car->merk_model} berhasil ditambahkan.");
    }

    /**
     * Form ubah unit.
     */
    public function edit(int $id): View
    {
        return view('admin.cars.form', $this->formData(KatalogMobil::findOrFail($id)));
    }

    /**
     * Simpan perubahan unit.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $car = KatalogMobil::findOrFail($id);
        $this->fillCar($car, $request);
        $changedFields = implode(', ', array_keys($car->getDirty())) ?: 'tidak ada';
        $car->save();
        LogActivity::record('katalog_mobil', 'UPDATE', "Mengubah unit {$car->merk_model} ({$car->plat_nomor}); kolom: {$changedFields}.");

        return redirect()->route('admin.cars.index')->with('success', "Unit {$car->merk_model} berhasil diperbarui.");
    }

    /**
     * Validasi input form lalu isi atribut unit.
     */
    private function fillCar(KatalogMobil $car, Request $request): void
    {
        $validated = $request->validate([
            'merk_model' => ['required', 'string', 'max:100'],
            'plat_nomor' => ['required', 'string', 'max:20', Rule::unique('katalog_mobil', 'plat_nomor')->ignore($car->id_mobil, 'id_mobil')],
            'id_kategori' => ['required', 'exists:kategori_mobil,id_kategori'],
            'harga_sewa' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'status_ketersediaan' => ['required', Rule::in(array_keys(self::STATUSES))],
            'tahun' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'transmisi' => ['required', Rule::in(['Otomatis', 'Manual'])],
            'jumlah_kursi' => ['required', 'integer', 'min:2', 'max:20'],
            'bahan_bakar' => ['required', 'string', 'max:30'],
            'kota' => ['required', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'fitur' => ['nullable', 'string', 'max:500'],
            'foto' => ['nullable', 'image', 'max:3072'],
        ]);

        $car->fill(collect($validated)->except(['fitur', 'foto'])->all());
        $car->fitur = collect(explode(',', (string) ($validated['fitur'] ?? '')))->map(fn (string $item): string => trim($item))->filter()->values()->all();

        if ($request->hasFile('foto')) {
            $car->foto_mobil = '/storage/'.$request->file('foto')->store('cars', 'public');
        }
    }

    /**
     * Data bersama untuk form tambah & ubah.
     *
     * @return array<string, mixed>
     */
    private function formData(KatalogMobil $car): array
    {
        return [
            'car' => $car,
            'categories' => KategoriMobil::orderBy('nama_kategori')->get(),
            'statuses' => self::STATUSES,
        ];
    }

    /**
     * Buat slug unik dari nama unit.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'unit';
        $slug = $base;
        $counter = 2;

        while (KatalogMobil::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
