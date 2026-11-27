@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
<div class="max-w-[1260px] mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-8">
    <section aria-labelledby="portal-heading" class="text-center space-y-2">
        <h1 id="portal-heading" class="text-2xl sm:text-3xl font-extrabold user-name-gradient">
            Rekap Absensi
        </h1>
        <p class="text-sm text-[#94a3b8]">
            Periode {{ \Carbon\Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('F Y') }}
        </p>
    </section>

    @if ($dbError)
        <div role="alert" class="rounded-2xl border p-4 text-sm badge-rose">
            {{ $dbError }}
        </div>
    @endif

    <section aria-label="Pilihan rekap absensi" class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
        {{-- Action Button 1: Rekap Absensi Pegawai --}}
        <a href="{{ route('rekap.pegawai') }}"
           class="portal-action-card p-6 flex flex-col justify-between gap-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-14 h-14 icon-tile cat-pelanggan shrink-0" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-heading text-lg sm:text-xl font-bold text-white leading-snug">
                            Rekap Absensi Pegawai
                        </h2>
                        <p class="text-xs text-[#94a3b8] mt-1">
                            Pegawai Tetap &amp; Direksi
                        </p>
                    </div>
                </div>
                @if ($summary !== null)
                    <span class="counter-badge font-mono tabular-nums shrink-0">
                        {{ $summary['pegawai_count'] }} Personil
                    </span>
                @endif
            </div>

            <div class="pt-4 border-t border-white/10 flex items-center justify-between gap-3">
                <span class="text-xs text-[#94a3b8]">
                    @if ($summary !== null)
                        Hadir bulan ini: <strong class="font-mono text-white">{{ $summary['pegawai_presensi_count'] }}</strong>
                    @endif
                </span>
                <span class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-full text-sm pusimpel-btn-primary shrink-0">
                    Buka Rekap
                </span>
            </div>
        </a>

        {{-- Action Button 2: Rekap Absensi Capeg, Honor, PKWT, Kontrak --}}
        <a href="{{ route('rekap.non-tetap') }}"
           class="portal-action-card p-6 flex flex-col justify-between gap-6">
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 icon-tile cat-kepegawaian shrink-0" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                                <path d="M9 16l2 2 4-4" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-heading text-lg sm:text-xl font-bold text-white leading-snug">
                                Rekap Absensi Capeg, Honor, PKWT, Kontrak
                            </h2>
                        </div>
                    </div>
                    @if ($summary !== null)
                        <span class="counter-badge font-mono tabular-nums shrink-0">
                            {{ $summary['non_tetap_count'] }} Personil
                        </span>
                    @endif
                </div>

                @if ($summary !== null)
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($summary['non_tetap_breakdown'] as $label => $total)
                            <span class="counter-badge inline-flex items-center gap-1.5 text-[11px]">
                                <span>{{ $label }}</span>
                                <span class="font-mono font-bold text-white">{{ $total }}</span>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pt-4 border-t border-white/10 flex items-center justify-between gap-3">
                <span class="text-xs text-[#94a3b8]">
                    @if ($summary !== null)
                        Hadir bulan ini: <strong class="font-mono text-white">{{ $summary['non_tetap_presensi_count'] }}</strong>
                    @endif
                </span>
                <span class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-full text-sm pusimpel-btn-primary shrink-0">
                    Buka Rekap
                </span>
            </div>
        </a>
    </section>
</div>
@endsection
