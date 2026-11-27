<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Office;
use App\Models\Presensi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceRecapService
{
    public const CATEGORY_PEGAWAI = 'pegawai';

    public const CATEGORY_NON_TETAP = 'non_tetap';

    /**
     * @var list<string>
     */
    public const NON_TETAP_STATUSES = ['Capeg', 'Honor', 'PKWT', 'Kontrak'];

    /**
     * Hitung ringkasan jumlah personil dan kehadiran bulan berjalan untuk halaman utama.
     *
     * @return array{
     *     pegawai_count: int,
     *     non_tetap_count: int,
     *     non_tetap_breakdown: array<string, int>,
     *     period_start: string,
     *     period_end: string,
     *     pegawai_presensi_count: int,
     *     non_tetap_presensi_count: int
     * }
     */
    public function getDashboardSummary(): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $periodStart = $now->copy()->startOfMonth()->toDateString();
        $periodEnd = $now->copy()->endOfMonth()->toDateString();

        $pegawai = $this->getPersonnelByCategory(self::CATEGORY_PEGAWAI);
        $nonTetap = $this->getPersonnelByCategory(self::CATEGORY_NON_TETAP);

        $breakdown = [];
        foreach (self::NON_TETAP_STATUSES as $status) {
            $breakdown[$status] = $nonTetap->where('status_label', $status)->count();
        }

        return [
            'pegawai_count' => $pegawai->count(),
            'non_tetap_count' => $nonTetap->count(),
            'non_tetap_breakdown' => $breakdown,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'pegawai_presensi_count' => $this->countPresentPersonnel($pegawai, $periodStart, $periodEnd),
            'non_tetap_presensi_count' => $this->countPresentPersonnel($nonTetap, $periodStart, $periodEnd),
        ];
    }

    /**
     * Ambil daftar personil aktif terklasifikasi menurut kategori rekap.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getPersonnelByCategory(string $category): Collection
    {
        $usersByNik = User::query()
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (User $user): string => strtoupper(trim((string) $user->nik)));

        $employees = Employee::query()
            ->where(function ($query): void {
                $query->where('ishapus', 0)->orWhereNull('ishapus');
            })
            ->orderBy('pgw_nama', 'asc')
            ->get();

        $records = collect();
        $allEmployeeNiks = [];

        foreach ($employees as $employee) {
            $nup = trim((string) $employee->pgw_nup);
            $upperNup = strtoupper($nup);

            if ($nup === '') {
                continue;
            }

            $allEmployeeNiks[$upperNup] = true;

            if ($employee->recapCategory() !== $category) {
                continue;
            }

            $matchedUser = $usersByNik->get($upperNup);
            $department = $matchedUser?->department ?: $employee->departmentLabel();
            $position = $matchedUser?->position ?: $employee->positionLabel();
            $officeId = $matchedUser?->office_id ?: ($employee->office_id ?: 'hq');
            $officeName = $matchedUser?->office_name ?: ($employee->office_name ?: 'Kantor Pusat PERUMDA Tirta Kepri');

            $records->push([
                'pgw_id' => (int) $employee->pgw_id,
                'nik' => $nup,
                'name' => trim((string) $employee->pgw_nama),
                'status_label' => $employee->statusLabel(),
                'department' => $department,
                'position' => $position,
                'office_id' => $officeId,
                'office_name' => $officeName,
                'phone' => $matchedUser?->phone ?: ($employee->pgw_nohp ?: '-'),
                'source' => 'employee',
            ]);
        }

        foreach ($usersByNik as $upperNik => $user) {
            if (isset($allEmployeeNiks[$upperNik])) {
                continue;
            }

            if ($user->role === 'staffIT' && strtoupper((string) $user->nik) === 'P242101') {
                continue;
            }

            $userClassification = $this->classifyUserOnly($user);
            if ($userClassification['category'] !== $category) {
                continue;
            }

            $records->push([
                'pgw_id' => null,
                'nik' => trim((string) $user->nik),
                'name' => trim((string) $user->name),
                'status_label' => $userClassification['status_label'],
                'department' => $user->department ?: 'Unit Umum',
                'position' => $user->position ?: 'Staf',
                'office_id' => $user->office_id ?: 'hq',
                'office_name' => $user->office_name ?: 'Kantor Pusat PERUMDA Tirta Kepri',
                'phone' => $user->phone ?: '-',
                'source' => 'users',
            ]);
        }

        return $records->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * Bangun data rekap terpadu dari attendance, attendant, dan tirtafield.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildRecap(string $category, array $filters): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $startDate = $this->sanitizeDate($filters['start_date'] ?? null) ?? $now->copy()->startOfMonth()->toDateString();
        $endDate = $this->sanitizeDate($filters['end_date'] ?? null) ?? $now->copy()->endOfMonth()->toDateString();

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $search = trim((string) ($filters['q'] ?? ''));
        $department = trim((string) ($filters['department'] ?? ''));
        $officeId = trim((string) ($filters['office_id'] ?? ''));
        $subStatus = trim((string) ($filters['sub_status'] ?? ''));
        $attendanceFilter = trim((string) ($filters['attendance_filter'] ?? 'all'));

        $allPersonnel = $this->getPersonnelByCategory($category);

        $departments = $allPersonnel->pluck('department')->filter()->unique()->sort()->values();
        $offices = Office::query()->where('is_active', true)->orderBy('nama_kantor', 'asc')->get();

        $filteredPersonnel = $allPersonnel->filter(function (array $person) use ($search, $department, $officeId, $subStatus): bool {
            if ($search !== '') {
                $needle = mb_strtolower($search);
                $haystack = mb_strtolower($person['name'].' '.$person['nik'].' '.$person['department'].' '.$person['position']);
                if (! str_contains($haystack, $needle)) {
                    return false;
                }
            }

            if ($department !== '' && $person['department'] !== $department) {
                return false;
            }

            if ($officeId !== '' && $person['office_id'] !== $officeId) {
                return false;
            }

            if ($subStatus !== '' && strcasecmp($person['status_label'], $subStatus) !== 0) {
                return false;
            }

            return true;
        })->values();

        $dailyRecords = $this->fetchUnifiedDailyRecords($filteredPersonnel, $startDate, $endDate);
        $recordsByNik = $dailyRecords->groupBy(fn (array $item): string => strtoupper($item['nik']));

        $rows = $filteredPersonnel->map(function (array $person) use ($recordsByNik): array {
            $upperNik = strtoupper(trim((string) $person['nik']));
            /** @var Collection<int, array<string, mixed>> $logs */
            $logs = $recordsByNik->get($upperNik, collect());

            $scanLogs = $logs->filter(fn (array $log): bool => $log['has_scan']);
            $izinLogs = $logs->filter(fn (array $log): bool => ! $log['has_scan'] && $log['has_reason']);

            $masukCount = $scanLogs->filter(fn (array $log): bool => $log['scan1'] !== '-')->count();
            $siangKeluarCount = $scanLogs->filter(fn (array $log): bool => $log['scan2'] !== '-')->count();
            $siangMasukCount = $scanLogs->filter(fn (array $log): bool => $log['scan3'] !== '-')->count();
            $pulangCount = $scanLogs->filter(fn (array $log): bool => $log['scan4'] !== '-')->count();

            $hadirPenuhCount = $scanLogs->filter(fn (array $log): bool => $log['scan1'] !== '-' && $log['scan4'] !== '-')->count();
            $belumPulangCount = $scanLogs->filter(fn (array $log): bool => $log['scan1'] !== '-' && $log['scan4'] === '-')->count();

            $tepatWaktuCount = $scanLogs->filter(fn (array $log): bool => ! $log['is_late'])->count();
            $terlambatCount = $scanLogs->filter(fn (array $log): bool => $log['is_late'])->count();

            $latestLog = $logs->first();

            return array_merge($person, [
                'hari_hadir' => $scanLogs->count(),
                'hadir_penuh_count' => $hadirPenuhCount,
                'belum_pulang_count' => $belumPulangCount,
                'izin_count' => $izinLogs->count(),
                'total_log' => $logs->count(),
                'masuk_count' => $masukCount,
                'istirahat_keluar_count' => $siangKeluarCount,
                'istirahat_masuk_count' => $siangMasukCount,
                'pulang_count' => $pulangCount,
                'tepat_waktu_count' => $tepatWaktuCount,
                'terlambat_count' => $terlambatCount,
                'last_punch_date' => $latestLog['tanggal'] ?? null,
                'last_status' => $latestLog['status_kehadiran'] ?? null,
            ]);
        });

        if ($attendanceFilter === 'present') {
            $rows = $rows->filter(fn (array $row): bool => $row['hari_hadir'] > 0)->values();
        } elseif ($attendanceFilter === 'absent') {
            $rows = $rows->filter(fn (array $row): bool => $row['hari_hadir'] === 0 && $row['izin_count'] === 0)->values();
        } elseif ($attendanceFilter === 'late') {
            $rows = $rows->filter(fn (array $row): bool => $row['terlambat_count'] > 0)->values();
        } elseif ($attendanceFilter === 'reason') {
            $rows = $rows->filter(fn (array $row): bool => $row['izin_count'] > 0)->values();
        }

        $visibleNiks = $rows->pluck('nik')->map(fn (string $n): string => strtoupper($n))->all();
        $dailyLogs = $dailyRecords
            ->filter(fn (array $log): bool => in_array(strtoupper($log['nik']), $visibleNiks, true))
            ->values();

        $summary = [
            'total_personil' => $rows->count(),
            'personil_hadir' => $rows->filter(fn (array $row): bool => $row['hari_hadir'] > 0)->count(),
            'total_hari_hadir' => $rows->sum('hari_hadir'),
            'total_hadir_penuh' => $rows->sum('hadir_penuh_count'),
            'total_izin' => $rows->sum('izin_count'),
            'total_tepat_waktu' => $rows->sum('tepat_waktu_count'),
            'total_terlambat' => $rows->sum('terlambat_count'),
            'total_log' => $rows->sum('total_log'),
        ];

        $statusBreakdown = [];
        if ($category === self::CATEGORY_NON_TETAP) {
            foreach (self::NON_TETAP_STATUSES as $status) {
                $statusBreakdown[$status] = $allPersonnel->where('status_label', $status)->count();
            }
        } else {
            $statusBreakdown['Pegawai Tetap'] = $allPersonnel->where('status_label', 'Pegawai Tetap')->count();
            $statusBreakdown['Direksi'] = $allPersonnel->where('status_label', 'Direksi')->count();
        }

        return [
            'category' => $category,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'q' => $search,
                'department' => $department,
                'office_id' => $officeId,
                'sub_status' => $subStatus,
                'attendance_filter' => $attendanceFilter,
            ],
            'summary' => $summary,
            'status_breakdown' => $statusBreakdown,
            'rows' => $rows,
            'daily_logs' => $dailyLogs,
            'departments' => $departments,
            'offices' => $offices,
        ];
    }

    /**
     * Ambil rincian log kehadiran harian satu pegawai dari ketiga sumber.
     *
     * @return array<string, mixed>|null
     */
    public function getEmployeeDetail(string $category, string $nik, ?string $startDate = null, ?string $endDate = null): ?array
    {
        $now = Carbon::now('Asia/Jakarta');
        $start = $this->sanitizeDate($startDate) ?? $now->copy()->startOfMonth()->toDateString();
        $end = $this->sanitizeDate($endDate) ?? $now->copy()->endOfMonth()->toDateString();

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $person = $this->getPersonnelByCategory($category)
            ->first(fn (array $item): bool => strcasecmp($item['nik'], trim($nik)) === 0);

        if (! $person) {
            return null;
        }

        $logs = $this->fetchUnifiedDailyRecords(collect([$person]), $start, $end)->values();

        return [
            'person' => $person,
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'logs' => $logs,
        ];
    }

    /**
     * Tarik dan gabungkan rekap harian dari table1/tempdatascan (attendance),
     * attendant+reason (attendant), dan presensi (tirtafield).
     *
     * @param  Collection<int, array<string, mixed>>  $personnel
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchUnifiedDailyRecords(Collection $personnel, string $startDate, string $endDate): Collection
    {
        if ($personnel->isEmpty()) {
            return collect();
        }

        $niks = $personnel->pluck('nik')->filter()->unique()->values()->all();
        $empIds = $personnel->pluck('pgw_id')->filter(fn ($id): bool => ! empty($id))->unique()->values()->all();

        $personByNik = $personnel->keyBy(fn (array $p): string => strtoupper(trim((string) $p['nik'])));
        $personByEmpId = $personnel
            ->filter(fn (array $p): bool => ! empty($p['pgw_id']))
            ->keyBy(fn (array $p): int => (int) $p['pgw_id']);

        $table1Map = [];
        $midScanMap = [];
        $attendantMap = [];

        $kepegConn = $this->kepegawaianConnectionName();

        try {
            if (! empty($empIds) && Schema::connection($kepegConn)->hasTable('table1')) {
                $table1Rows = DB::connection($kepegConn)
                    ->table('table1')
                    ->select(['emp_id', 'dtgl', 'inpresent', 'outpresent'])
                    ->whereIn('emp_id', $empIds)
                    ->whereBetween('dtgl', [$startDate, $endDate])
                    ->where(function ($query): void {
                        $query->where(function ($q): void {
                            $q->whereNotNull('inpresent')->where('inpresent', '!=', '00:00:00')->where('inpresent', '!=', '');
                        })->orWhere(function ($q): void {
                            $q->whereNotNull('outpresent')->where('outpresent', '!=', '00:00:00')->where('outpresent', '!=', '');
                        });
                    })
                    ->get();

                foreach ($table1Rows as $row) {
                    $person = $personByEmpId->get((int) $row->emp_id);
                    if (! $person) {
                        continue;
                    }
                    $dateKey = substr((string) $row->dtgl, 0, 10);
                    $key = strtoupper($person['nik']).'|'.$dateKey;
                    $table1Map[$key] = [
                        'inpresent' => $this->normalizeTime((string) ($row->inpresent ?? '')),
                        'outpresent' => $this->normalizeTime((string) ($row->outpresent ?? '')),
                    ];
                }
            }

            if (Schema::connection($kepegConn)->hasTable('tempdatascan')) {
                $scanUserIds = array_values(array_unique(array_merge(
                    array_map('strval', $empIds),
                    $niks
                )));

                if (! empty($scanUserIds)) {
                    $scanRows = DB::connection($kepegConn)
                        ->table('tempdatascan')
                        ->select(['userid', 'checkdate', 'checktime'])
                        ->whereIn('userid', $scanUserIds)
                        ->whereBetween('checkdate', [$startDate, $endDate])
                        ->orderBy('checkdate', 'asc')
                        ->orderBy('checktime', 'asc')
                        ->get();

                    foreach ($scanRows as $row) {
                        $rawUser = trim((string) $row->userid);
                        $person = $personByNik->get(strtoupper($rawUser));
                        if (! $person && ctype_digit($rawUser)) {
                            $person = $personByEmpId->get((int) $rawUser);
                        }
                        if (! $person) {
                            continue;
                        }

                        $dateKey = substr((string) $row->checkdate, 0, 10);
                        $jam = $this->normalizeTime((string) $row->checktime);
                        if ($jam === '-') {
                            continue;
                        }

                        $key = strtoupper($person['nik']).'|'.$dateKey;
                        $midScanMap[$key][] = $jam;
                    }
                }
            }

            if (! empty($empIds) && Schema::connection($kepegConn)->hasTable('attendant')) {
                $attQuery = DB::connection($kepegConn)
                    ->table('attendant as a')
                    ->whereIn('a.emp_id', $empIds)
                    ->whereBetween('a.att_date', [$startDate, $endDate]);

                if (Schema::connection($kepegConn)->hasTable('reason')) {
                    $attQuery->leftJoin('reason as r', 'a.reason_id', '=', 'r.idx')
                        ->select(['a.emp_id', 'a.att_date', 'a.keterangan', 'r.re_desc']);
                } else {
                    $attQuery->select(['a.emp_id', 'a.att_date', 'a.keterangan', DB::raw('NULL as re_desc')]);
                }

                foreach ($attQuery->get() as $row) {
                    $person = $personByEmpId->get((int) $row->emp_id);
                    if (! $person) {
                        continue;
                    }
                    $dateKey = substr((string) $row->att_date, 0, 10);
                    $key = strtoupper($person['nik']).'|'.$dateKey;
                    $attendantMap[$key] = [
                        'reason' => $this->normalizeAttendantReason((string) ($row->re_desc ?? '')),
                        'notes' => trim((string) ($row->keterangan ?? '')),
                    ];
                }
            }
        } catch (\Throwable) {
            // Tetap kembalikan data dari Tirtafield bila koneksi kepri_kepegawaian sedang tidak tersedia
        }

        $tirtafieldMap = [];
        if (! empty($niks)) {
            $presensiRows = Presensi::query()
                ->whereIn('nik', $niks)
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->orderBy('tanggal', 'asc')
                ->orderBy('waktu', 'asc')
                ->get();

            foreach ($presensiRows as $row) {
                $dateKey = $row->tanggal ? $row->tanggal->format('Y-m-d') : '';
                if ($dateKey === '') {
                    continue;
                }
                $key = strtoupper(trim((string) $row->nik)).'|'.$dateKey;
                $slot = $row->normalizedSlot();

                if (! isset($tirtafieldMap[$key])) {
                    $tirtafieldMap[$key] = [
                        'masuk' => '-',
                        'istirahatKeluar' => '-',
                        'istirahatMasuk' => '-',
                        'pulang' => '-',
                        'is_late' => false,
                        'notes' => [],
                        'locations' => [],
                    ];
                }

                $tirtafieldMap[$key][$slot] = $row->formattedWaktu();
                if ($slot === 'masuk' && $row->isLate()) {
                    $tirtafieldMap[$key]['is_late'] = true;
                    $tirtafieldMap[$key]['notes'][] = $row->status ?: 'Terlambat';
                }
                if (! empty($row->catatan)) {
                    $tirtafieldMap[$key]['notes'][] = trim((string) $row->catatan);
                }
                if (! empty($row->lokasi)) {
                    $tirtafieldMap[$key]['locations'][] = trim((string) $row->lokasi);
                }
            }
        }

        $allKeys = array_values(array_unique(array_merge(
            array_keys($table1Map),
            array_keys($attendantMap),
            array_keys($tirtafieldMap)
        )));

        $unified = collect();

        foreach ($allKeys as $key) {
            [$upperNik, $dateKey] = explode('|', $key, 2);
            $person = $personByNik->get($upperNik);
            if (! $person) {
                continue;
            }

            $t1 = $table1Map[$key] ?? null;
            $rawMidScans = array_values(array_unique($midScanMap[$key] ?? []));
            $att = $attendantMap[$key] ?? null;
            $tf = $tirtafieldMap[$key] ?? null;

            $carbonDate = Carbon::parse($dateKey);
            $hariIndo = $this->hariIndonesia($carbonDate->format('l'));
            $isWeekend = in_array($hariIndo, ['Sabtu', 'Minggu'], true);

            $scan1 = $t1['inpresent'] ?? '-';
            $scan4 = $t1['outpresent'] ?? '-';

            if ($scan1 === '-' && $tf !== null && $tf['masuk'] !== '-') {
                $scan1 = $tf['masuk'];
            }
            if ($scan4 === '-' && $tf !== null && $tf['pulang'] !== '-') {
                $scan4 = $tf['pulang'];
            }

            [$scan2, $scan3, $isShift] = $this->resolveMiddayScans($scan1, $scan4, $rawMidScans, $isWeekend);

            if ($scan2 === '-' && $tf !== null && $tf['istirahatKeluar'] !== '-') {
                $scan2 = $tf['istirahatKeluar'];
            }
            if ($scan3 === '-' && $tf !== null && $tf['istirahatMasuk'] !== '-') {
                $scan3 = $tf['istirahatMasuk'];
            }

            $hasScan = ($scan1 !== '-' || $scan2 !== '-' || $scan3 !== '-' || $scan4 !== '-');
            $hasReason = $att !== null && ($att['reason'] !== '' || $att['notes'] !== '');

            if (! $hasScan && ! $hasReason) {
                continue;
            }

            $sources = [];
            if ($t1 !== null) {
                $sources[] = 'Attendance';
            }
            if ($hasReason) {
                $sources[] = 'Attendant';
            }
            if ($tf !== null) {
                $sources[] = 'Tirtafield';
            }

            $notes = [];
            $isLate = false;

            if ($hasScan) {
                if ($scan1 !== '-' && $scan4 !== '-') {
                    $statusKehadiran = $isWeekend ? 'Hadir (Hari Libur)' : 'Hadir Penuh';
                } elseif ($scan1 !== '-' && $scan4 === '-') {
                    $statusKehadiran = 'Belum Pulang';
                } elseif ($scan1 === '-' && $scan4 !== '-') {
                    $statusKehadiran = 'Pulang Saja';
                } else {
                    $statusKehadiran = 'Scan Siang';
                }

                if ($t1 !== null && ! $isWeekend) {
                    [$fpLate, $fpNotes] = $this->evaluateFingerprintPunctuality($scan1, $scan2, $scan3, $scan4, $hariIndo, $isShift);
                    if ($fpLate) {
                        $isLate = true;
                    }
                    $notes = array_merge($notes, $fpNotes);
                }

                if ($tf !== null) {
                    if ($tf['is_late']) {
                        $isLate = true;
                    }
                    $notes = array_merge($notes, $tf['notes']);
                }

                if ($att !== null && $att['reason'] !== '') {
                    $notes[] = $att['reason'].($att['notes'] !== '' ? ': '.$att['notes'] : '');
                }
            } else {
                $statusKehadiran = $att['reason'] !== '' ? $att['reason'] : 'Keterangan';
                if ($att['notes'] !== '') {
                    $notes[] = $att['notes'];
                }
            }

            $lokasi = ($tf !== null && ! empty($tf['locations']))
                ? implode(', ', array_unique($tf['locations']))
                : ($t1 !== null ? 'Mesin Fingerprint' : $person['office_name']);

            $unified->push([
                'nik' => $person['nik'],
                'name' => $person['name'],
                'status_label' => $person['status_label'],
                'department' => $person['department'],
                'tanggal' => $dateKey,
                'hari' => $hariIndo,
                'scan1' => $scan1,
                'scan2' => $scan2,
                'scan3' => $scan3,
                'scan4' => $scan4,
                'has_scan' => $hasScan,
                'has_reason' => $hasReason,
                'status_kehadiran' => $statusKehadiran,
                'is_late' => $isLate,
                'ketepatan' => $hasScan ? ($isLate ? 'Terlambat' : 'Tepat Waktu') : 'Keterangan',
                'lokasi' => $lokasi,
                'catatan' => implode(' · ', array_values(array_unique(array_filter($notes)))),
                'sumber' => implode(' + ', $sources),
            ]);
        }

        return $unified->sort(function (array $a, array $b): int {
            $cmpDate = strcmp($b['tanggal'], $a['tanggal']);
            if ($cmpDate !== 0) {
                return $cmpDate;
            }

            return strcasecmp($a['name'], $b['name']);
        })->values();
    }

    /**
     * Hitung jumlah personil unik yang memiliki catatan scan/kehadiran pada rentang tanggal.
     *
     * @param  Collection<int, array<string, mixed>>  $personnel
     */
    private function countPresentPersonnel(Collection $personnel, string $startDate, string $endDate): int
    {
        if ($personnel->isEmpty()) {
            return 0;
        }

        $presentNiks = [];
        $niks = $personnel->pluck('nik')->filter()->unique()->values()->all();
        $empIds = $personnel->pluck('pgw_id')->filter(fn ($id): bool => ! empty($id))->unique()->values()->all();
        $nikByEmpId = $personnel
            ->filter(fn (array $p): bool => ! empty($p['pgw_id']))
            ->mapWithKeys(fn (array $p): array => [(int) $p['pgw_id'] => strtoupper($p['nik'])])
            ->all();

        $kepegConn = $this->kepegawaianConnectionName();

        try {
            if (! empty($empIds) && Schema::connection($kepegConn)->hasTable('table1')) {
                $matchedEmpIds = DB::connection($kepegConn)
                    ->table('table1')
                    ->whereIn('emp_id', $empIds)
                    ->whereBetween('dtgl', [$startDate, $endDate])
                    ->where(function ($query): void {
                        $query->where(function ($q): void {
                            $q->whereNotNull('inpresent')->where('inpresent', '!=', '00:00:00')->where('inpresent', '!=', '');
                        })->orWhere(function ($q): void {
                            $q->whereNotNull('outpresent')->where('outpresent', '!=', '00:00:00')->where('outpresent', '!=', '');
                        });
                    })
                    ->distinct()
                    ->pluck('emp_id');

                foreach ($matchedEmpIds as $empId) {
                    if (isset($nikByEmpId[(int) $empId])) {
                        $presentNiks[$nikByEmpId[(int) $empId]] = true;
                    }
                }
            }
        } catch (\Throwable) {
            // Abaikan jika koneksi kepegawaian tidak dapat dijangkau
        }

        if (! empty($niks)) {
            $tfNiks = Presensi::query()
                ->whereIn('nik', $niks)
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->distinct()
                ->pluck('nik');

            foreach ($tfNiks as $nik) {
                $presentNiks[strtoupper(trim((string) $nik))] = true;
            }
        }

        return count($presentNiks);
    }

    /**
     * Pisahkan scan siang keluar & masuk sesuai aturan deteksi shift di attendance/index.php.
     *
     * @param  list<string>  $scansToday
     * @return array{0: string, 1: string, 2: bool}
     */
    private function resolveMiddayScans(string $scan1, string $scan4, array $scansToday, bool $isWeekend): array
    {
        $tScan1 = $scan1 !== '-' ? strtotime($scan1) : false;
        $tScan4 = $scan4 !== '-' ? strtotime($scan4) : false;

        $isShiftPagi = ($tScan1 !== false && $tScan1 <= strtotime('08:00') && $tScan4 !== false && $tScan4 >= strtotime('13:30') && $tScan4 <= strtotime('15:30'));
        $isShiftSiang = ($tScan1 !== false && (
            ($tScan1 >= strtotime('13:00') && $tScan1 < strtotime('18:00')) ||
            ($tScan1 >= strtotime('12:00') && $tScan1 < strtotime('13:00') && $tScan4 !== false && $tScan4 >= strtotime('20:00'))
        ));
        $isShiftMalam = ($tScan1 !== false && $tScan1 >= strtotime('18:00'));
        $isShiftTrans = ($tScan4 !== false && $tScan4 >= strtotime('20:00'));
        $isShift = ($isShiftPagi || $isShiftSiang || $isShiftMalam || $isShiftTrans);

        $scan2 = '-';
        $scan3 = '-';

        if (! $isWeekend && ! empty($scansToday)) {
            $midScans = [];
            foreach ($scansToday as $jamStr) {
                $time = strtotime($jamStr);
                if ($time !== false && $time >= strtotime('11:00') && $time < strtotime('15:30')) {
                    if ($isShift) {
                        if ($tScan1 !== false && abs($time - $tScan1) <= 900) {
                            continue;
                        }
                        if ($tScan4 !== false && abs($time - $tScan4) <= 900) {
                            continue;
                        }
                    }
                    $midScans[] = $jamStr;
                }
            }

            $countMid = count($midScans);
            if ($countMid === 1) {
                if (strtotime($midScans[0]) < strtotime('13:00')) {
                    $scan2 = $midScans[0];
                } else {
                    $scan3 = $midScans[0];
                }
            } elseif ($countMid >= 2) {
                $scan2 = $midScans[0];
                $scan3 = $midScans[1];
            }
        }

        return [$scan2, $scan3, $isShift];
    }

    /**
     * Evaluasi keterlambatan scan fingerprint sesuai aturan attendance/index.php.
     *
     * @return array{0: bool, 1: list<string>}
     */
    private function evaluateFingerprintPunctuality(string $scan1, string $scan2, string $scan3, string $scan4, string $hariIndo, bool $isShift): array
    {
        $isLate = false;
        $notes = [];

        $tScan1 = $scan1 !== '-' ? strtotime($scan1) : false;
        $tScan4 = $scan4 !== '-' ? strtotime($scan4) : false;

        $isShiftSiang = ($tScan1 !== false && (
            ($tScan1 >= strtotime('13:00') && $tScan1 < strtotime('18:00')) ||
            ($tScan1 >= strtotime('12:00') && $tScan1 < strtotime('13:00') && $tScan4 !== false && $tScan4 >= strtotime('20:00'))
        ));
        $isShiftMalam = ($tScan1 !== false && $tScan1 >= strtotime('18:00'));

        if ($tScan1 !== false) {
            if ($isShiftSiang) {
                if ($tScan1 > strtotime('14:00')) {
                    $isLate = true;
                    $notes[] = 'Telat Masuk Shift Siang';
                }
            } elseif ($isShiftMalam) {
                if ($tScan1 > strtotime('22:00')) {
                    $isLate = true;
                    $notes[] = 'Telat Masuk Shift Malam';
                }
            } elseif ($tScan1 > strtotime('08:00')) {
                $isLate = true;
                $notes[] = 'Telat Masuk Pagi';
            }
        }

        if (! $isShift) {
            if ($scan2 !== '-') {
                $limitKeluar = $hariIndo === 'Jumat' ? strtotime('11:30') : strtotime('12:00');
                if (strtotime($scan2) < $limitKeluar) {
                    $notes[] = 'Cepat Keluar Siang';
                }
            }

            if ($scan3 !== '-' && strtotime($scan3) > strtotime('13:00')) {
                $notes[] = 'Telat Masuk Siang';
            }
        }

        return [$isLate, $notes];
    }

    /**
     * Normalisasi kode alasan dari tabel reason milik attendant.
     */
    private function normalizeAttendantReason(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }

        if (in_array($trimmed, ['25', '25%', '21', 'k25', 'K25'], true)) {
            return 'Izin Khusus';
        }

        return $trimmed;
    }

    private function normalizeTime(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '00:00:00' || $trimmed === '0000-00-00 00:00:00') {
            return '-';
        }

        try {
            return Carbon::parse($trimmed)->format('H:i');
        } catch (\Throwable) {
            if (preg_match('/(\d{2}:\d{2})/', $trimmed, $matches)) {
                return $matches[1];
            }

            return '-';
        }
    }

    private function hariIndonesia(string $dayEnglish): string
    {
        return match ($dayEnglish) {
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            default => $dayEnglish,
        };
    }

    private function kepegawaianConnectionName(): string
    {
        return (string) config('database.kepegawaian_connection', 'kepegawaian');
    }

    /**
     * Klasifikasi akun yang hanya ada di tabel users tanpa padanan di tabel employee.
     *
     * @return array{category: string, status_label: string}
     */
    private function classifyUserOnly(User $user): array
    {
        $status = trim((string) $user->status_pegawai);
        $lower = mb_strtolower($status);

        if (str_contains($lower, 'capeg')) {
            return ['category' => self::CATEGORY_NON_TETAP, 'status_label' => 'Capeg'];
        }

        if (str_contains($lower, 'honor')) {
            return ['category' => self::CATEGORY_NON_TETAP, 'status_label' => 'Honor'];
        }

        if (str_contains($lower, 'pkwt')) {
            return ['category' => self::CATEGORY_NON_TETAP, 'status_label' => 'PKWT'];
        }

        if (str_contains($lower, 'kontrak')) {
            return ['category' => self::CATEGORY_NON_TETAP, 'status_label' => 'Kontrak'];
        }

        if ($user->role === 'direktur' || str_contains($lower, 'direksi')) {
            return ['category' => self::CATEGORY_PEGAWAI, 'status_label' => 'Direksi'];
        }

        return ['category' => self::CATEGORY_PEGAWAI, 'status_label' => 'Pegawai Tetap'];
    }

    private function sanitizeDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
