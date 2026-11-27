<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Rekap Absensi') | Tirtafield DS</title>
    <link rel="icon" type="image/png" href="{{ asset('build/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600&family=Montserrat:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col antialiased">
    @auth
        <header class="no-print sticky top-0 z-30 pusimpel-header">
            <div class="max-w-[1140px] mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between h-14 gap-4">
                    <div class="flex items-center gap-6 min-w-0">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 min-w-0 py-1 hover:opacity-90 transition-opacity">
                            <img src="{{ asset('build/favicon.png') }}" alt="" class="w-8 h-8 rounded-lg object-contain shrink-0" aria-hidden="true">
                            <span class="font-heading text-sm sm:text-base font-extrabold tracking-tight text-white truncate">
                                Tirtafield <span class="text-[#38bdf8]">DS</span>
                            </span>
                        </a>

                        <nav aria-label="Navigasi utama" class="hidden md:flex items-center gap-1.5">
                            <a href="{{ route('dashboard') }}"
                               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-colors {{ request()->routeIs('dashboard') ? 'bg-[#38bdf8] text-[#070c16]' : 'text-[#cbd5e1] hover:bg-white/10 hover:text-white' }}">
                                Beranda
                            </a>
                            <a href="{{ route('rekap.pegawai') }}"
                               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-colors {{ request()->routeIs('rekap.pegawai') ? 'bg-[#38bdf8] text-[#070c16]' : 'text-[#cbd5e1] hover:bg-white/10 hover:text-white' }}">
                                Pegawai
                            </a>
                            <a href="{{ route('rekap.non-tetap') }}"
                               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-colors {{ request()->routeIs('rekap.non-tetap') ? 'bg-[#38bdf8] text-[#070c16]' : 'text-[#cbd5e1] hover:bg-white/10 hover:text-white' }}">
                                Capeg · Honor · PKWT · Kontrak
                            </a>
                        </nav>
                    </div>

                    <div class="hidden md:flex items-center gap-3 shrink-0">
                        <span class="text-xs text-[#94a3b8] font-mono">{{ auth()->user()->nik }}</span>

                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="min-h-[36px] px-3.5 py-1.5 rounded-xl text-xs font-semibold inline-flex items-center pusimpel-btn-ghost">
                                Keluar
                            </button>
                        </form>
                    </div>

                    <button type="button"
                            id="mobileMenuToggle"
                            aria-expanded="false"
                            aria-controls="mobileMenuPanel"
                            aria-label="Buka menu navigasi"
                            class="md:hidden min-h-[40px] px-3 rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 pusimpel-btn-ghost">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <line x1="3" y1="12" x2="21" y2="12" />
                            <line x1="3" y1="18" x2="21" y2="18" />
                        </svg>
                        <span>Menu</span>
                    </button>
                </div>
            </div>

            <div id="mobileMenuPanel" class="hidden md:hidden border-t border-white/10 bg-[#0c1422] px-4 py-3 space-y-2">
                <nav aria-label="Navigasi seluler" class="flex flex-col gap-1">
                    <a href="{{ route('dashboard') }}"
                       class="min-h-[42px] px-3.5 py-2 rounded-xl text-sm font-semibold flex items-center {{ request()->routeIs('dashboard') ? 'bg-[#38bdf8] text-[#070c16]' : 'text-[#cbd5e1] hover:bg-white/10' }}">
                        Beranda
                    </a>
                    <a href="{{ route('rekap.pegawai') }}"
                       class="min-h-[42px] px-3.5 py-2 rounded-xl text-sm font-semibold flex items-center {{ request()->routeIs('rekap.pegawai') ? 'bg-[#38bdf8] text-[#070c16]' : 'text-[#cbd5e1] hover:bg-white/10' }}">
                        Rekap Absensi Pegawai
                    </a>
                    <a href="{{ route('rekap.non-tetap') }}"
                       class="min-h-[42px] px-3.5 py-2 rounded-xl text-sm font-semibold flex items-center {{ request()->routeIs('rekap.non-tetap') ? 'bg-[#38bdf8] text-[#070c16]' : 'text-[#cbd5e1] hover:bg-white/10' }}">
                        Rekap Capeg, Honor, PKWT, Kontrak
                    </a>
                </nav>
                <form action="{{ route('logout') }}" method="POST" class="pt-2 border-t border-white/10 flex items-center justify-between">
                    <span class="text-xs font-mono text-[#94a3b8]">{{ auth()->user()->nik }}</span>
                    @csrf
                    <button type="submit"
                            class="min-h-[40px] px-4 py-1.5 rounded-xl text-xs font-semibold pusimpel-btn-ghost">
                        Keluar
                    </button>
                </form>
            </div>
        </header>
    @endauth

    <main class="flex-1">
        @yield('content')
    </main>

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
