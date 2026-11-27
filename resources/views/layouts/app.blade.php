<!DOCTYPE html>
<html lang="id" data-theme="vice-night">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal Rekap Absensi') | Tirtafield DS</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=JetBrains+Mono:wght@500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('tirtafield_theme');
                if (saved === 'vice-day' || saved === 'vice-night') {
                    document.documentElement.setAttribute('data-theme', saved);
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col antialiased" style="background-color: var(--bg-canvas); color: var(--text-primary);">
    @auth
        <header class="no-print sticky top-0 z-30 border-b" style="background-color: var(--bg-surface); border-color: var(--border-subtle);">
            <div class="h-1 vice-horizon-bar w-full" aria-hidden="true"></div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16 gap-4">
                    <div class="flex items-center gap-6 min-w-0">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0 rounded-lg py-1 pr-2 transition-colors">
                            <span class="w-10 h-10 rounded-lg vice-btn-coral flex items-center justify-center shrink-0 border" style="border-color: var(--accent-gold);" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                                    <path d="M9 14l2 2 4-4" />
                                </svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block font-vice-display text-xl font-extrabold tracking-wide uppercase truncate" style="color: var(--text-primary);">
                                    Tirtafield <span style="color: var(--accent-gold);">DS</span>
                                </span>
                                <span class="block text-xs font-medium truncate" style="color: var(--text-muted);">
                                    PERUMDA Air Minum Tirta Kepri
                                </span>
                            </span>
                        </a>

                        <nav aria-label="Navigasi utama" class="hidden md:flex items-center gap-1.5">
                            <a href="{{ route('dashboard') }}"
                               class="px-3.5 py-2 rounded-lg text-sm font-semibold min-h-[44px] inline-flex items-center border transition-colors {{ request()->routeIs('dashboard') ? 'vice-badge-cr' : 'border-transparent hover:opacity-90' }}"
                               @if (! request()->routeIs('dashboard')) style="color: var(--text-secondary);" @endif>
                                Beranda
                            </a>
                            <a href="{{ route('rekap.pegawai') }}"
                               class="px-3.5 py-2 rounded-lg text-sm font-semibold min-h-[44px] inline-flex items-center border transition-colors {{ request()->routeIs('rekap.pegawai') ? 'vice-badge-cr' : 'border-transparent hover:opacity-90' }}"
                               @if (! request()->routeIs('rekap.pegawai')) style="color: var(--text-secondary);" @endif>
                                Rekap Pegawai
                            </a>
                            <a href="{{ route('rekap.non-tetap') }}"
                               class="px-3.5 py-2 rounded-lg text-sm font-semibold min-h-[44px] inline-flex items-center border transition-colors {{ request()->routeIs('rekap.non-tetap') ? 'vice-badge-tl' : 'border-transparent hover:opacity-90' }}"
                               @if (! request()->routeIs('rekap.non-tetap')) style="color: var(--text-secondary);" @endif>
                                Capeg, Honor, PKWT, Kontrak
                            </a>
                        </nav>
                    </div>

                    <div class="hidden md:flex items-center gap-3 shrink-0">
                        <button type="button"
                                data-theme-toggle
                                aria-label="Ubah tema malam atau siang"
                                class="min-h-[44px] px-3 py-2 rounded-lg border text-xs font-bold transition-colors inline-flex items-center gap-2 vice-btn-ghost">
                            <span class="theme-label">Mode Siang</span>
                        </button>

                        <div class="text-right pl-2 border-l" style="border-color: var(--border-subtle);">
                            <div class="text-xs font-bold truncate max-w-[180px]" style="color: var(--text-primary);">{{ auth()->user()->name }}</div>
                            <div class="text-xs font-mono" style="color: var(--text-muted);">{{ auth()->user()->nik }} · {{ auth()->user()->roleLabel() }}</div>
                        </div>

                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="min-h-[44px] px-3.5 py-2 rounded-lg border text-sm font-semibold transition-colors inline-flex items-center gap-2 vice-btn-ghost">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                    <polyline points="16 17 21 12 16 7" />
                                    <line x1="21" y1="12" x2="9" y2="12" />
                                </svg>
                                <span>Keluar</span>
                            </button>
                        </form>
                    </div>

                    <div class="flex md:hidden items-center gap-2">
                        <button type="button"
                                data-theme-toggle
                                aria-label="Ubah tema malam atau siang"
                                class="min-h-[44px] px-3 rounded-lg border text-xs font-bold vice-btn-ghost">
                            <span class="theme-label">Siang</span>
                        </button>

                        <button type="button"
                                id="mobileMenuToggle"
                                aria-expanded="false"
                                aria-controls="mobileMenuPanel"
                                aria-label="Buka menu navigasi"
                                class="min-h-[44px] min-w-[44px] px-3 rounded-lg border text-sm font-semibold inline-flex items-center gap-2 vice-btn-ghost">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="3" y1="6" x2="21" y2="6" />
                                <line x1="3" y1="12" x2="21" y2="12" />
                                <line x1="3" y1="18" x2="21" y2="18" />
                            </svg>
                            <span>Menu</span>
                        </button>
                    </div>
                </div>
            </div>

            <div id="mobileMenuPanel" class="hidden md:hidden border-t px-4 py-3 space-y-2" style="background-color: var(--bg-surface); border-color: var(--border-subtle);">
                <div class="pb-2 border-b" style="border-color: var(--border-subtle);">
                    <div class="text-sm font-bold" style="color: var(--text-primary);">{{ auth()->user()->name }}</div>
                    <div class="text-xs font-mono" style="color: var(--text-muted);">{{ auth()->user()->nik }} · {{ auth()->user()->roleLabel() }}</div>
                </div>
                <nav aria-label="Navigasi seluler" class="flex flex-col gap-1.5">
                    <a href="{{ route('dashboard') }}"
                       class="min-h-[44px] px-3 py-2 rounded-lg text-sm font-semibold flex items-center border {{ request()->routeIs('dashboard') ? 'vice-badge-cr' : 'border-transparent' }}"
                       @if (! request()->routeIs('dashboard')) style="color: var(--text-primary);" @endif>
                        Beranda
                    </a>
                    <a href="{{ route('rekap.pegawai') }}"
                       class="min-h-[44px] px-3 py-2 rounded-lg text-sm font-semibold flex items-center border {{ request()->routeIs('rekap.pegawai') ? 'vice-badge-cr' : 'border-transparent' }}"
                       @if (! request()->routeIs('rekap.pegawai')) style="color: var(--text-primary);" @endif>
                        Rekap Absensi Pegawai
                    </a>
                    <a href="{{ route('rekap.non-tetap') }}"
                       class="min-h-[44px] px-3 py-2 rounded-lg text-sm font-semibold flex items-center border {{ request()->routeIs('rekap.non-tetap') ? 'vice-badge-tl' : 'border-transparent' }}"
                       @if (! request()->routeIs('rekap.non-tetap')) style="color: var(--text-primary);" @endif>
                        Rekap Capeg, Honor, PKWT, Kontrak
                    </a>
                </nav>
                <form action="{{ route('logout') }}" method="POST" class="pt-2 border-t" style="border-color: var(--border-subtle);">
                    @csrf
                    <button type="submit"
                            class="w-full min-h-[44px] px-3 py-2 rounded-lg border text-sm font-semibold text-left vice-btn-ghost">
                        Keluar dari Portal
                    </button>
                </form>
            </div>
        </header>
    @endauth

    <main class="flex-1">
        @if (session('status'))
            <div class="no-print max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                <div role="status" class="rounded-lg border px-4 py-3 text-sm font-semibold flex items-center gap-2.5 vice-badge-em">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="no-print border-t mt-auto" style="background-color: var(--bg-surface); border-color: var(--border-subtle);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs" style="color: var(--text-muted);">
            <span>Tirtafield DS · Portal Rekapitulasi Presensi PERUMDA Air Minum Tirta Kepri</span>
            <span>Sumber data: Database Tirtafield (Asia/Jakarta)</span>
        </div>
    </footer>

    <script>
        (function () {
            function syncThemeLabels() {
                var current = document.documentElement.getAttribute('data-theme') || 'vice-night';
                var nextLabel = current === 'vice-night' ? 'Mode Siang' : 'Mode Malam';
                document.querySelectorAll('.theme-label').forEach(function (el) {
                    el.textContent = nextLabel;
                });
            }

            syncThemeLabels();

            document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var current = document.documentElement.getAttribute('data-theme') || 'vice-night';
                    var next = current === 'vice-night' ? 'vice-day' : 'vice-night';
                    document.documentElement.setAttribute('data-theme', next);
                    try {
                        localStorage.setItem('tirtafield_theme', next);
                    } catch (e) {}
                    syncThemeLabels();
                });
            });

            var toggle = document.getElementById('mobileMenuToggle');
            var panel = document.getElementById('mobileMenuPanel');
            if (toggle && panel) {
                toggle.addEventListener('click', function () {
                    var isOpen = !panel.classList.contains('hidden');
                    panel.classList.toggle('hidden', isOpen);
                    toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && !panel.classList.contains('hidden')) {
                        panel.classList.add('hidden');
                        toggle.setAttribute('aria-expanded', 'false');
                        toggle.focus();
                    }
                });
            }
        })();
    </script>
    @stack('scripts')
</body>
</html>
