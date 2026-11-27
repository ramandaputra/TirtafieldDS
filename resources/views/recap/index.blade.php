@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
@php
    $isPegawai = $category === \App\Services\AttendanceRecapService::CATEGORY_PEGAWAI;
    $currentRouteName = $isPegawai ? 'rekap.pegawai' : 'rekap.non-tetap';
    $bulan = $recap['bulan'] ?? now('Asia/Jakarta')->format('m');
    $tahun = $recap['tahun'] ?? now('Asia/Jakarta')->format('Y');
    $personnel = $recap['personnel'] ?? collect();
    $selectedPerson = $recap['selected_person'] ?? null;
    $dailyLogs = $recap['daily_logs'] ?? collect();
    $totals = $recap['totals'] ?? [];
    $namaBulanList = $recap['nama_bulan_list'] ?? [];
    $tahunList = $recap['tahun_list'] ?? [$tahun];
    $selectedLabel = $selectedPerson
        ? $selectedPerson['name'].' ('.$selectedPerson['nik'].')'
        : '';
@endphp

<div class="max-w-[1140px] mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-4">
    {{-- Judul Halaman --}}
    <div class="no-print flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl sm:text-2xl font-extrabold user-name-gradient">
            {{ $pageTitle }}
        </h1>
        <a href="{{ route('dashboard') }}"
           class="min-h-[38px] px-3.5 py-1.5 rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 pusimpel-btn-ghost shrink-0">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12" />
                <polyline points="12 19 5 12 12 5" />
            </svg>
            <span>Kembali</span>
        </a>
    </div>

    @if ($dbError)
        <div role="alert" class="rounded-xl border p-4 text-sm badge-rose">
            {{ $dbError }}
        </div>
    @else
        {{-- Filter Menu Absensi --}}
        <section aria-label="Pilih pegawai dan periode" class="no-print pusimpel-card p-4 relative z-20">
            <form id="recapFilterForm" action="{{ route($currentRouteName) }}" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <input type="hidden" id="nik" name="nik" value="{{ $selectedPerson['nik'] ?? '' }}">

                <div class="md:col-span-6 relative">
                    <label for="employeeComboInput" class="block text-xs font-medium text-[#94a3b8] mb-1">
                        Pilih Pegawai
                    </label>
                    <div class="relative">
                        <input type="text"
                               id="employeeComboInput"
                               value="{{ $selectedLabel }}"
                               placeholder="Ketik nama atau NIK..."
                               role="combobox"
                               aria-expanded="false"
                               aria-controls="employeeComboList"
                               aria-autocomplete="list"
                               autocomplete="off"
                               class="w-full min-h-[42px] pl-3.5 pr-9 py-2 rounded-xl text-sm pusimpel-input">
                        <button type="button"
                                id="employeeComboToggle"
                                aria-label="Buka daftar pegawai"
                                class="absolute inset-y-0 right-0 px-3 flex items-center text-[#94a3b8] hover:text-white">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                    </div>

                    <ul id="employeeComboList"
                        role="listbox"
                        aria-label="Daftar pegawai"
                        class="hidden pusimpel-dropdown absolute left-0 right-0 mt-1.5 max-h-72 overflow-y-auto py-1 z-30">
                        @forelse ($personnel as $pgw)
                            @php
                                $isCurrent = $selectedPerson && strcasecmp($selectedPerson['nik'], $pgw['nik']) === 0;
                            @endphp
                            <li role="option"
                                tabindex="-1"
                                aria-selected="{{ $isCurrent ? 'true' : 'false' }}"
                                data-nik="{{ $pgw['nik'] }}"
                                data-label="{{ $pgw['name'].' ('.$pgw['nik'].')' }}"
                                data-search="{{ strtolower($pgw['name'].' '.$pgw['nik'].' '.$pgw['status_label']) }}"
                                class="combo-option px-3.5 py-2 text-sm cursor-pointer flex items-center justify-between gap-2 transition-colors {{ $isCurrent ? 'bg-[#38bdf8]/20 text-white font-semibold' : 'text-[#cbd5e1] hover:bg-white/10 hover:text-white' }}">
                                <span class="truncate">{{ $pgw['name'] }}</span>
                                <span class="text-xs font-mono text-[#94a3b8] shrink-0">{{ $pgw['nik'] }}</span>
                            </li>
                        @empty
                            <li class="px-3.5 py-2 text-sm text-[#94a3b8]">Tidak ada data</li>
                        @endforelse
                    </ul>
                </div>

                <div class="grid grid-cols-2 gap-3 md:col-span-4">
                    <div>
                        <label for="bulan" class="block text-xs font-medium text-[#94a3b8] mb-1">Bulan</label>
                        <select id="bulan" name="bulan" required
                                class="w-full min-h-[42px] px-3 py-2 rounded-xl text-sm pusimpel-input pusimpel-select">
                            @for ($i = 1; $i <= 12; $i++)
                                @php $valBulan = sprintf('%02d', $i); @endphp
                                <option value="{{ $valBulan }}" {{ $bulan === $valBulan ? 'selected' : '' }}>
                                    {{ $namaBulanList[$i] ?? $valBulan }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label for="tahun" class="block text-xs font-medium text-[#94a3b8] mb-1">Tahun</label>
                        <select id="tahun" name="tahun" required
                                class="w-full min-h-[42px] px-3 py-2 rounded-xl text-sm pusimpel-input pusimpel-select">
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
                            class="w-full min-h-[42px] px-4 py-2 rounded-xl text-sm pusimpel-btn-primary">
                        Tampilkan
                    </button>
                </div>
            </form>
        </section>

        @if ($selectedPerson)
            {{-- Info Pegawai + Cetak PDF --}}
            <section aria-label="Informasi pegawai dan cetak PDF" class="pusimpel-card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="space-y-1 min-w-0">
                    <h2 class="font-heading text-base sm:text-lg font-bold text-white truncate">
                        {{ $selectedPerson['name'] }}
                    </h2>
                    <p class="text-xs text-[#94a3b8] font-mono tabular-nums">
                        {{ $selectedPerson['nik'] }} · {{ $recap['period_label'] }} · Hadir {{ $totals['hari_hadir'] ?? 0 }} hr · Telat {{ $totals['terlambat_count'] ?? 0 }}
                    </p>
                </div>

                <a href="{{ route('rekap.cetak', ['category' => $category, 'nik' => $selectedPerson['nik'], 'bulan' => $bulan, 'tahun' => $tahun]) }}"
                   target="_blank"
                   rel="noopener"
                   class="no-print w-full sm:w-auto min-h-[42px] px-5 py-2 rounded-xl text-sm pusimpel-btn-primary inline-flex items-center justify-center gap-2 shrink-0">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 6 2 18 2 18 9" />
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                        <rect x="6" y="14" width="12" height="8" />
                    </svg>
                    <span>Cetak PDF</span>
                </a>
            </section>

            {{-- Tabel Rekap Harian --}}
            <div class="pusimpel-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-center border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="border-b border-white/15 bg-white/5 text-xs font-semibold text-[#cbd5e1]">
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-3">Hari</th>
                                <th class="py-3 px-3">Jadwal</th>
                                <th class="py-3 px-3">Masuk</th>
                                <th class="py-3 px-3">Keluar Siang</th>
                                <th class="py-3 px-3">Masuk Siang</th>
                                <th class="py-3 px-3">Pulang</th>
                                <th class="py-3 px-4 text-left">Status / Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10 text-xs sm:text-sm">
                            @forelse ($dailyLogs as $log)
                                @php
                                    $isLibur = (bool) $log['is_libur'];
                                    $s1Class = $log['scan1'] === '-' ? 'text-[#64748b]' : (! empty($log['scan1_late']) ? 'text-[#fda4af]' : 'text-[#6ee7b7]');
                                    $s2Class = $log['scan2'] === '-' ? 'text-[#64748b]' : (! empty($log['scan2_early']) ? 'text-[#fda4af]' : 'text-[#6ee7b7]');
                                    $s3Class = $log['scan3'] === '-' ? 'text-[#64748b]' : (! empty($log['scan3_late']) ? 'text-[#fda4af]' : 'text-[#6ee7b7]');
                                    $s4Class = $log['scan4'] === '-' ? 'text-[#64748b]' : 'text-[#7dd3fc]';

                                    $jadwalText = $log['jadwal_in'] !== '-' ? ($log['jadwal_in'].' - '.$log['jadwal_out']) : '-';
                                    $fpNote = trim((string) ($log['fp_notes'] ?? ''));
                                    $statusText = $fpNote !== '' ? $fpNote : $log['status_kehadiran'];

                                    if ($fpNote !== '' || $log['status_kehadiran'] === '(-)') {
                                        $ketClass = 'text-[#fda4af] font-semibold';
                                    } elseif ($isLibur) {
                                        $ketClass = 'text-[#fcd34d]';
                                    } elseif (! $log['has_scan'] && $log['has_reason']) {
                                        $ketClass = 'text-[#7dd3fc] font-semibold';
                                    } elseif ($log['has_scan']) {
                                        $ketClass = 'text-[#6ee7b7]';
                                    } else {
                                        $ketClass = 'text-[#94a3b8]';
                                    }
                                @endphp
                                <tr class="pusimpel-table-row transition-colors {{ $isLibur ? 'bg-amber-500/5' : '' }}">
                                    <td class="py-2.5 px-4 font-mono text-xs tabular-nums {{ $isLibur ? 'text-[#fcd34d]' : 'text-[#e2e8f0]' }}">
                                        {{ \Carbon\Carbon::parse($log['tanggal'])->format('d/m/Y') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-xs font-semibold {{ $isLibur ? 'text-[#fcd34d]' : 'text-white' }}">
                                        {{ $log['hari'] }}
                                    </td>
                                    <td class="py-2.5 px-3 font-mono text-xs tabular-nums text-[#94a3b8]">
                                        {{ $jadwalText }}
                                    </td>
                                    <td class="py-2.5 px-3 font-mono text-xs font-semibold tabular-nums {{ $s1Class }}">{{ $log['scan1'] }}</td>
                                    <td class="py-2.5 px-3 font-mono text-xs font-semibold tabular-nums {{ $s2Class }}">{{ $log['scan2'] }}</td>
                                    <td class="py-2.5 px-3 font-mono text-xs font-semibold tabular-nums {{ $s3Class }}">{{ $log['scan3'] }}</td>
                                    <td class="py-2.5 px-3 font-mono text-xs font-semibold tabular-nums {{ $s4Class }}">{{ $log['scan4'] }}</td>
                                    <td class="py-2.5 px-4 text-left text-xs {{ $ketClass }}">
                                        {{ $statusText }}
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
                <p class="text-sm font-semibold text-white">Tidak ada data personil.</p>
            </div>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const nikInput = document.getElementById('nik');
        const comboInput = document.getElementById('employeeComboInput');
        const comboToggle = document.getElementById('employeeComboToggle');
        const comboList = document.getElementById('employeeComboList');
        const form = document.getElementById('recapFilterForm');
        const submitBtn = document.getElementById('filterSubmitBtn');
        let committedLabel = comboInput ? comboInput.value : '';

        if (form && submitBtn) {
            form.addEventListener('submit', function () {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Memuat...';
            });
        }

        if (!nikInput || !comboInput || !comboList) {
            return;
        }

        const options = Array.from(comboList.querySelectorAll('.combo-option'));

        function openList() {
            comboList.classList.remove('hidden');
            comboInput.setAttribute('aria-expanded', 'true');
        }

        function closeList(restoreLabel) {
            comboList.classList.add('hidden');
            comboInput.setAttribute('aria-expanded', 'false');
            if (restoreLabel) {
                comboInput.value = committedLabel;
            }
        }

        function filterOptions(keyword) {
            const q = keyword.trim().toLowerCase();
            options.forEach(function (opt) {
                const hay = opt.getAttribute('data-search') || '';
                opt.classList.toggle('hidden', q !== '' && !hay.includes(q));
            });
        }

        function selectOption(opt) {
            const nik = opt.getAttribute('data-nik');
            const label = opt.getAttribute('data-label');
            if (!nik) {
                return;
            }
            nikInput.value = nik;
            committedLabel = label || nik;
            comboInput.value = committedLabel;
            closeList(false);
            if (form) {
                form.submit();
            }
        }

        comboInput.addEventListener('focus', function () {
            comboInput.select();
            filterOptions('');
            openList();
        });

        comboInput.addEventListener('input', function () {
            filterOptions(comboInput.value);
            openList();
        });

        comboInput.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeList(true);
            } else if (event.key === 'Enter') {
                const firstVisible = options.find(function (opt) {
                    return !opt.classList.contains('hidden');
                });
                if (firstVisible && !comboList.classList.contains('hidden')) {
                    event.preventDefault();
                    selectOption(firstVisible);
                }
            }
        });

        if (comboToggle) {
            comboToggle.addEventListener('click', function () {
                if (comboList.classList.contains('hidden')) {
                    filterOptions('');
                    openList();
                    comboInput.focus();
                } else {
                    closeList(true);
                }
            });
        }

        options.forEach(function (opt) {
            opt.addEventListener('click', function () {
                selectOption(opt);
            });
        });

        document.addEventListener('click', function (event) {
            if (!comboInput.contains(event.target) && !comboList.contains(event.target) && (!comboToggle || !comboToggle.contains(event.target))) {
                closeList(true);
            }
        });
    })();
</script>
@endpush
