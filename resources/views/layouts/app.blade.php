<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal Rekap Absensi') | Tirtafield DS</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600&family=Montserrat:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col antialiased">
    @auth
        <header class="no-print sticky top-0 z-30 pusimpel-header">
            <div class="max-w-[1260px] mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between h-16 gap-4">
                    <div class="flex items-center gap-6 min-w-0">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0 rounded-xl py-1 pr-2 hover:opacity-95 transition-opacity">
                            <span class="w-10 h-10 icon-tile cat-pelanggan shrink-0" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                                    <path d="M9 14l2 2 4-4" />
                                </svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block font-heading text-base font-extrabold tracking-tight text-white truncate">
                                    Tirtafield <span class="text-[#38bdf8]">DS</span>
                                </span>
                                <span class="block text-xs text-[#94a3b8] truncate">PERUMDA Air Minum Tirta Kepri</span>
                            </span>
                        </a>

                        <nav aria-label="Navigasi utama" class="hidden md:flex items-center gap-2">
                            <a href="{{ route('dashboard') }}"
                               class="pill-btn min-h-[40px] {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                Beranda
                            </a>
                            <a href="{{ route('rekap.pegawai') }}"
                               class="pill-btn min-h-[40px] {{ request()->routeIs('rekap.pegawai') ? 'active' : '' }}">
                                <span class="category-dot dot-pelanggan" aria-hidden="true"></span>
                                <span>Rekap Pegawai</span>
                            </a>
                            <a href="{{ route('rekap.non-tetap') }}"
                               class="pill-btn min-h-[40px] {{ request()->routeIs('rekap.non-tetap') ? 'active' : '' }}">
                                <span class="category-dot dot-kepegawaian" aria-hidden="true"></span>
                                <span>Capeg, Honor, PKWT, Kontrak</span>
                            </a>
                        </nav>
                    </div>

                    <div class="hidden md:flex items-center gap-3 shrink-0">
                        <div class="text-right">
                            <div class="text-xs font-semibold text-[#f1f5f9] truncate max-w-[190px]">{{ auth()->user()->name }}</div>
                            <div class="text-xs font-mono text-[#94a3b8]">{{ auth()->user()->nik }} · {{ auth()->user()->roleLabel() }}</div>
                        </div>

                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="min-h-[44px] px-4 py-2 rounded-full text-xs font-semibold inline-flex items-center gap-2 pusimpel-btn-ghost">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                    <polyline points="16 17 21 12 16 7" />
                                    <line x1="21" y1="12" x2="9" y2="12" />
                                </svg>
                                <span>Keluar</span>
                            </button>
                        </form>
                    </div>

                    <button type="button"
                            id="mobileMenuToggle"
                            aria-expanded="false"
                            aria-controls="mobileMenuPanel"
                            aria-label="Buka menu navigasi"
                            class="md:hidden min-h-[44px] min-w-[44px] px-3.5 rounded-full text-xs font-semibold inline-flex items-center gap-2 pusimpel-btn-ghost">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <line x1="3" y1="12" x2="21" y2="12" />
                            <line x1="3" y1="18" x2="21" y2="18" />
                        </svg>
                        <span>Menu</span>
                    </button>
                </div>
            </div>

            <div id="mobileMenuPanel" class="hidden md:hidden border-t border-white/10 bg-[#0c1422] px-4 py-3 space-y-2.5">
                <div class="pb-2 border-b border-white/10">
                    <div class="text-sm font-semibold text-white">{{ auth()->user()->name }}</div>
                    <div class="text-xs font-mono text-[#94a3b8]">{{ auth()->user()->nik }} · {{ auth()->user()->roleLabel() }}</div>
                </div>
                <nav aria-label="Navigasi seluler" class="flex flex-col gap-1.5">
                    <a href="{{ route('dashboard') }}"
                       class="min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold flex items-center {{ request()->routeIs('dashboard') ? 'bg-[#38bdf8] text-[#070c16]' : 'text-[#cbd5e1] hover:bg-white/10' }}">
                        Beranda
                    </a>
                    <a href="{{ route('rekap.pegawai') }}"
                       class="min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold flex items-center {{ request()->routeIs('rekap.pegawai') ? 'bg-[#38bdf8] text-[#070c16]' : 'text-[#cbd5e1] hover:bg-white/10' }}">
                        Rekap Absensi Pegawai
                    </a>
                    <a href="{{ route('rekap.non-tetap') }}"
                       class="min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold flex items-center {{ request()->routeIs('rekap.non-tetap') ? 'bg-[#38bdf8] text-[#070c16]' : 'text-[#cbd5e1] hover:bg-white/10' }}">
                        Rekap Capeg, Honor, PKWT, Kontrak
                    </a>
                </nav>
                <form action="{{ route('logout') }}" method="POST" class="pt-2 border-t border-white/10">
                    @csrf
                    <button type="submit"
                            class="w-full min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold text-left pusimpel-btn-ghost">
                        Keluar dari Portal
                    </button>
                </form>
            </div>
        </header>
    @endauth

    <main class="flex-1">
        @if (session('status'))
            <div class="no-print max-w-[1260px] mx-auto px-4 sm:px-6 pt-4">
                <div role="status" class="rounded-2xl border px-4 py-3 text-sm font-semibold flex items-center gap-2.5 badge-em">
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

    <footer class="no-print mt-12 border-t border-white/10">
        <div class="max-w-[1260px] mx-auto px-4 sm:px-6 py-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-[#94a3b8]">
            <span>Tirtafield DS · Portal Rekapitulasi Presensi PERUMDA Air Minum Tirta Kepri</span>
            <span>Sumber data: Database Tirtafield (Asia/Jakarta)</span>
        </div>
    </footer>

    <script>
        (function () {
            const toggle = document.getElementById('mobileMenuToggle');
            const panel = document.getElementById('mobileMenuPanel');
            if (!toggle || !panel) {
                return;
            }

            toggle.addEventListener('click', function () {
                const isOpen = !panel.classList.contains('hidden');
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
        })();
    </script>
    @stack('scripts')
</body>
</html>
