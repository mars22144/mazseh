@props(['title' => 'Panel Kasir', 'heading' => 'Panel Kasir'])

@php
    /** @var \App\Models\User $staff */
    $staff = Auth::user();
    $awaitingVerificationCount = \App\Models\Pembayaran::awaitingVerification()->count();
@endphp

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | Kasir MAZSEH</title>

    <link rel="preload" href="{{ asset('assets/inter-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/mazseh.css') }}">
    @stack('styles')
</head>
<body>
    <a class="skip-link" href="#main">Lewati ke konten utama</a>

    <div class="workspace">
        <aside class="sidebar" id="kasir-sidebar" aria-label="Navigasi panel kasir">
            <a class="brand" href="{{ route('kasir.dashboard') }}">
                <div>
                    <span class="brand-name">MAZ<span>SEH</span></span>
                    <span class="brand-caption">PANEL KASIR</span>
                </div>
            </a>

            <nav class="sidebar-nav">
                <span class="workspace-label">OPERASIONAL</span>
                <a href="{{ route('kasir.dashboard') }}" class="sidebar-link {{ request()->routeIs('kasir.dashboard') ? 'active' : '' }}" @if(request()->routeIs('kasir.dashboard')) aria-current="page" @endif>
                    <x-icon name="dashboard" />
                    <span>Ringkasan</span>
                </a>
                <a href="{{ route('kasir.payments.index') }}" class="sidebar-link {{ request()->routeIs('kasir.payments.*') ? 'active' : '' }}" @if(request()->routeIs('kasir.payments.*')) aria-current="page" @endif>
                    <x-icon name="wallet" />
                    <span>Pembayaran</span>
                    @if($awaitingVerificationCount > 0)
                        <span class="nav-count" aria-label="{{ $awaitingVerificationCount }} menunggu verifikasi">{{ $awaitingVerificationCount }}</span>
                    @endif
                </a>

                <span class="workspace-label">SITUS</span>
                <a href="{{ route('home') }}" class="sidebar-link">
                    <x-icon name="car" />
                    <span>Situs pelanggan</span>
                </a>
            </nav>

            <div class="sidebar-account">
                <span class="avatar">{{ $staff->initials }}</span>
                <div class="grow" style="min-width:0;">
                    <strong class="od-truncate">{{ $staff->name }}</strong>
                    <small>{{ $staff->role_label }} &bull; {{ $staff->city ?? 'MAZSEH' }}</small>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="icon-btn" aria-label="Keluar akun" title="Keluar">
                        <x-icon name="logout" />
                    </button>
                </form>
            </div>
        </aside>
        <div class="sidebar-scrim" id="kasir-sidebar-scrim"></div>

        <div class="workspace-main">
            <header class="workspace-header">
                <div class="workspace-header-title">
                    <button type="button" class="icon-btn mobile-sidebar-toggle" id="kasir-sidebar-toggle" aria-label="Buka menu panel kasir" aria-controls="kasir-sidebar" aria-expanded="false">
                        <x-icon name="menu" />
                    </button>
                    <span>Kasir</span>
                    <x-icon name="chevron" style="--od-icon-size:14px;" />
                    <strong style="color:var(--ink);">{{ $heading }}</strong>
                </div>
                <div class="header-actions">
                    <span class="tiny muted">{{ now()->translatedFormat('l, d F Y') }}</span>
                </div>
            </header>

            <main id="main" class="workspace-content">
                @if(session('success'))
                    <div class="notice success" style="margin-bottom:24px;" role="status">
                        <x-icon name="check" />
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="notice error" style="margin-bottom:24px;" role="alert">
                        <x-icon name="info" />
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                {{ $slot }}

                <p class="workspace-footer">&copy; {{ date('Y') }} MAZSEH &bull; Panel kasir internal. Setiap verifikasi tercatat atas nama Anda.</p>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('kasir-sidebar');
            const scrim = document.getElementById('kasir-sidebar-scrim');
            const toggle = document.getElementById('kasir-sidebar-toggle');
            if (!sidebar || !scrim || !toggle) return;

            function setOpen(isOpen) {
                sidebar.classList.toggle('is-open', isOpen);
                scrim.classList.toggle('is-open', isOpen);
                toggle.setAttribute('aria-expanded', String(isOpen));
            }

            toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('is-open')));
            scrim.addEventListener('click', () => setOpen(false));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setOpen(false);
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
