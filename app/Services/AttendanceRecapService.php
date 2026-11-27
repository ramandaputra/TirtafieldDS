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
use stdClass;

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
     * Ambil daftar personil aktif dari employee kepri_kepegawaian (sumber attendance & attendant).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getPersonnelByCategory(string $category): Collection
    {
        $usersByNik = User::query()
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (User $user): string => strtoupper(trim((string) $user->nik)));

        $employees = $this->loadEmployeesFromSource();
        $records = collect();

        foreach ($employees as $employee) {
            $nup = trim((string) ($employee->pgw_nup ?? ''));
            if ($nup === '') {
                continue;
            }

            $classified = $this->classifyEmployeeRecord($employee);
            if ($classified === null || $classified['category'] !== $category) {
                continue;
            }

            $upperNup = strtoupper($nup);
            $matchedUser = $usersByNik->get($upperNup);
            $deptId = isset($employee->dept_id) ? (int) $employee->dept_id : null;
            $occId = isset($employee->occ_id) ? (int) $employee->occ_id : null;

            $department = $matchedUser?->department
                ?: ($deptId !== null && isset(Employee::DEPARTMENT_MAP[$deptId]) ? Employee::DEPARTMENT_MAP[$deptId] : 'Unit Umum');

            $position = $matchedUser?->position ?: $this->resolvePositionLabel($employee, $occId);
            $officeId = $matchedUser?->office_id ?: (($employee->office_id ?? null) ?: 'hq');
            $officeName = $matchedUser?->office_name ?: (($employee->office_name ?? null) ?: 'Kantor Pusat PERUMDA Tirta Kepri');

            $records->push([
                'pgw_id' => (int) $employee->pgw_id,
                'nik' => $nup,
                'name' => trim((string) $employee->pgw_nama),
                'status_label' => $classified['status_label'],
                'department' => $department,
                'position' => $position,
                'office_id' => $officeId,
                'office_name' => $officeName,
                'phone' => $matchedUser?->phone ?: (($employee->pgw_nohp ?? null) ?: '-'),
                'source' => 'employee',
            ]);
        }

        return $records->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * Bangun rekap absensi dengan logika table1, tempdatascan (attendance) dan attendant+reason (attendant).
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

        $dailyRecords = $this->fetchAttendanceAndAttendantRecords($filteredPersonnel, $startDate, $endDate, false);
        $recordsByNik = $dailyRecords->groupBy(fn (array $item): string => strtoupper($item['nik']));

        $rows = $filteredPersonnel->map(function (array $person) use ($recordsByNik): array {
            $upperNik = strtoupper(trim((string) $person['nik']));
            /** @var Collection<int, array<string, mixed>> $logs */
            $logs = $recordsByNik->get($upperNik, collect());

            $scanLogs = $logs->filter(fn (array $log): bool => $log['has_scan']);
            $izinLogs = $logs->filter(fn (array $log): bool => ! $log['has_table1_scan'] && $log['has_reason']);
            $alpaLogs = $logs->filter(fn (array $log): bool => $log['status_kehadiran'] === '(-)');

            $masukCount = $scanLogs->filter(fn (array $log): bool => $log['scan1'] !== '-')->count();
            $siangKeluarCount = $scanLogs->filter(fn (array $log): bool => $log['scan2'] !== '-')->count();
            $siangMasukCount = $scanLogs->filter(fn (array $log): bool => $log['scan3'] !== '-')->count();
            $pulangCount = $scanLogs->filter(fn (array $log): bool => $log['scan4'] !== '-')->count();

            $hadirPenuhCount = $scanLogs->filter(fn (array $log): bool => $log['scan1'] !== '-' && $log['scan4'] !== '-')->count();
            $hadirWdCount = $scanLogs->filter(fn (array $log): bool => $log['status_kehadiran'] === 'Hadir Penuh')->count();
            $hadirWeCount = $scanLogs->filter(fn (array $log): bool => $log['status_kehadiran'] === 'Hadir (Hari Libur)')->count();
            $belumPulangCount = $scanLogs->filter(fn (array $log): bool => $log['status_kehadiran'] === 'Belum Pulang')->count();
            $pulangSajaCount = $scanLogs->filter(fn (array $log): bool => $log['status_kehadiran'] === 'Pulang Saja')->count();

            $tepatWaktuCount = $scanLogs->filter(fn (array $log): bool => ! $log['is_late'])->count();
            $terlambatCount = $scanLogs->filter(fn (array $log): bool => $log['is_late'])->count();
            $activeLogs = $logs->filter(fn (array $log): bool => $log['has_scan'] || $log['has_reason']);
            $latestLog = $activeLogs->first() ?: $logs->first();

            return array_merge($person, [
                'hari_hadir' => $scanLogs->count(),
                'hadir_penuh_count' => $hadirPenuhCount,
                'hadir_wd_count' => $hadirWdCount,
                'hadir_we_count' => $hadirWeCount,
                'belum_pulang_count' => $belumPulangCount,
                'pulang_saja_count' => $pulangSajaCount,
                'izin_count' => $izinLogs->count(),
                'alpa_count' => $alpaLogs->count(),
                'total_log' => $activeLogs->count(),
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
            ->filter(fn (array $log): bool => in_array(strtoupper($log['nik']), $visibleNiks, true)
                && ($log['has_scan'] || $log['has_reason'] || $log['status_kehadiran'] === '(-)'))
            ->values();

        $summary = [
            'total_personil' => $rows->count(),
            'personil_hadir' => $rows->filter(fn (array $row): bool => $row['hari_hadir'] > 0)->count(),
            'total_hari_hadir' => $rows->sum('hari_hadir'),
            'total_hadir_penuh' => $rows->sum('hadir_penuh_count'),
            'total_izin' => $rows->sum('izin_count'),
            'total_alpa' => $rows->sum('alpa_count'),
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
     * Bangun rekap absensi bulanan per pegawai (UX langsung seperti attendance & attendant).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildMonthlyRecap(string $category, array $filters): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $rawBulan = (int) ($filters['bulan'] ?? $now->month);
        $bulanInt = ($rawBulan >= 1 && $rawBulan <= 12) ? $rawBulan : $now->month;
        $bulan = sprintf('%02d', $bulanInt);

        $rawTahun = (int) ($filters['tahun'] ?? $now->year);
        $tahunInt = ($rawTahun >= 2000 && $rawTahun <= 2100) ? $rawTahun : $now->year;
        $tahun = (string) $tahunInt;

        $subStatus = trim((string) ($filters['sub_status'] ?? ''));
        $requestedNik = trim((string) ($filters['nik'] ?? ''));

        $allPersonnel = $this->getPersonnelByCategory($category);
        $statusBreakdown = [];
        if ($category === self::CATEGORY_NON_TETAP) {
            foreach (self::NON_TETAP_STATUSES as $status) {
                $statusBreakdown[$status] = $allPersonnel->where('status_label', $status)->count();
            }
        } else {
            $statusBreakdown['Pegawai Tetap'] = $allPersonnel->where('status_label', 'Pegawai Tetap')->count();
            $statusBreakdown['Direksi'] = $allPersonnel->where('status_label', 'Direksi')->count();
        }

        $personnel = $subStatus !== ''
            ? $allPersonnel->filter(fn (array $p): bool => strcasecmp($p['status_label'], $subStatus) === 0)->values()
            : $allPersonnel;

        $selectedPerson = $requestedNik !== ''
            ? $personnel->first(fn (array $p): bool => strcasecmp($p['nik'], $requestedNik) === 0)
            : null;

        if (! $selectedPerson && $personnel->isNotEmpty()) {
            $selectedPerson = $personnel->first();
        }

        $periodStart = Carbon::createFromDate($tahunInt, $bulanInt, 1, 'Asia/Jakarta');
        $startDate = $periodStart->toDateString();
        $endDate = $periodStart->copy()->endOfMonth()->toDateString();

        $dailyLogs = $selectedPerson
            ? $this->fetchAttendanceAndAttendantRecords(collect([$selectedPerson]), $startDate, $endDate, true)
            : collect();

        $namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $totals = [
            'hari_hadir' => $dailyLogs->filter(fn (array $log): bool => $log['has_scan'])->count(),
            'hadir_penuh' => $dailyLogs->filter(fn (array $log): bool => $log['scan1'] !== '-' && $log['scan4'] !== '-')->count(),
            'hadir_wd' => $dailyLogs->filter(fn (array $log): bool => $log['status_kehadiran'] === 'Hadir Penuh')->count(),
            'hadir_we' => $dailyLogs->filter(fn (array $log): bool => $log['status_kehadiran'] === 'Hadir (Hari Libur)')->count(),
            'izin_count' => $dailyLogs->filter(fn (array $log): bool => ! $log['has_table1_scan'] && $log['has_reason'])->count(),
            'terlambat_count' => $dailyLogs->filter(fn (array $log): bool => $log['is_late'])->count(),
        ];

        return [
            'category' => $category,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'bulan_label' => $namaBulan[$bulanInt] ?? $bulan,
            'period_label' => ($namaBulan[$bulanInt] ?? $bulan).' '.$tahun,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'sub_status' => $subStatus,
            'status_breakdown' => $statusBreakdown,
            'tahun_list' => $this->loadAvailableYears($tahunInt),
            'nama_bulan_list' => $namaBulan,
            'personnel' => $personnel,
            'selected_person' => $selectedPerson,
            'daily_logs' => $dailyLogs,
            'totals' => $totals,
        ];
    }

    /**
     * Ambil rincian absensi kalender harian penuh satu pegawai sesuai attendance dan attendant.
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

        $logs = $this->fetchAttendanceAndAttendantRecords(collect([$person]), $start, $end, true)->values();

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
     * Ambil dan susun rekap harian identik dengan attendance (table1 + tempdatascan)
     * dan attendant (table1 LEFT JOIN attendant LEFT JOIN reason).
     *
     * @param  Collection<int, array<string, mixed>>  $personnel
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchAttendanceAndAttendantRecords(
        Collection $personnel,
        string $startDate,
        string $endDate,
        bool $fullCalendar
    ): Collection {
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
                    ->orderBy('dtgl', 'asc')
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
                $scanUserIds = array_values(array_unique(array_filter(
                    array_merge(array_map('strval', $empIds), $niks),
                    fn (string $v): bool => trim($v) !== ''
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
                        if ($rawUser === '') {
                            continue;
                        }
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
            // Kembalikan koleksi kosong bila koneksi kepri_kepegawaian sedang tidak dapat diakses
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $holidays = $this->buildHolidaysForRange($startDate, $endDate);
        $tirtafieldMap = $this->loadTirtafieldPresensiMap($niks, $startDate, $endDate);

        if ($fullCalendar) {
            $keys = [];
            $calendarDates = $this->expandDateRange($startDate, $endDate);
            foreach ($personnel as $person) {
                $upperNik = strtoupper(trim((string) $person['nik']));
                foreach ($calendarDates as $dateKey) {
                    $keys[] = $upperNik.'|'.$dateKey;
                }
            }
        } else {
            $keys = array_values(array_unique(array_merge(
                array_keys($table1Map),
                array_keys($midScanMap),
                array_keys($attendantMap),
                array_keys($tirtafieldMap)
            )));
        }

        $records = collect();

        foreach ($keys as $key) {
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
            $holidayName = $holidays[$dateKey] ?? null;
            $isHoliday = $holidayName !== null;
            $isLibur = $isWeekend || $isHoliday;
            $isFuture = $dateKey > $today;

            $fingerScan1 = $t1['inpresent'] ?? '-';
            $fingerScan4 = $t1['outpresent'] ?? '-';
            $hasFingerScan = $fingerScan1 !== '-' || $fingerScan4 !== '-' || ! empty($rawMidScans);
            // Prioritas sumber: jika absen mesin finger ada (meskipun juga absen HP), selalu gunakan data mesin finger;
            // data HP (Tirtafield) hanya dipakai jika tidak ada absen mesin finger pada tanggal tersebut.
            $useTirtafield = ! $hasFingerScan && $tf !== null;

            if ($useTirtafield) {
                $scan1 = $tf['masuk'];
                $scan2 = $tf['istirahatKeluar'];
                $scan3 = $tf['istirahatMasuk'];
                $scan4 = $tf['pulang'];
                $isShift = false;
            } else {
                $scan1 = $fingerScan1;
                $scan4 = $fingerScan4;
                [$scan2, $scan3, $isShift] = $this->resolveMiddayScans($scan1, $scan4, $rawMidScans, $isWeekend);
            }

            $hasTable1Scan = ($scan1 !== '-' || $scan4 !== '-');
            $hasScan = ($hasTable1Scan || $scan2 !== '-' || $scan3 !== '-');
            $reasonText = $att['reason'] ?? '';
            $attNotes = $att['notes'] ?? '';
            $hasReason = ($att !== null && ($reasonText !== '' || $attNotes !== ''));

            if (! $fullCalendar && ! $hasScan && ! $hasReason && ($isLibur || $isFuture)) {
                continue;
            }

            $notes = [];
            $fpNotes = [];
            $isLate = false;
            $scan1Late = false;
            $scan2Early = false;
            $scan3Late = false;

            if ($hasTable1Scan) {
                if ($scan1 !== '-' && $scan4 !== '-') {
                    $statusKehadiran = $isLibur ? 'Hadir (Hari Libur)' : 'Hadir Penuh';
                } elseif ($scan1 !== '-') {
                    $statusKehadiran = 'Belum Pulang';
                } else {
                    $statusKehadiran = 'Pulang Saja';
                }
            } elseif ($hasReason && $reasonText !== '') {
                $statusKehadiran = $reasonText;
            } elseif ($hasScan) {
                $statusKehadiran = 'Scan Siang';
            } elseif ($isHoliday) {
                $statusKehadiran = $holidayName;
            } elseif ($isWeekend) {
                $statusKehadiran = 'Libur Akhir Pekan';
            } elseif ($isFuture) {
                $statusKehadiran = '-';
            } else {
                $statusKehadiran = '(-)';
            }

            if ($hasScan && in_array($hariIndo, ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'], true) && ! $isHoliday) {
                [$isLate, $fpNotes, $scan1Late, $scan2Early, $scan3Late] = $this->evaluateFingerprintPunctuality($scan1, $scan2, $scan3, $scan4, $hariIndo, $isShift);
                $notes = array_merge($notes, $fpNotes);
            }

            if ($useTirtafield) {
                if ($tf['is_late'] && ! $isLate && ! $isHoliday) {
                    $isLate = true;
                    $scan1Late = true;
                    $fpNotes[] = 'Telat Masuk Pagi';
                }
                $notes = array_merge($notes, $tf['notes']);
            }

            if ($hasReason) {
                if ($hasTable1Scan && $reasonText !== '') {
                    $notes[] = $reasonText.($attNotes !== '' ? ': '.$attNotes : '');
                } elseif ($attNotes !== '') {
                    $notes[] = $attNotes;
                } elseif (! $hasTable1Scan && $reasonText === '') {
                    $notes[] = 'Keterangan';
                }
            } elseif (! $hasScan && ($isHoliday || $isWeekend)) {
                $notes[] = $holidayName ?? 'Libur Akhir Pekan';
            }

            $sources = [];
            if ($hasFingerScan) {
                $sources[] = 'Attendance';
            }
            if ($hasReason) {
                $sources[] = 'Attendant';
            }
            if ($useTirtafield) {
                $sources[] = 'Tirtafield';
            }
            if (empty($sources)) {
                $sources[] = $t1 !== null ? 'Attendant' : 'Attendance';
            }

            $lokasi = $useTirtafield
                ? (! empty($tf['locations']) ? implode(', ', array_unique($tf['locations'])) : 'Tirtafield')
                : ($hasScan ? 'Mesin Fingerprint' : '-');

            $records->push([
                'nik' => $person['nik'],
                'name' => $person['name'],
                'status_label' => $person['status_label'],
                'department' => $person['department'],
                'tanggal' => $dateKey,
                'hari' => $hariIndo,
                'is_libur' => $isLibur,
                'jadwal_in' => $isLibur ? '-' : '08:00',
                'jadwal_out' => $isLibur ? '-' : '16:00',
                'scan1' => $scan1,
                'scan2' => $scan2,
                'scan3' => $scan3,
                'scan4' => $scan4,
                'scan1_late' => $scan1Late,
                'scan2_early' => $scan2Early,
                'scan3_late' => $scan3Late,
                'has_table1_scan' => $hasTable1Scan,
                'has_scan' => $hasScan,
                'has_reason' => $hasReason,
                'status_kehadiran' => $statusKehadiran,
                'is_late' => $isLate,
                'ketepatan' => $hasScan ? ($isLate ? 'Terlambat' : 'Tepat Waktu') : ($hasReason ? $statusKehadiran : '-'),
                'fp_notes' => implode(', ', array_values(array_unique($fpNotes))),
                'lokasi' => $lokasi,
                'catatan' => implode(' · ', array_values(array_unique(array_filter($notes)))),
                'sumber' => implode(' + ', $sources),
            ]);
        }

        if ($fullCalendar) {
            return $records->sort(function (array $a, array $b): int {
                return strcmp($a['tanggal'], $b['tanggal']);
            })->values();
        }

        return $records->sort(function (array $a, array $b): int {
            $cmpDate = strcmp($b['tanggal'], $a['tanggal']);
            if ($cmpDate !== 0) {
                return $cmpDate;
            }

            return strcasecmp($a['name'], $b['name']);
        })->values();
    }

    /**
     * Hitung jumlah personil unik yang memiliki scan masuk/pulang pada rentang tanggal.
     *
     * @param  Collection<int, array<string, mixed>>  $personnel
     */
    private function countPresentPersonnel(Collection $personnel, string $startDate, string $endDate): int
    {
        if ($personnel->isEmpty()) {
            return 0;
        }

        $presentNiks = [];
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
            // Tetap hitung dari Tirtafield bila koneksi kepegawaian tidak dapat dijangkau
        }

        $niks = $personnel->pluck('nik')->filter()->unique()->values()->all();
        if (! empty($niks) && Schema::hasTable('presensi')) {
            $tfRows = Presensi::query()
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->where(function ($query) use ($niks): void {
                    $query->whereIn('nik', $niks)->orWhereIn('pgw_nup', $niks);
                })
                ->where(function ($query): void {
                    $query->whereNull('approval_status')->orWhere('approval_status', '!=', 'rejected');
                })
                ->get(['nik', 'pgw_nup']);

            foreach ($tfRows as $tfRow) {
                $matchedNik = strtoupper(trim((string) ($tfRow->nik ?: $tfRow->pgw_nup)));
                if ($matchedNik !== '') {
                    $presentNiks[$matchedNik] = true;
                }
            }
        }

        return count($presentNiks);
    }

    /**
     * Ambil catatan presensi dari database tirtafield (tabel presensi) berdasarkan NIK/NUP.
     *
     * @param  list<string>  $niks
     * @return array<string, array<string, mixed>>
     */
    private function loadTirtafieldPresensiMap(array $niks, string $startDate, string $endDate): array
    {
        if (empty($niks) || ! Schema::hasTable('presensi')) {
            return [];
        }

        $rows = Presensi::query()
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->where(function ($query) use ($niks): void {
                $query->whereIn('nik', $niks)->orWhereIn('pgw_nup', $niks);
            })
            ->where(function ($query): void {
                $query->whereNull('approval_status')->orWhere('approval_status', '!=', 'rejected');
            })
            ->orderBy('tanggal', 'asc')
            ->orderBy('waktu', 'asc')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $dateKey = $row->tanggal ? $row->tanggal->format('Y-m-d') : '';
            $rawNik = trim((string) ($row->nik ?: $row->pgw_nup));
            if ($dateKey === '' || $rawNik === '') {
                continue;
            }

            $key = strtoupper($rawNik).'|'.$dateKey;
            if (! isset($map[$key])) {
                $map[$key] = [
                    'masuk' => '-',
                    'istirahatKeluar' => '-',
                    'istirahatMasuk' => '-',
                    'pulang' => '-',
                    'is_late' => false,
                    'notes' => [],
                    'locations' => [],
                ];
            }

            $slot = $row->normalizedSlot();
            $timeVal = $row->formattedWaktu();
            if ($timeVal !== '-') {
                $map[$key][$slot] = $timeVal;
            }

            if (in_array($slot, ['masuk', 'istirahatMasuk'], true) && $row->isLate()) {
                $map[$key]['is_late'] = true;
                $map[$key]['notes'][] = $row->status ?: 'Terlambat';
            }
            if (! empty($row->catatan)) {
                $map[$key]['notes'][] = trim((string) $row->catatan);
            }
            if (! empty($row->lokasi)) {
                $map[$key]['locations'][] = trim((string) $row->lokasi);
            }
        }

        return $map;
    }

    /**
     * Muat seluruh baris employee aktif dari kepri_kepegawaian (fallback ke koneksi default saat testing).
     *
     * @return Collection<int, stdClass|Employee>
     */
    private function loadEmployeesFromSource(): Collection
    {
        $kepegConn = $this->kepegawaianConnectionName();

        try {
            if (Schema::connection($kepegConn)->hasTable('employee')) {
                $rows = DB::connection($kepegConn)
                    ->table('employee')
                    ->where(function ($query): void {
                        $query->where('ishapus', 0)->orWhereNull('ishapus');
                    })
                    ->orderBy('pgw_nama', 'asc')
                    ->get();

                if ($rows->isNotEmpty()) {
                    return $rows;
                }
            }
        } catch (\Throwable) {
            // Fallback ke koneksi default bila koneksi kepegawaian sedang tidak dapat dijangkau
        }

        return Employee::query()
            ->where(function ($query): void {
                $query->where('ishapus', 0)->orWhereNull('ishapus');
            })
            ->orderBy('pgw_nama', 'asc')
            ->get();
    }

    /**
     * Klasifikasi kategori & status kepegawaian dari satu baris employee.
     *
     * @return array{category: string, status_label: string}|null
     */
    private function classifyEmployeeRecord(stdClass|Employee $employee): ?array
    {
        if ($employee instanceof Employee) {
            $category = $employee->recapCategory();
            if ($category === null) {
                return null;
            }

            return [
                'category' => $category,
                'status_label' => $employee->statusLabel(),
            ];
        }

        $rawStatus = $employee->pgw_sttskerja ?? null;
        if ($rawStatus === null) {
            return null;
        }

        $statusCode = (int) $rawStatus;
        $nup = strtoupper(trim((string) ($employee->pgw_nup ?? '')));
        $occId = isset($employee->occ_id) ? (int) $employee->occ_id : null;
        $deptId = isset($employee->dept_id) ? (int) $employee->dept_id : null;

        if ($statusCode === 1) {
            return ['category' => self::CATEGORY_PEGAWAI, 'status_label' => 'Pegawai Tetap'];
        }

        if ($statusCode === 3 && ($nup === 'D001' || $occId === 7 || $deptId === 1)) {
            return ['category' => self::CATEGORY_PEGAWAI, 'status_label' => 'Direksi'];
        }

        if ($statusCode === 2) {
            return ['category' => self::CATEGORY_NON_TETAP, 'status_label' => 'Capeg'];
        }

        if ($statusCode === 9) {
            return ['category' => self::CATEGORY_NON_TETAP, 'status_label' => 'PKWT'];
        }

        if ($statusCode === 3) {
            return [
                'category' => self::CATEGORY_NON_TETAP,
                'status_label' => str_starts_with($nup, 'H') ? 'Honor' : 'Kontrak',
            ];
        }

        if ($statusCode === 4) {
            return [
                'category' => self::CATEGORY_NON_TETAP,
                'status_label' => str_starts_with($nup, 'K') ? 'Kontrak' : 'Honor',
            ];
        }

        return null;
    }

    private function resolvePositionLabel(stdClass|Employee $employee, ?int $occId): string
    {
        if ($employee instanceof Employee) {
            return $employee->positionLabel();
        }

        $seksi = trim((string) ($employee->seksi_bidang ?? ''));
        if ($seksi !== '') {
            return $seksi;
        }

        $occAddDesc = trim((string) ($employee->occ_add_desc ?? ''));
        if ($occId !== null && isset(Employee::OCCUPATION_MAP[$occId])) {
            $base = Employee::OCCUPATION_MAP[$occId];

            return $occAddDesc !== '' ? $base.' - '.$occAddDesc : $base;
        }

        return $occAddDesc !== '' ? $occAddDesc : 'Staf';
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
     * Evaluasi keterlambatan scan fingerprint sesuai aturan attendance/index.php dan cetak.php.
     *
     * @return array{0: bool, 1: list<string>, 2: bool, 3: bool, 4: bool}
     */
    private function evaluateFingerprintPunctuality(string $scan1, string $scan2, string $scan3, string $scan4, string $hariIndo, bool $isShift): array
    {
        $isLate = false;
        $notes = [];
        $scan1Late = false;
        $scan2Early = false;
        $scan3Late = false;

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
                    $scan1Late = true;
                    $notes[] = 'Telat Masuk Shift Siang';
                }
            } elseif ($isShiftMalam) {
                if ($tScan1 > strtotime('22:00')) {
                    $isLate = true;
                    $scan1Late = true;
                    $notes[] = 'Telat Masuk Shift Malam';
                }
            } elseif ($tScan1 > strtotime('08:00')) {
                $isLate = true;
                $scan1Late = true;
                $notes[] = 'Telat Masuk Pagi';
            }
        }

        if (! $isShift) {
            if ($scan2 !== '-') {
                $limitKeluar = $hariIndo === 'Jumat' ? strtotime('11:30') : strtotime('12:00');
                if (strtotime($scan2) < $limitKeluar) {
                    $scan2Early = true;
                    $notes[] = 'Cepat Keluar Siang';
                }
            }

            if ($scan3 !== '-' && strtotime($scan3) > strtotime('13:00')) {
                $isLate = true;
                $scan3Late = true;
                $notes[] = 'Telat Masuk Siang';
            }
        }

        return [$isLate, $notes, $scan1Late, $scan2Early, $scan3Late];
    }

    /**
     * Ambil daftar tahun unik dari tempdatascan/table1 untuk dropdown pilihan tahun.
     *
     * @return list<int>
     */
    private function loadAvailableYears(int $selectedYear): array
    {
        $kepegConn = $this->kepegawaianConnectionName();
        $years = [$selectedYear, (int) Carbon::now('Asia/Jakarta')->year];

        try {
            if (Schema::connection($kepegConn)->hasTable('table1')) {
                $rows = DB::connection($kepegConn)
                    ->table('table1')
                    ->selectRaw('DISTINCT SUBSTR(dtgl, 1, 4) as y')
                    ->pluck('y');

                foreach ($rows as $row) {
                    $yearInt = (int) $row;
                    if ($yearInt >= 2000 && $yearInt <= 2100) {
                        $years[] = $yearInt;
                    }
                }
            }
        } catch (\Throwable) {
            // Gunakan tahun bawaan bila query daftar tahun tidak dapat dijalankan
        }

        $years = array_values(array_unique($years));
        rsort($years);

        return $years;
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

    /**
     * Daftar hari libur nasional sesuai kalender pada attendance/cetak.php & attendant/cetak.php.
     *
     * @return array<string, string>
     */
    private function buildHolidaysForRange(string $startDate, string $endDate): array
    {
        $startYear = (int) substr($startDate, 0, 4);
        $endYear = (int) substr($endDate, 0, 4);
        $holidays = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            if ($year === 2026) {
                $holidays['2026-01-01'] = 'Tahun Baru Masehi';
                $holidays['2026-05-01'] = 'Hari Buruh Internasional';
                $holidays['2026-05-14'] = 'Kenaikan Yesus Kristus';
                $holidays['2026-05-15'] = 'Cuti Bersama Kenaikan Yesus';
                $holidays['2026-05-27'] = 'Hari Raya Idul Adha';
                $holidays['2026-05-28'] = 'Cuti Bersama Idul Adha';
                $holidays['2026-05-31'] = 'Hari Raya Waisak';
                $holidays['2026-06-01'] = 'Hari Lahir Pancasila';
                $holidays['2026-08-17'] = 'Hari Kemerdekaan RI';
                $holidays['2026-08-25'] = 'Hari Maulid Nabi';
                $holidays['2026-12-25'] = 'Hari Raya Natal';
            } else {
                $holidays[$year.'-01-01'] = 'Tahun Baru Masehi';
                $holidays[$year.'-05-01'] = 'Hari Buruh Internasional';
                $holidays[$year.'-06-01'] = 'Hari Lahir Pancasila';
                $holidays[$year.'-08-17'] = 'Hari Kemerdekaan RI';
                $holidays[$year.'-12-25'] = 'Hari Raya Natal';
            }
        }

        return $holidays;
    }

    /**
     * @return list<string>
     */
    private function expandDateRange(string $startDate, string $endDate): array
    {
        $dates = [];
        $cursor = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
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
