@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
@php
    $isPegawai = $category === \App\Services\AttendanceRecapService::CATEGORY_PEGAWAI;
    $filters = $recap['filters'] ?? [
        'start_date' => now('Asia/Jakarta')->startOfMonth()->toDateString(),
        'end_date' => now('Asia/Jakarta')->endOfMonth()->toDateString(),
        'q' => '',
        'department' => '',
        'office_id' => '',
        'sub_status' => '',
        'attendance_filter' => 'all',
    ];
    $summary = $recap['summary'] ?? [];
    $statusBreakdown = $recap['status_breakdown'] ?? [];
    $rows = $recap['rows'] ?? collect();
    $dailyLogs = $recap['daily_logs'] ?? collect();
    $departments = $recap['departments'] ?? collect();
    $offices = $recap['offices'] ?? collect();

    $hasActiveFilter = $filters['q'] !== ''
        || $filters['department'] !== ''
        || $filters['office_id'] !== ''
        || $filters['sub_status'] !== ''
        || $filters['attendance_filter'] !== 'all';

    $currentRouteName = $isPegawai ? 'rekap.pegawai' : 'rekap.non-tetap';
@endphp

<div class="max-w-[1260px] mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-6">
    {{-- Breadcrumb & Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="space-y-2">
            <a href="{{ route('dashboard') }}"
               class="no-print inline-flex items-center gap-2 min-h-[44px] px-3 -ml-3 rounded-full text-sm font-semibold text-[#38bdf8] hover:bg-white/10 transition-colors">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 19 5 12 12 5" />
                </svg>
                <span>Kembali ke Beranda</span>
            </a>
            <h1 class="text-2xl sm:text-3xl font-extrabold user-name-gradient">
                {{ $pageTitle }}
            </h1>
            <p class="text-sm text-[#cbd5e1]">
                {{ $pageSubtitle }} Periode:
                <span class="font-mono font-semibold text-white">{{ $filters['start_date'] }}</span>
                s/d
                <span class="font-mono font-semibold text-white">{{ $filters['end_date'] }}</span>.
            </p>
        </div>

        <div class="no-print flex flex-wrap items-center gap-3 shrink-0">
            <a href="{{ route('rekap.export', array_merge(['category' => $category], $filters)) }}"
               class="min-h-[44px] px-5 py-2.5 rounded-full text-sm pusimpel-btn-primary inline-flex items-center gap-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                <span>Ekspor CSV</span>
            </a>

            <button type="button"
                    onclick="window.print()"
                    class="min-h-[44px] px-5 py-2.5 rounded-full text-sm font-semibold pusimpel-btn-ghost inline-flex items-center gap-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 6 2 18 2 18 9" />
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                    <rect x="6" y="14" width="12" height="8" />
                </svg>
                <span>Cetak</span>
            </button>
        </div>
    </div>

    @if ($dbError)
        <div role="alert" class="rounded-2xl border p-4 text-sm flex items-start gap-3 badge-rose">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <div>
                <p class="font-bold">Gagal memuat data rekap</p>
                <p class="mt-0.5">{{ $dbError }}</p>
            </div>
        </div>
    @else
        {{-- Sub-Status Filter Pills (Pusimpel Filter Pills Style) --}}
        @if (! empty($subStatusOptions))
            <div class="no-print filter-pills" role="group" aria-label="Filter sub-status kepegawaian">
                <a href="{{ route($currentRouteName, array_merge($filters, ['sub_status' => ''])) }}"
                   class="pill-btn min-h-[44px] {{ $filters['sub_status'] === '' ? 'active' : '' }}">
                    Semua Status
                </a>
                @foreach ($subStatusOptions as $subOpt)
                    @php
                        $isActiveSub = strcasecmp($filters['sub_status'], $subOpt) === 0;
                        $dotClass = match ($subOpt) {
                            'Capeg' => 'dot-teknik',
                            'Honor' => 'dot-manajemen',
                            'PKWT' => 'dot-keuangan',
                            default => 'dot-kepegawaian',
                        };
                    @endphp
                    <a href="{{ route($currentRouteName, array_merge($filters, ['sub_status' => $subOpt])) }}"
                       class="pill-btn min-h-[44px] {{ $isActiveSub ? 'active' : '' }}">
                        @if (! $isActiveSub)
                            <span class="category-dot {{ $dotClass }}" aria-hidden="true"></span>
                        @endif
                        <span>{{ $subOpt }}</span>
                        <span class="font-mono text-xs px-1.5 py-0.5 rounded-full {{ $isActiveSub ? 'bg-[#070c16]/15 text-[#070c16]' : 'bg-white/10 text-[#f1f5f9]' }}">
                            {{ $statusBreakdown[$subOpt] ?? 0 }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Filter Form --}}
        <section aria-label="Filter rekap absensi" class="no-print pusimpel-card p-4 sm:p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-white/10">
                <span class="font-heading text-sm font-bold text-white">Filter Periode &amp; Personil</span>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" data-preset="today" class="preset-btn min-h-[38px] px-3.5 py-1.5 rounded-full text-xs font-semibold pusimpel-btn-ghost">
                        Hari Ini
                    </button>
                    <button type="button" data-preset="7days" class="preset-btn min-h-[38px] px-3.5 py-1.5 rounded-full text-xs font-semibold pusimpel-btn-ghost">
                        7 Hari Terakhir
                    </button>
                    <button type="button" data-preset="month" class="preset-btn min-h-[38px] px-3.5 py-1.5 rounded-full text-xs font-semibold pusimpel-btn-ghost">
                        Bulan Ini
                    </button>
                </div>
            </div>

            <form id="recapFilterForm" action="{{ route($currentRouteName) }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                @if (! empty($subStatusOptions))
                    <input type="hidden" name="sub_status" value="{{ $filters['sub_status'] }}">
                @endif

                <div>
                    <label for="start_date" class="block text-xs font-semibold text-[#e2e8f0] mb-1.5">Tanggal Mulai</label>
                    <input type="date" id="start_date" name="start_date" value="{{ $filters['start_date'] }}"
                           class="w-full min-h-[44px] px-3.5 py-2 rounded-xl text-sm pusimpel-input">
                </div>

                <div>
                    <label for="end_date" class="block text-xs font-semibold text-[#e2e8f0] mb-1.5">Tanggal Akhir</label>
                    <input type="date" id="end_date" name="end_date" value="{{ $filters['end_date'] }}"
                           class="w-full min-h-[44px] px-3.5 py-2 rounded-xl text-sm pusimpel-input">
                </div>

                <div>
                    <label for="q" class="block text-xs font-semibold text-[#e2e8f0] mb-1.5">Cari Nama / NIK</label>
                    <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Nama atau NIK..."
                           class="w-full min-h-[44px] px-4 py-2 rounded-full text-sm pusimpel-input">
                </div>

                <div>
                    <label for="department" class="block text-xs font-semibold text-[#e2e8f0] mb-1.5">Bidang / Unit</label>
                    <select id="department" name="department"
                            class="w-full min-h-[44px] px-3.5 py-2 rounded-xl text-sm pusimpel-input pusimpel-select">
                        <option value="">Semua Bidang</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept }}" {{ $filters['department'] === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="attendance_filter" class="block text-xs font-semibold text-[#e2e8f0] mb-1.5">Status Kehadiran</label>
                    <select id="attendance_filter" name="attendance_filter"
                            class="w-full min-h-[44px] px-3.5 py-2 rounded-xl text-sm pusimpel-input pusimpel-select">
                        <option value="all" {{ $filters['attendance_filter'] === 'all' ? 'selected' : '' }}>Semua Personil</option>
                        <option value="present" {{ $filters['attendance_filter'] === 'present' ? 'selected' : '' }}>Sudah Presensi</option>
                        <option value="absent" {{ $filters['attendance_filter'] === 'absent' ? 'selected' : '' }}>Belum Presensi</option>
                        <option value="late" {{ $filters['attendance_filter'] === 'late' ? 'selected' : '' }}>Pernah Terlambat</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" id="filterSubmitBtn"
                            class="flex-1 min-h-[44px] px-4 py-2 rounded-full text-sm pusimpel-btn-primary">
                        Terapkan
                    </button>
                    @if ($hasActiveFilter)
                        <a href="{{ route($currentRouteName, ['start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]) }}"
                           class="min-h-[44px] px-3.5 py-2 rounded-full text-sm font-semibold inline-flex items-center pusimpel-btn-ghost">
                            Reset
                        </a>
                    @endif
                </div>

                @if ($offices->isNotEmpty())
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label for="office_id" class="block text-xs font-semibold text-[#e2e8f0] mb-1.5">Kantor Penempatan</label>
                        <select id="office_id" name="office_id"
                                class="w-full min-h-[44px] px-3.5 py-2 rounded-xl text-sm pusimpel-input pusimpel-select">
                            <option value="">Semua Kantor Penempatan</option>
                            @foreach ($offices as $office)
                                <option value="{{ $office->id }}" {{ $filters['office_id'] === $office->id ? 'selected' : '' }}>{{ $office->nama_kantor }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </form>
        </section>

        {{-- Summary Metrics Row (100% Real DB Data) --}}
        <section aria-label="Ringkasan statistik kehadiran" class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
            <div class="pusimpel-card p-4">
                <div class="text-xs font-medium text-[#94a3b8]">Total Personil</div>
                <div class="text-2xl font-extrabold font-mono tabular-nums text-white mt-1">{{ $summary['total_personil'] ?? 0 }}</div>
            </div>
            <div class="pusimpel-card p-4">
                <div class="text-xs font-medium text-[#94a3b8]">Personil Tercatat Hadir</div>
                <div class="text-2xl font-extrabold font-mono tabular-nums text-[#38bdf8] mt-1">{{ $summary['personil_hadir'] ?? 0 }}</div>
            </div>
            <div class="pusimpel-card p-4">
                <div class="text-xs font-medium text-[#94a3b8]">Masuk Tepat Waktu</div>
                <div class="text-2xl font-extrabold font-mono tabular-nums text-[#6ee7b7] mt-1">{{ $summary['total_tepat_waktu'] ?? 0 }}</div>
            </div>
            <div class="pusimpel-card p-4">
                <div class="text-xs font-medium text-[#94a3b8]">Masuk Terlambat</div>
                <div class="text-2xl font-extrabold font-mono tabular-nums text-[#fcd34d] mt-1">{{ $summary['total_terlambat'] ?? 0 }}</div>
            </div>
            <div class="col-span-2 lg:col-span-1 pusimpel-card p-4">
                <div class="text-xs font-medium text-[#94a3b8]">Total Log Presensi</div>
                <div class="text-2xl font-extrabold font-mono tabular-nums text-white mt-1">{{ $summary['total_log'] ?? 0 }}</div>
                <div class="text-xs text-[#cbd5e1] mt-0.5 font-mono">
                    Disetujui: {{ $summary['total_approved'] ?? 0 }} · Ditolak: {{ $summary['total_rejected'] ?? 0 }}
                </div>
            </div>
        </section>

        {{-- View Mode Tabs --}}
        <div class="no-print flex flex-wrap items-center gap-2 pb-1" role="tablist" aria-label="Tampilan data rekap">
            <button type="button"
                    id="tabSummaryBtn"
                    role="tab"
                    aria-selected="true"
                    aria-controls="panelSummary"
                    class="pill-btn min-h-[44px] active">
                Ringkasan Per Personil ({{ $rows->count() }})
            </button>
            <button type="button"
                    id="tabLogsBtn"
                    role="tab"
                    aria-selected="false"
                    aria-controls="panelLogs"
                    class="pill-btn min-h-[44px]">
                Rincian Log Harian ({{ $dailyLogs->count() }})
            </button>
        </div>

        {{-- PANEL 1: Ringkasan Per Personil --}}
        <section id="panelSummary" role="tabpanel" aria-labelledby="tabSummaryBtn">
            @if ($rows->isEmpty())
                <div class="no-result-box space-y-3">
                    <p class="text-base font-bold text-white">Tidak ada data personil yang sesuai dengan filter.</p>
                    <p class="text-sm text-[#94a3b8]">Coba ubah kata kunci pencarian, pilihan bidang, atau rentang tanggal.</p>
                    @if ($hasActiveFilter)
                        <div>
                            <a href="{{ route($currentRouteName) }}"
                               class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-full text-sm pusimpel-btn-primary">
                                Reset Semua Filter
                            </a>
                        </div>
                    @endif
                </div>
            @else
                {{-- Desktop Table --}}
                <div class="hidden lg:block pusimpel-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/15 bg-white/5 text-xs font-bold text-[#e2e8f0]">
                                    <th class="py-3.5 px-4">No</th>
                                    <th class="py-3.5 px-4">NIK / NUP</th>
                                    <th class="py-3.5 px-4">Nama &amp; Unit Kerja</th>
                                    <th class="py-3.5 px-4">Status</th>
                                    <th class="py-3.5 px-4 text-center">Hari Hadir</th>
                                    <th class="py-3.5 px-4 text-center">Masuk</th>
                                    <th class="py-3.5 px-4 text-center">Pulang</th>
                                    <th class="py-3.5 px-4 text-center">Tepat Waktu</th>
                                    <th class="py-3.5 px-4 text-center">Terlambat</th>
                                    <th class="py-3.5 px-4 text-right no-print">Rincian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10 text-sm">
                                @foreach ($rows as $idx => $row)
                                    @php
                                        $badgeClass = match ($row['status_label']) {
                                            'Pegawai Tetap' => 'badge-blue',
                                            'Direksi' => 'badge-purple',
                                            'Capeg' => 'badge-cyan',
                                            'Honor' => 'badge-amber',
                                            'PKWT' => 'badge-em',
                                            default => 'badge-purple',
                                        };
                                    @endphp
                                    <tr class="pusimpel-table-row transition-colors">
                                        <td class="py-3.5 px-4 font-mono text-xs text-[#94a3b8] tabular-nums">{{ $idx + 1 }}</td>
                                        <td class="py-3.5 px-4 font-mono text-xs font-semibold text-[#e2e8f0] whitespace-nowrap">{{ $row['nik'] }}</td>
                                        <td class="py-3.5 px-4">
                                            <div class="font-semibold text-white">{{ $row['name'] }}</div>
                                            <div class="text-xs text-[#94a3b8]">{{ $row['department'] }} · {{ $row['position'] }}</div>
                                        </td>
                                        <td class="py-3.5 px-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border {{ $badgeClass }}">
                                                {{ $row['status_label'] }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-bold tabular-nums text-white">{{ $row['hari_hadir'] }}</td>
                                        <td class="py-3.5 px-4 text-center font-mono tabular-nums text-[#cbd5e1]">{{ $row['masuk_count'] }}</td>
                                        <td class="py-3.5 px-4 text-center font-mono tabular-nums text-[#cbd5e1]">{{ $row['pulang_count'] }}</td>
                                        <td class="py-3.5 px-4 text-center font-mono font-semibold tabular-nums text-[#6ee7b7]">{{ $row['tepat_waktu_count'] }}</td>
                                        <td class="py-3.5 px-4 text-center font-mono font-semibold tabular-nums {{ $row['terlambat_count'] > 0 ? 'text-[#fcd34d]' : 'text-[#94a3b8]' }}">
                                            {{ $row['terlambat_count'] }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right no-print">
                                            <button type="button"
                                                    data-detail-nik="{{ $row['nik'] }}"
                                                    data-detail-name="{{ $row['name'] }}"
                                                    class="detail-trigger min-h-[38px] px-3.5 py-1.5 rounded-full text-xs font-semibold pusimpel-btn-ghost">
                                                Lihat Log ({{ $row['total_log'] }})
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Tablet & Mobile Cards --}}
                <div class="lg:hidden grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($rows as $row)
                        <article class="pusimpel-card p-4 space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-white truncate">{{ $row['name'] }}</h3>
                                    <p class="text-xs font-mono text-[#94a3b8]">{{ $row['nik'] }}</p>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border shrink-0 badge-cyan">
                                    {{ $row['status_label'] }}
                                </span>
                            </div>

                            <div class="text-xs text-[#cbd5e1] space-y-0.5">
                                <div class="truncate"><strong class="text-white">Bidang:</strong> {{ $row['department'] }}</div>
                                <div class="truncate"><strong class="text-white">Jabatan:</strong> {{ $row['position'] }}</div>
                            </div>

                            <div class="grid grid-cols-4 gap-2 pt-2 border-t border-white/10 text-center">
                                <div class="rounded-xl p-2 pusimpel-card-alt">
                                    <div class="text-[11px] text-[#94a3b8]">Hadir</div>
                                    <div class="text-sm font-bold font-mono text-white">{{ $row['hari_hadir'] }}</div>
                                </div>
                                <div class="rounded-xl p-2 pusimpel-card-alt">
                                    <div class="text-[11px] text-[#94a3b8]">Masuk</div>
                                    <div class="text-sm font-bold font-mono text-white">{{ $row['masuk_count'] }}</div>
                                </div>
                                <div class="rounded-xl p-2 border badge-em">
                                    <div class="text-[11px]">Tepat</div>
                                    <div class="text-sm font-bold font-mono">{{ $row['tepat_waktu_count'] }}</div>
                                </div>
                                <div class="rounded-xl p-2 border badge-amber">
                                    <div class="text-[11px]">Telat</div>
                                    <div class="text-sm font-bold font-mono">{{ $row['terlambat_count'] }}</div>
                                </div>
                            </div>

                            <button type="button"
                                    data-detail-nik="{{ $row['nik'] }}"
                                    data-detail-name="{{ $row['name'] }}"
                                    class="no-print detail-trigger w-full min-h-[44px] px-4 py-2 rounded-full text-xs font-semibold pusimpel-btn-ghost">
                                Lihat Rincian Log ({{ $row['total_log'] }})
                            </button>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- PANEL 2: Rincian Log Harian --}}
        <section id="panelLogs" role="tabpanel" aria-labelledby="tabLogsBtn" class="hidden">
            @if ($dailyLogs->isEmpty())
                <div class="no-result-box space-y-2">
                    <p class="text-base font-bold text-white">Belum ada log presensi pada periode ini.</p>
                    <p class="text-sm text-[#94a3b8]">Rentang tanggal {{ $filters['start_date'] }} s/d {{ $filters['end_date'] }} belum memiliki catatan presensi untuk kategori ini.</p>
                </div>
            @else
                <div class="hidden lg:block pusimpel-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/15 bg-white/5 text-xs font-bold text-[#e2e8f0]">
                                    <th class="py-3.5 px-4">Tanggal &amp; Jam</th>
                                    <th class="py-3.5 px-4">NIK &amp; Nama</th>
                                    <th class="py-3.5 px-4">Status</th>
                                    <th class="py-3.5 px-4">Sesi</th>
                                    <th class="py-3.5 px-4">Ketepatan</th>
                                    <th class="py-3.5 px-4">Persetujuan</th>
                                    <th class="py-3.5 px-4">Lokasi &amp; Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10 text-sm">
                                @foreach ($dailyLogs as $log)
                                    <tr class="pusimpel-table-row transition-colors">
                                        <td class="py-3.5 px-4 whitespace-nowrap font-mono text-xs">
                                            <div class="font-bold text-white">{{ $log['tanggal'] }}</div>
                                            <div class="text-[#94a3b8]">{{ $log['waktu'] }} WIB</div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <div class="font-semibold text-white">{{ $log['name'] }}</div>
                                            <div class="text-xs font-mono text-[#94a3b8]">{{ $log['nik'] }} · {{ $log['department'] }}</div>
                                        </td>
                                        <td class="py-3.5 px-4 whitespace-nowrap text-xs font-bold text-[#e2e8f0]">
                                            {{ $log['status_label'] }}
                                        </td>
                                        <td class="py-3.5 px-4 whitespace-nowrap text-xs font-semibold text-white">
                                            {{ $log['slot'] }}
                                        </td>
                                        <td class="py-3.5 px-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border {{ $log['is_late'] ? 'badge-amber' : 'badge-em' }}">
                                                {{ $log['status'] }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border
                                                {{ $log['approval_status'] === 'approved' ? 'badge-em' : ($log['approval_status'] === 'rejected' ? 'badge-rose' : 'badge-amber') }}">
                                                {{ $log['approval_label'] }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-xs text-[#cbd5e1] max-w-xs">
                                            <div class="font-semibold text-white">{{ $log['lokasi'] }} ({{ $log['radius_label'] }})</div>
                                            @if ($log['catatan'] !== '')
                                                <div class="mt-0.5 break-words text-[#94a3b8]">{{ $log['catatan'] }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lg:hidden grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($dailyLogs as $log)
                        <article class="pusimpel-card p-4 space-y-2.5">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-bold text-white">{{ $log['name'] }}</h3>
                                    <p class="text-xs font-mono text-[#94a3b8]">{{ $log['nik'] }} · {{ $log['status_label'] }}</p>
                                </div>
                                <span class="text-xs font-mono font-bold text-[#38bdf8]">{{ $log['tanggal'] }} {{ $log['waktu'] }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="px-2.5 py-0.5 rounded-full border border-white/15 bg-white/10 text-xs font-semibold text-white">{{ $log['slot'] }}</span>
                                <span class="px-2.5 py-0.5 rounded-full border text-xs font-bold {{ $log['is_late'] ? 'badge-amber' : 'badge-em' }}">
                                    {{ $log['status'] }}
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full border text-xs font-bold {{ $log['approval_status'] === 'approved' ? 'badge-em' : ($log['approval_status'] === 'rejected' ? 'badge-rose' : 'badge-amber') }}">
                                    {{ $log['approval_label'] }}
                                </span>
                            </div>
                            <div class="text-xs text-[#cbd5e1]">
                                <div><strong class="text-white">Lokasi:</strong> {{ $log['lokasi'] }}</div>
                                @if ($log['catatan'] !== '')
                                    <div class="mt-0.5 break-words text-[#94a3b8]">{{ $log['catatan'] }}</div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>

{{-- Detail Modal --}}
<div id="detailModal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="detailModalTitle"
     class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/75 p-4 sm:p-6">
    <div class="min-h-full flex items-center justify-center">
        <div class="pusimpel-card w-full max-w-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-white/15 flex items-center justify-between gap-4">
                <div>
                    <h2 id="detailModalTitle" class="font-heading text-lg font-bold text-white">Rincian Log Presensi</h2>
                    <p id="detailModalSubtitle" class="text-xs font-mono text-[#94a3b8] mt-0.5"></p>
                </div>
                <button type="button"
                        id="closeDetailModalBtn"
                        aria-label="Tutup rincian log presensi"
                        class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center pusimpel-btn-ghost">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>

            <div id="detailModalBody" class="p-5 max-h-[70vh] overflow-y-auto space-y-3 text-sm"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const filterForm = document.getElementById('recapFilterForm');
        const filterBtn = document.getElementById('filterSubmitBtn');
        const startInput = document.getElementById('start_date');
        const endInput = document.getElementById('end_date');

        if (filterForm && filterBtn) {
            filterForm.addEventListener('submit', function () {
                filterBtn.disabled = true;
                filterBtn.textContent = 'Memuat...';
            });
        }

        function formatLocalDate(dateObj) {
            const y = dateObj.getFullYear();
            const m = String(dateObj.getMonth() + 1).padStart(2, '0');
            const d = String(dateObj.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        document.querySelectorAll('.preset-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!startInput || !endInput || !filterForm) {
                    return;
                }
                const now = new Date();
                const preset = btn.getAttribute('data-preset');
                if (preset === 'today') {
                    const today = formatLocalDate(now);
                    startInput.value = today;
                    endInput.value = today;
                } else if (preset === '7days') {
                    const start = new Date(now);
                    start.setDate(now.getDate() - 6);
                    startInput.value = formatLocalDate(start);
                    endInput.value = formatLocalDate(now);
                } else if (preset === 'month') {
                    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    startInput.value = formatLocalDate(firstDay);
                    endInput.value = formatLocalDate(lastDay);
                }
                filterForm.submit();
            });
        });

        const tabSummaryBtn = document.getElementById('tabSummaryBtn');
        const tabLogsBtn = document.getElementById('tabLogsBtn');
        const panelSummary = document.getElementById('panelSummary');
        const panelLogs = document.getElementById('panelLogs');

        function activateTab(tabName) {
            if (!tabSummaryBtn || !tabLogsBtn || !panelSummary || !panelLogs) {
                return;
            }
            const isSummary = tabName === 'summary';
            tabSummaryBtn.setAttribute('aria-selected', isSummary ? 'true' : 'false');
            tabLogsBtn.setAttribute('aria-selected', isSummary ? 'false' : 'true');

            if (isSummary) {
                tabSummaryBtn.className = 'pill-btn min-h-[44px] active';
                tabLogsBtn.className = 'pill-btn min-h-[44px]';
                panelSummary.classList.remove('hidden');
                panelLogs.classList.add('hidden');
            } else {
                tabLogsBtn.className = 'pill-btn min-h-[44px] active';
                tabSummaryBtn.className = 'pill-btn min-h-[44px]';
                panelLogs.classList.remove('hidden');
                panelSummary.classList.add('hidden');
            }
        }

        if (tabSummaryBtn && tabLogsBtn) {
            tabSummaryBtn.addEventListener('click', function () { activateTab('summary'); });
            tabLogsBtn.addEventListener('click', function () { activateTab('logs'); });
        }

        const modal = document.getElementById('detailModal');
        const modalTitle = document.getElementById('detailModalTitle');
        const modalSubtitle = document.getElementById('detailModalSubtitle');
        const modalBody = document.getElementById('detailModalBody');
        const closeBtn = document.getElementById('closeDetailModalBtn');
        let lastTriggerEl = null;

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function closeModal() {
            if (!modal) {
                return;
            }
            modal.classList.add('hidden');
            if (lastTriggerEl && typeof lastTriggerEl.focus === 'function') {
                lastTriggerEl.focus();
            }
        }

        async function loadDetail(nik, name) {
            if (!modal || !modalTitle || !modalSubtitle || !modalBody) {
                return;
            }

            modalTitle.textContent = name;
            modalSubtitle.textContent = `NIK: ${nik} · Periode ${startInput ? startInput.value : ''} s/d ${endInput ? endInput.value : ''}`;
            modalBody.innerHTML = '<div class="py-8 text-center text-sm font-semibold text-[#94a3b8]">Memuat rincian log presensi...</div>';
            modal.classList.remove('hidden');
            if (closeBtn) {
                closeBtn.focus();
            }

            try {
                const params = new URLSearchParams({
                    start_date: startInput ? startInput.value : '',
                    end_date: endInput ? endInput.value : '',
                });
                const url = @json(url('/rekap/' . $category . '/detail')) + '/' + encodeURIComponent(nik) + '?' + params.toString();
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                });
                const json = await res.json();

                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Gagal memuat rincian presensi.');
                }

                const logs = json.data.logs || [];
                if (logs.length === 0) {
                    modalBody.innerHTML = `
                        <div class="no-result-box space-y-1">
                            <p class="font-bold text-white">Belum ada catatan presensi</p>
                            <p class="text-xs text-[#94a3b8]">Tidak ditemukan log presensi untuk personil ini pada rentang tanggal yang dipilih.</p>
                        </div>
                    `;
                    return;
                }

                modalBody.innerHTML = logs.map(function (item) {
                    const lateClass = item.is_late ? 'badge-amber' : 'badge-em';
                    const appClass = item.approval_status === 'approved'
                        ? 'badge-em'
                        : (item.approval_status === 'rejected' ? 'badge-rose' : 'badge-amber');
                    const notesHtml = item.catatan
                        ? `<p class="text-xs text-[#94a3b8] mt-1">${escapeHtml(item.catatan)}</p>`
                        : '';

                    return `
                        <div class="rounded-2xl border p-3.5 space-y-2 pusimpel-card-alt">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="font-mono text-xs font-bold text-white">
                                    ${escapeHtml(item.tanggal)} · ${escapeHtml(item.waktu)} WIB
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="px-2.5 py-0.5 rounded-full border border-white/15 bg-white/10 text-xs font-semibold text-white">${escapeHtml(item.slot)}</span>
                                    <span class="px-2.5 py-0.5 rounded-full border text-xs font-bold ${lateClass}">${escapeHtml(item.status)}</span>
                                    <span class="px-2.5 py-0.5 rounded-full border text-xs font-bold ${appClass}">${escapeHtml(item.approval_label)}</span>
                                </div>
                            </div>
                            <div class="text-xs text-[#cbd5e1]">
                                <strong class="text-white">Lokasi:</strong> ${escapeHtml(item.lokasi)} (${escapeHtml(item.radius_label)})
                            </div>
                            ${notesHtml}
                        </div>
                    `;
                }).join('');
            } catch (err) {
                modalBody.innerHTML = `
                    <div role="alert" class="rounded-2xl border p-4 text-sm space-y-2 badge-rose">
                        <p class="font-bold">Gagal memuat rincian log</p>
                        <p>${escapeHtml(err.message || 'Terjadi kesalahan saat menghubungi server.')}</p>
                        <button type="button" id="retryDetailBtn" class="min-h-[40px] px-4 py-1.5 rounded-full text-xs pusimpel-btn-primary">
                            Coba Lagi
                        </button>
                    </div>
                `;
                const retryBtn = document.getElementById('retryDetailBtn');
                if (retryBtn) {
                    retryBtn.addEventListener('click', function () {
                        loadDetail(nik, name);
                    });
                }
            }
        }

        document.querySelectorAll('.detail-trigger').forEach(function (btn) {
            btn.addEventListener('click', function () {
                lastTriggerEl = btn;
                loadDetail(btn.getAttribute('data-detail-nik'), btn.getAttribute('data-detail-name'));
            });
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal || event.target === modal.firstElementChild) {
                    closeModal();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    })();
</script>
@endpush
