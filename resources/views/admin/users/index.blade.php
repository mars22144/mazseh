<x-layouts.staff title="Pengguna" heading="Pengguna">
    <div class="page-title">
        <div>
            <h1>Pengguna</h1>
            <p>Atur peran staf dan verifikasi dokumen KTP & SIM pelanggan.</p>
        </div>
    </div>

    <nav class="category-tabs" aria-label="Filter pengguna">
        <a href="{{ route('admin.users.index') }}" class="chip {{ ! $role && ! $onlyPendingDocuments ? 'active' : '' }}"><span>Semua</span></a>
        @foreach($roles as $key => $label)
            <a href="{{ route('admin.users.index', ['peran' => $key]) }}" class="chip {{ $role === $key ? 'active' : '' }}"><span>{{ $label }}</span></a>
        @endforeach
        <a href="{{ route('admin.users.index', ['dokumen' => 1]) }}" class="chip {{ $onlyPendingDocuments ? 'active' : '' }}">
            <span>Dokumen menunggu</span>
            <span class="chip-count">{{ $pendingDocumentCount }}</span>
        </a>
    </nav>

    <section class="table-panel">
        <form class="table-tools" action="{{ route('admin.users.index') }}" method="GET" role="search">
            @if($role)<input type="hidden" name="peran" value="{{ $role }}">@endif
            @if($onlyPendingDocuments)<input type="hidden" name="dokumen" value="1">@endif
            <div class="search-field">
                <x-icon name="search" />
                <label for="user-search" class="sr-only">Cari pengguna</label>
                <input id="user-search" name="q" type="search" value="{{ $search }}" placeholder="Nama atau email">
            </div>
            <button type="submit" class="btn btn-dark"><x-icon name="search" /><span>Cari</span></button>
        </form>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Pengguna</th>
                        <th scope="col">Dokumen KTP & SIM</th>
                        <th scope="col">Peran</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php($document = $user->dokumenPelanggan)
                        <tr>
                            <td>
                                <div class="od-row" style="--od-gap:12px;">
                                    <span class="avatar">{{ $user->initials }}</span>
                                    <div>
                                        <strong>{{ $user->name }}</strong>
                                        <small>{{ $user->email }} &bull; {{ $user->transaksi_count }} pesanan</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($document)
                                    <span class="badge {{ $document->status_badge_class }}"><x-icon name="shield" />{{ $document->status_label }}</span>
                                    <small class="inline-actions" style="margin-top:6px;">
                                        @if($document->file_ktp)<a href="{{ $document->file_ktp }}" target="_blank" rel="noopener" class="text-link">KTP</a>@endif
                                        @if($document->file_sim)<a href="{{ $document->file_sim }}" target="_blank" rel="noopener" class="text-link">SIM</a>@endif
                                    </small>
                                    @if($document->status_verifikasi === 'menunggu_validasi')
                                        <form action="{{ route('admin.users.document', $user->id) }}" method="POST" class="inline-actions" style="margin-top:8px;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" name="status" value="disetujui" class="btn btn-outline btn-small" style="color:var(--green);">Setujui</button>
                                            <button type="submit" name="status" value="ditolak" class="btn btn-outline btn-small" style="color:var(--red);">Tolak</button>
                                        </form>
                                    @endif
                                @else
                                    <span class="badge">Belum upload</span>
                                @endif
                            </td>
                            <td>
                                @if($user->is(Auth::user()))
                                    <span class="badge blue">{{ $user->role_label }} (Anda)</span>
                                @else
                                    <form action="{{ route('admin.users.role', $user->id) }}" method="POST" class="inline-actions">
                                        @csrf
                                        @method('PATCH')
                                        <label for="role-{{ $user->id }}" class="sr-only">Peran {{ $user->name }}</label>
                                        <select id="role-{{ $user->id }}" name="role" style="width:auto; min-height:40px;">
                                            @foreach($roles as $key => $label)
                                                <option value="{{ $key }}" @selected($user->role === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-outline btn-small">Simpan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="table-empty">Tidak ada pengguna yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span>{{ $users->total() }} pengguna</span>
            @if($users->hasPages())
                <div class="inline-actions">
                    @if(! $users->onFirstPage())<a href="{{ $users->previousPageUrl() }}" class="btn btn-outline btn-small" rel="prev"><x-icon name="back" /><span>Sebelumnya</span></a>@endif
                    @if($users->hasMorePages())<a href="{{ $users->nextPageUrl() }}" class="btn btn-outline btn-small" rel="next"><span>Berikutnya</span><x-icon name="arrow" /></a>@endif
                </div>
            @endif
        </div>
    </section>
</x-layouts.staff>
