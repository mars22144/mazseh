<x-layouts.staff title="Verifikasi Dokumen" heading="Verifikasi Dokumen">
    @php
        $status = (string) request()->query('status', '');
        $search = trim((string) request()->query('q', ''));
        $statusOptions = [
            '' => 'Semua',
            'menunggu_validasi' => 'Menunggu Validasi',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            'belum_upload' => 'Belum Upload',
        ];
    @endphp

    <div class="page-title">
        <div>
            <h1>Verifikasi Dokumen KTP &amp; SIM</h1>
            <p>Validasi identitas pelanggan sebelum serah-terima armada.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="notice error error-summary" role="alert">
            <x-icon name="info" />
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <nav class="category-tabs" aria-label="Filter status dokumen">
        @foreach($statusOptions as $key => $label)
            <a href="{{ route('kasir.dokumen.index', array_filter(['status' => $key, 'q' => $search])) }}"
               class="chip {{ $status === $key ? 'active' : '' }}"
               @if($status === $key) aria-current="page" @endif>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>

    <section class="table-panel">
        <form class="table-tools" action="{{ route('kasir.dokumen.index') }}" method="GET" role="search">
            @if($status !== '')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <div class="search-field">
                <x-icon name="search" />
                <label for="dokumen-search" class="sr-only">Cari nama, email, atau nomor SIM pelanggan</label>
                <input id="dokumen-search" name="q" type="search" value="{{ $search }}" placeholder="Nama, email, atau nomor SIM pelanggan">
            </div>
            <div class="inline-actions">
                <button type="submit" class="btn btn-dark">
                    <x-icon name="search" />
                    <span>Cari</span>
                </button>
                @if($search !== '' || $status !== '')
                    <a href="{{ route('kasir.dokumen.index') }}" class="btn btn-ghost">Reset</a>
                @endif
            </div>
        </form>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Berkas KTP &amp; SIM</th>
                        <th scope="col">Status</th>
                        <th scope="col">Verifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dokumenList as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->user->name ?? '-' }}</strong>
                                <small>{{ $item->user->email ?? '-' }}</small>
                                <small>SIM: {{ $item->user->sim_number ?? '-' }}</small>
                            </td>
                            <td>
                                <div class="inline-actions">
                                    @if($item->file_ktp)
                                        <a href="{{ route('files.document', [$item->user_id, 'ktp']) }}" target="_blank" rel="noopener" class="btn btn-outline btn-small">Lihat KTP</a>
                                    @else
                                        <span class="tiny muted">KTP belum ada</span>
                                    @endif
                                    @if($item->file_sim)
                                        <a href="{{ route('files.document', [$item->user_id, 'sim']) }}" target="_blank" rel="noopener" class="btn btn-outline btn-small">Lihat SIM</a>
                                    @else
                                        <span class="tiny muted">SIM belum ada</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $item->status_badge_class }}">{{ $item->status_label }}</span>
                                @if($item->catatan_verifikasi)
                                    <small>{{ $item->catatan_verifikasi }}</small>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('kasir.dokumen.verify', $item->id_dokumen) }}" method="POST" class="od-stack" style="--od-gap:6px; min-width:220px;">
                                    @csrf
                                    <input type="text" name="catatan_verifikasi" maxlength="500" value="{{ $item->catatan_verifikasi }}" placeholder="Catatan (wajib jika ditolak)" style="min-height:36px; padding:6px 10px; font-size:12px;">
                                    <div class="inline-actions">
                                        <button type="submit" name="status_verifikasi" value="disetujui" class="btn btn-primary btn-small">Setujui</button>
                                        <button type="submit" name="status_verifikasi" value="ditolak" class="btn btn-outline btn-small" style="color:var(--red);">Tolak</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="table-empty">
                                <x-icon name="shield" />
                                <span>Tidak ada dokumen pelanggan yang sesuai filter.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span>
                @if($dokumenList->total() > 0)
                    Menampilkan {{ $dokumenList->firstItem() }}&ndash;{{ $dokumenList->lastItem() }} dari {{ $dokumenList->total() }} dokumen
                @else
                    0 dokumen
                @endif
            </span>
            @if($dokumenList->hasPages())
                <div class="inline-actions">
                    @if(! $dokumenList->onFirstPage())
                        <a href="{{ $dokumenList->previousPageUrl() }}" class="btn btn-outline btn-small" rel="prev">
                            <x-icon name="back" />
                            <span>Sebelumnya</span>
                        </a>
                    @endif
                    @if($dokumenList->hasMorePages())
                        <a href="{{ $dokumenList->nextPageUrl() }}" class="btn btn-outline btn-small" rel="next">
                            <span>Berikutnya</span>
                            <x-icon name="arrow" />
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>
</x-layouts.staff>
