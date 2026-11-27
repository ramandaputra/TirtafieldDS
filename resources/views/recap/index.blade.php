@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
@php
    $isPegawai = $category === \App\Services\AttendanceRecapService::CATEGORY_PEGAWAI;
    $currentRouteName = $isPegawai ? 'rekap.pegawai' : 'rekap.non-tetap';
    $bulan = $recap['bulan'] ?? now('Asia/Jakarta')->format('m');
    $tahun = $recap['tahun'] ?? now('Asia/Jakarta')->format('Y');
    $subStatus = $recap['sub_status'] ?? '';
    $statusBreakdown = $recap['status_breakdown'] ?? [];
    $personnel = $recap['personnel'] ?? collect();
    $selectedPerson = $recap['selected_person'] ?? null;
    $dailyLogs = $recap['daily_logs'] ?? collect();
    $totals = $recap['totals'] ?? [];
    $namaBulanList = $recap['nama_bulan_list'] ?? [];
    $tahunList = $recap['tahun_list'] ?? [$tahun];
@endphp

<div class="max-w-[1160px] mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-5">
    {{-- Header --}}
    <div class="no-print space-y-1.5">
        <a href="{{ route('dashboard') }}"
           class="inline-flex items-center gap-1.5 min-h-[40px] px-2 -ml-2 rounded-full text-xs font-semibold text-[#38bdf8] hover:bg-white/10 transition-colors">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12" />
                <polyline points="12 19 5 12 12 5" />
            </svg>
            <span>Kembali</span>
        </a>
        <h1 class="text-2xl sm:text-3xl font-extrabold user-name-gradient">
            {{ $pageTitle }}
        </h1>
    </div>

    @if ($dbError)
        <div role="alert" class="rounded-2xl border p-4 text-sm badge-rose">
            {{ $dbError }}
        </div>
    @else
        {{-- Sub-Status Filter Pills (khusus Capeg / Honor / PKWT / Kontrak) --}}
        @if (! empty($subStatusOptions))
            <div class="no-print filter-pills" role="group" aria-label="Filter status pegawai">
                <a href="{{ route($currentRouteName, ['bulan' => $bulan, 'tahun' => $tahun]) }}"
                   class="pill-btn min-h-[40px] {{ $subStatus === '' ? 'active' : '' }}">
                    Semua
                </a>
                @foreach ($subStatusOptions as $subOpt)
                    @php
                        $isActiveSub = strcasecmp($subStatus, $subOpt) === 0;
                        $dotClass = match ($subOpt) {
                            'Capeg' => 'dot-teknik',
                            'Honor' => 'dot-manajemen',
                            'PKWT' => 'dot-keuangan',
                            default => 'dot-kepegawaian',
                        };
                    @endphp
                    <a href="{{ route($currentRouteName, ['sub_status' => $subOpt, 'bulan' => $bulan, 'tahun' => $tahun]) }}"
                       class="pill-btn min-h-[40px] {{ $isActiveSub ? 'active' : '' }}">
                        @if (! $isActiveSub)
                            <span class="category-dot {{ $dotClass }}" aria-hidden="true"></span>
                        @endif
                        <span>{{ $subOpt }}</span>
                        <span class="font-mono text-xs {{ $isActiveSub ? 'text-[#070c16]' : 'text-[#94a3b8]' }}">
                            {{ $statusBreakdown[$subOpt] ?? 0 }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Filter Bar: Pilih Pegawai, Bulan, Tahun (UX ringkas seperti attendance & attendant) --}}
        <section aria-label="Pilih pegawai dan periode" class="no-print pusimpel-card p-4 sm:p-5">
            <form id="recapFilterForm" action="{{ route($currentRouteName) }}" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                @if ($subStatus !== '')
                    <input type="hidden" name="sub_status" value="{{ $subStatus }}">
                @endif

                <div class="md:col-span-6 space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <label for="nik" class="text-xs font-semibold text-[#cbd5e1]">Pilih Pegawai</label>
                        <input type="search"
                               id="employeeQuickSearch"
                               placeholder="Cari nama atau NIK..."
                               aria-label="Cari nama atau NIK di daftar pegawai"
                               class="w-44 sm:w-52 px-3 py-1 rounded-full text-xs pusimpel-input">
                    </div>
                    <select id="nik" name="nik" required
                            class="w-full min-h-[44px] px-3.5 py-2 rounded-xl text-sm pusimpel-input pusimpel-select">
                        @forelse ($personnel as $pgw)
                            <option value="{{ $pgw['nik'] }}"
                                    data-search="{{ strtolower($pgw['name'].' '.$pgw['nik'].' '.$pgw['status_label']) }}"
                                    {{ ($selectedPerson && strcasecmp($selectedPerson['nik'], $pgw['nik']) === 0) ? 'selected' : '' }}>
                                {{ $pgw['name'] }} ({{ $pgw['nik'] }} · {{ $pgw['status_label'] }})
                            </option>
                        @empty
                            <option value="">Tidak ada data personil</option>
                        @endforelse
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3 md:col-span-4">
                    <div>
                        <label for="bulan" class="block text-xs font-semibold text-[#cbd5e1] mb-1.5">Bulan</label>
                        <select id="bulan" name="bulan" required
                                class="w-full min-h-[44px] px-3.5 py-2 rounded-xl text-sm pusimpel-input pusimpel-select">
                            @for ($i = 1; $i <= 12; $i++)
                                @php $valBulan = sprintf('%02d', $i); @endphp
                                <option value="{{ $valBulan }}" {{ $bulan === $valBulan ? 'selected' : '' }}>
                                    {{ $namaBulanList[$i] ?? $valBulan }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label for="tahun" class="block text-xs font-semibold text-[#cbd5e1] mb-1.5">Tahun</label>
                        <select id="tahun" name="tahun" required
                                class="w-full min-h-[44px] px-3.5 py-2 rounded-xl text-sm pusimpel-input pusimpel-select">
                            @foreach ($tahunList as $th)
                                <option value="{{ $th }}" {{ (string) $tahun === (string) $th ? 'selected' : '' }}>
                                    {{ $th }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <button type="submit" id="filterSubmitBtn"
                            class="w-full min-h-[44px] px-5 py-2 rounded-full text-sm pusimpel-btn-primary">
                        Tampilkan
                    </button>
                </div>
            </form>
        </section>

        @if ($selectedPerson)
            {{-- Header Hasil & Tombol Cetak PDF --}}
            <div class="no-print flex flex-wrap items-center justify-between gap-3 pt-1">
                <h2 class="font-heading text-base sm:text-lg font-bold text-white">
                    Hasil Rekapitulasi
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('rekap.export', ['category' => $category, 'nik' => $selectedPerson['nik'], 'bulan' => $bulan, 'tahun' => $tahun, 'sub_status' => $subStatus]) }}"
                       class="min-h-[42px] px-4 py-2 rounded-full text-xs sm:text-sm font-semibold pusimpel-btn-ghost inline-flex items-center gap-2">
                        <span>Ekspor CSV</span>
                    </a>
                    <a href="{{ route('rekap.cetak', ['category' => $category, 'nik' => $selectedPerson['nik'], 'bulan' => $bulan, 'tahun' => $tahun, 'sub_status' => $subStatus]) }}"
                       target="_blank"
                       rel="noopener"
                       class="min-h-[42px] px-5 py-2 rounded-full text-xs sm:text-sm pusimpel-btn-primary inline-flex items-center gap-2">
                        <span>Cetak PDF</span>
                    </a>
                </div>
            </div>

            {{-- Kartu Identitas Pegawai --}}
            <section aria-label="Informasi pegawai" class="pusimpel-card p-4 sm:p-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <dl class="grid grid-cols-[90px_1fr] gap-y-1.5 gap-x-3 text-sm">
                        <dt class="text-[#94a3b8] font-medium">NIK</dt>
                        <dd class="font-mono font-semibold text-white">{{ $selectedPerson['nik'] }}</dd>

                        <dt class="text-[#94a3b8] font-medium">Nama</dt>
                        <dd class="font-bold text-white">
                            {{ $selectedPerson['name'] }}
                            <span class="font-normal text-xs text-[#94a3b8]">· {{ $selectedPerson['status_label'] }} · {{ $selectedPerson['department'] }}</span>
                        </dd>

                        <dt class="text-[#94a3b8] font-medium">Periode</dt>
                        <dd class="font-semibold text-[#38bdf8]">{{ $recap['period_label'] }}</dd>
                    </dl>

                    <div class="flex flex-wrap gap-2 text-xs font-mono">
                        <span class="px-3 py-1.5 rounded-xl pusimpel-card-alt text-[#e2e8f0]">
                            Hadir <strong class="text-white">{{ $totals['hari_hadir'] ?? 0 }}</strong> hr
                        </span>
                        <span class="px-3 py-1.5 rounded-xl border badge-em">
                            Penuh <strong>{{ $totals['hadir_penuh'] ?? 0 }}</strong>
                        </span>
                        <span class="px-3 py-1.5 rounded-xl border badge-amber">
                            Telat <strong>{{ $totals['terlambat_count'] ?? 0 }}</strong>
                        </span>
                        <span class="px-3 py-1.5 rounded-xl border badge-cyan">
                            Izin/Dinas <strong>{{ $totals['izin_count'] ?? 0 }}</strong>
                        </span>
                    </div>
                </div>
            </section>

            {{-- Tabel Rekap Harian Sebulan Penuh --}}
            <div class="md:hidden text-center text-xs font-semibold text-[#fcd34d] no-print">
                Geser tabel ke kanan/kiri untuk melihat seluruh kolom
            </div>

            <div class="pusimpel-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-center border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="border-b border-white/15 bg-white/5 text-xs font-bold text-[#e2e8f0]">
                                <th class="py-3 px-3.5">Tanggal</th>
                                <th class="py-3 px-3.5">Hari</th>
                                <th class="py-3 px-3.5">Jadwal</th>
                                <th class="py-3 px-3.5">Scan Pagi<br><span class="font-normal text-[#94a3b8]">(Masuk)</span></th>
                                <th class="py-3 px-3.5">Scan Siang<br><span class="font-normal text-[#94a3b8]">(Keluar)</span></th>
                                <th class="py-3 px-3.5">Scan Siang<br><span class="font-normal text-[#94a3b8]">(Masuk)</span></th>
                                <th class="py-3 px-3.5">Scan Sore<br><span class="font-normal text-[#94a3b8]">(Pulang)</span></th>
                                <th class="py-3 px-4 text-left">Status / Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10 text-sm">
                            @forelse ($dailyLogs as $log)
                                @php
                                    $isLibur = (bool) $log['is_libur'];
                                    $s1Class = $log['scan1'] === '-' ? 'text-[#64748b]' : (! empty($log['scan1_late']) ? 'text-[#fda4af]' : 'text-[#6ee7b7]');
                                    $s2Class = $log['scan2'] === '-' ? 'text-[#64748b]' : (! empty($log['scan2_early']) ? 'text-[#fda4af]' : 'text-[#6ee7b7]');
                                    $s3Class = $log['scan3'] === '-' ? 'text-[#64748b]' : (! empty($log['scan3_late']) ? 'text-[#fda4af]' : 'text-[#6ee7b7]');
                                    $s4Class = $log['scan4'] === '-' ? 'text-[#64748b]' : 'text-[#7dd3fc]';

                                    if ($log['status_kehadiran'] === '(-)') {
                                        $statusBadge = 'badge-rose';
                                    } elseif (! $log['has_scan']) {
                                        $statusBadge = $log['has_reason'] ? 'badge-cyan' : ($isLibur ? 'badge-amber' : 'pusimpel-card-alt text-[#94a3b8] border-white/10');
                                    } elseif ($log['is_late']) {
                                        $statusBadge = 'badge-amber';
                                    } elseif (in_array($log['status_kehadiran'], ['Belum Pulang', 'Pulang Saja'], true)) {
                                        $statusBadge = 'badge-blue';
                                    } else {
                                        $statusBadge = 'badge-em';
                                    }

                                    $jadwalText = $log['jadwal_in'] !== '-' ? ($log['jadwal_in'].' - '.$log['jadwal_out']) : '-';
                                @endphp
                                <tr class="pusimpel-table-row transition-colors {{ $isLibur ? 'bg-amber-500/10' : '' }}">
                                    <td class="py-2.5 px-3.5 font-mono text-xs {{ $isLibur ? 'text-[#fcd34d] font-bold' : 'text-[#e2e8f0]' }}">
                                        {{ \Carbon\Carbon::parse($log['tanggal'])->format('d/m/Y') }}
                                    </td>
                                    <td class="py-2.5 px-3.5 text-xs font-bold {{ $isLibur ? 'text-[#fcd34d]' : 'text-white' }}">
                                        {{ $log['hari'] }}
                                    </td>
                                    <td class="py-2.5 px-3.5 font-mono text-xs text-[#94a3b8]">
                                        {{ $jadwalText }}
                                    </td>
                                    <td class="py-2.5 px-3.5 font-mono text-xs font-bold {{ $s1Class }}">{{ $log['scan1'] }}</td>
                                    <td class="py-2.5 px-3.5 font-mono text-xs font-bold {{ $s2Class }}">{{ $log['scan2'] }}</td>
                                    <td class="py-2.5 px-3.5 font-mono text-xs font-bold {{ $s3Class }}">{{ $log['scan3'] }}</td>
                                    <td class="py-2.5 px-3.5 font-mono text-xs font-bold {{ $s4Class }}">{{ $log['scan4'] }}</td>
                                    <td class="py-2.5 px-4 text-left whitespace-normal min-w-[200px]">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusBadge }}">
                                                {{ $log['status_kehadiran'] }}
                                            </span>
                                            @if ($log['catatan'] !== '' && $log['catatan'] !== $log['status_kehadiran'])
                                                <span class="text-xs font-semibold {{ ($log['is_late'] || ! empty($log['scan2_early'])) ? 'text-[#fda4af]' : 'text-[#94a3b8]' }}">
                                                    {{ $log['catatan'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-sm text-[#94a3b8]">Belum ada data pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="no-result-box">
                <p class="text-sm font-semibold text-white">Tidak ada data personil untuk filter ini.</p>
            </div>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const searchInput = document.getElementById('employeeQuickSearch');
        const nikSelect = document.getElementById('nik');
        const form = document.getElementById('recapFilterForm');
        const submitBtn = document.getElementById('filterSubmitBtn');

        if (form && submitBtn) {
            form.addEventListener('submit', function () {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Memuat...';
            });
        }

        if (searchInput && nikSelect) {
            const allOptions = Array.from(nikSelect.options).map(function (opt) {
                return {
                    value: opt.value,
                    text: opt.text,
                    search: opt.getAttribute('data-search') || opt.text.toLowerCase()
                };
            });

            searchInput.addEventListener('input', function () {
                const keyword = searchInput.value.trim().toLowerCase();
                const currentValue = nikSelect.value;
                const filtered = keyword === ''
                    ? allOptions
                    : allOptions.filter(function (item) {
                        return item.search.includes(keyword);
                    });

                nikSelect.innerHTML = '';
                if (filtered.length === 0) {
                    const emptyOpt = document.createElement('option');
                    emptyOpt.value = '';
                    emptyOpt.text = 'Tidak ditemukan';
                    nikSelect.appendChild(emptyOpt);
                    return;
                }

                let matchedCurrent = false;
                filtered.forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = item.value;
                    opt.text = item.text;
                    opt.setAttribute('data-search', item.search);
                    if (item.value === currentValue) {
                        opt.selected = true;
                        matchedCurrent = true;
                    }
                    nikSelect.appendChild(opt);
                });

                if (!matchedCurrent && nikSelect.options.length > 0) {
                    nikSelect.options[0].selected = true;
                }
            });
        }
    })();
</script>
@endpush
