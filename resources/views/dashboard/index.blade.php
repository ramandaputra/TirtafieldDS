@extends('layouts.app')

@section('title', 'Beranda Rekap Absensi')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8">
    <section aria-labelledby="portal-heading" class="space-y-2">
        <h1 id="portal-heading" class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#0F172A]">
            Portal Rekapitulasi Absensi
        </h1>
        <p class="text-sm sm:text-base text-[#475569] max-w-2xl">
            Pilih jenis rekap absensi di bawah ini untuk melihat laporan kehadiran, rincian jam presensi, dan ekspor data dari database Tirtafield.
        </p>
    </section>

    @if ($dbError)
        <div role="alert" class="rounded-xl border border-rose-300 bg-[#FFF1F2] p-4 text-sm text-[#9F1239] flex items-start gap-3">
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

    <section aria-label="Pilihan rekap absensi" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Action Button 1: Rekap Absensi Pegawai --}}
        <a href="{{ route('rekap.pegawai') }}"
           class="group flex flex-col justify-between bg-white border-2 border-[#00288E] rounded-2xl p-6 sm:p-7 shadow-xs hover:shadow-md hover:bg-[#F8FAFF] active:bg-[#EFF6FF] transition-all min-h-[220px]">
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="w-12 h-12 rounded-xl bg-[#00288E] text-white flex items-center justify-center shrink-0" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>
                    @if ($summary !== null)
                        <span class="inline-flex items-center px-3 py-1 rounded-lg bg-[#EFF6FF] border border-[#BFDBFE] text-xs font-bold text-[#1E3A8A] font-mono tabular-nums">
                            {{ $summary['pegawai_count'] }} Personil
                        </span>
                    @endif
                </div>

                <div>
                    <h2 class="text-xl font-extrabold text-[#0F172A] tracking-tight">
                        Rekap Absensi Pegawai
                    </h2>
                    <p class="text-sm text-[#475569] mt-1.5 leading-relaxed">
                        Laporan kehadiran untuk Pegawai Tetap dan Direksi, lengkap dengan rekap harian, status ketepatan waktu, dan rincian 4 slot presensi.
                    </p>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-200 flex items-center justify-between gap-3">
                <span class="text-xs font-medium text-[#475569]">
                    @if ($summary !== null)
                        Tercatat presensi bulan ini: <strong class="font-mono text-[#0F172A]">{{ $summary['pegawai_presensi_count'] }}</strong> orang
                    @else
                        Buka laporan kehadiran pegawai
                    @endif
                </span>
                <span class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 rounded-xl bg-[#00288E] group-hover:bg-[#001F70] text-white text-sm font-bold transition-colors shrink-0">
                    Buka Rekap Pegawai
                </span>
            </div>
        </a>

        {{-- Action Button 2: Rekap Absensi Capeg, Honor, PKWT, Kontrak --}}
        <a href="{{ route('rekap.non-tetap') }}"
           class="group flex flex-col justify-between bg-white border-2 border-[#0369A1] rounded-2xl p-6 sm:p-7 shadow-xs hover:shadow-md hover:bg-[#F0F9FF] active:bg-[#E0F2FE] transition-all min-h-[220px]">
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="w-12 h-12 rounded-xl bg-[#0369A1] text-white flex items-center justify-center shrink-0" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                            <path d="M9 16l2 2 4-4" />
                        </svg>
                    </div>
                    @if ($summary !== null)
                        <span class="inline-flex items-center px-3 py-1 rounded-lg bg-[#E0F2FE] border border-[#BAE6FD] text-xs font-bold text-[#075985] font-mono tabular-nums">
                            {{ $summary['non_tetap_count'] }} Personil
                        </span>
                    @endif
                </div>

                <div>
                    <h2 class="text-xl font-extrabold text-[#0F172A] tracking-tight">
                        Rekap Absensi Capeg, Honor, PKWT, Kontrak
                    </h2>
                    <p class="text-sm text-[#475569] mt-1.5 leading-relaxed">
                        Laporan kehadiran terpadu dengan filter sub-status untuk Calon Pegawai (Capeg), tenaga Honor, PKWT, dan Kontrak.
                    </p>
                </div>

                @if ($summary !== null)
                    <div class="flex flex-wrap gap-2 pt-1">
                        @foreach ($summary['non_tetap_breakdown'] as $label => $total)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-100 border border-slate-200 text-xs font-semibold text-[#0F172A]">
                                <span>{{ $label }}:</span>
                                <span class="font-mono font-bold">{{ $total }}</span>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-6 pt-4 border-t border-slate-200 flex items-center justify-between gap-3">
                <span class="text-xs font-medium text-[#475569]">
                    @if ($summary !== null)
                        Tercatat presensi bulan ini: <strong class="font-mono text-[#0F172A]">{{ $summary['non_tetap_presensi_count'] }}</strong> orang
                    @else
                        Buka laporan non-tetap
                    @endif
                </span>
                <span class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 rounded-xl bg-[#0369A1] group-hover:bg-[#075985] text-white text-sm font-bold transition-colors shrink-0">
                    Buka Rekap Non-Tetap
                </span>
            </div>
        </a>
    </section>
</div>
@endsection
