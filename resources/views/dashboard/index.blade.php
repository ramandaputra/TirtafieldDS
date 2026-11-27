@extends('layouts.app')

@section('title', 'Beranda Rekap Absensi')

@section('content')
<div class="max-w-[1260px] mx-auto px-4 sm:px-6 py-8 sm:py-10">
    <section aria-labelledby="portal-heading" class="hero-section text-center max-w-[980px] mx-auto">
        <div class="profile-container mx-auto mb-4" aria-hidden="true">
            <div class="w-full h-full icon-tile cat-teknik">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                    <path d="M9 16l2 2 4-4" />
                </svg>
            </div>
        </div>

        <h1 id="portal-heading" class="text-2xl sm:text-3xl font-extrabold user-name-gradient">
            Portal Rekapitulasi Absensi
        </h1>
        <p class="welcome-subtext text-sm sm:text-base max-w-[600px] mx-auto mt-2">
            Pilih kategori rekap absensi di bawah ini untuk melihat laporan kehadiran, rincian jam presensi, dan ekspor data dari database <strong>Tirtafield</strong>.
        </p>
    </section>

    <hr class="divider-glass my-7">

    @if ($dbError)
        <div role="alert" class="mb-6 rounded-2xl border p-4 text-sm flex items-start gap-3 badge-rose">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <div>
                <p class="font-bold">Koneksi database terkendala</p>
                <p class="mt-0.5">{{ $dbError }}</p>
            </div>
        </div>
    @endif

    <section aria-label="Pilihan rekap absensi" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-heading text-lg font-bold text-white flex items-center gap-2">
                <span class="category-dot dot-teknik" aria-hidden="true"></span>
                <span>Pilih Jenis Rekap Absensi</span>
            </h2>
            <span class="counter-badge">2 Modul Rekap</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Action Button 1: Rekap Absensi Pegawai (Kategori Pelanggan/Biru Pusimpel) --}}
            <a href="{{ route('rekap.pegawai') }}"
               class="portal-action-card p-6 sm:p-7 flex flex-col justify-between min-h-[245px]">
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="w-16 h-16 icon-tile cat-pelanggan shrink-0" aria-hidden="true">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                        </div>
                        @if ($summary !== null)
                            <span class="counter-badge font-mono tabular-nums">
                                {{ $summary['pegawai_count'] }} Personil
                            </span>
                        @endif
                    </div>

                    <div>
                        <h3 class="font-heading text-xl font-bold text-white">
                            Rekap Absensi Pegawai
                        </h3>
                        <p class="text-sm text-[#cbd5e1] mt-1.5 leading-relaxed">
                            Laporan kehadiran untuk Pegawai Tetap dan Direksi, lengkap dengan rekap harian, ketepatan waktu, dan rincian 4 sesi presensi.
                        </p>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between gap-3">
                    <span class="text-xs text-[#94a3b8]">
                        @if ($summary !== null)
                            Presensi bulan ini: <strong class="font-mono text-[#e2e8f0]">{{ $summary['pegawai_presensi_count'] }}</strong> orang
                        @else
                            Buka laporan kehadiran pegawai
                        @endif
                    </span>
                    <span class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-full text-sm pusimpel-btn-primary shrink-0">
                        Buka Rekap Pegawai
                    </span>
                </div>
            </a>

            {{-- Action Button 2: Rekap Absensi Capeg, Honor, PKWT, Kontrak (Kategori Kepegawaian/Ungu Pusimpel) --}}
            <a href="{{ route('rekap.non-tetap') }}"
               class="portal-action-card p-6 sm:p-7 flex flex-col justify-between min-h-[245px]">
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="w-16 h-16 icon-tile cat-kepegawaian shrink-0" aria-hidden="true">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                                <path d="M9 16l2 2 4-4" />
                            </svg>
                        </div>
                        @if ($summary !== null)
                            <span class="counter-badge font-mono tabular-nums">
                                {{ $summary['non_tetap_count'] }} Personil
                            </span>
                        @endif
                    </div>

                    <div>
                        <h3 class="font-heading text-xl font-bold text-white">
                            Rekap Absensi Capeg, Honor, PKWT, Kontrak
                        </h3>
                        <p class="text-sm text-[#cbd5e1] mt-1.5 leading-relaxed">
                            Laporan kehadiran terpadu dengan filter sub-status untuk Calon Pegawai (Capeg), tenaga Honor, PKWT, dan Kontrak.
                        </p>
                    </div>

                    @if ($summary !== null)
                        <div class="flex flex-wrap gap-2 pt-1">
                            @foreach ($summary['non_tetap_breakdown'] as $label => $total)
                                <span class="counter-badge inline-flex items-center gap-1.5">
                                    <span>{{ $label }}:</span>
                                    <span class="font-mono font-bold text-white">{{ $total }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between gap-3">
                    <span class="text-xs text-[#94a3b8]">
                        @if ($summary !== null)
                            Presensi bulan ini: <strong class="font-mono text-[#e2e8f0]">{{ $summary['non_tetap_presensi_count'] }}</strong> orang
                        @else
                            Buka laporan non-tetap
                        @endif
                    </span>
                    <span class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-full text-sm pusimpel-btn-primary shrink-0">
                        Buka Rekap Non-Tetap
                    </span>
                </div>
            </a>
        </div>
    </section>
</div>
@endsection
