<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Office;
use App\Models\Presensi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceRecapService
{
    public const CATEGORY_PEGAWAI = 'pegawai';

    public const CATEGORY_NON_TETAP = 'non_tetap';

    /**
     * @var list<string>
     */
    public const NON_TETAP_STATUSES = ['Capeg', 'Honor', 'PKWT', 'Kontrak'];

    /**
     * Hitung ringkasan jumlah personil dan presensi bulan berjalan untuk halaman utama.
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

        $pegawaiNiks = $pegawai->pluck('nik')->all();
        $nonTetapNiks = $nonTetap->pluck('nik')->all();

        $pegawaiPresensiCount = empty($pegawaiNiks)
            ? 0
            : Presensi::query()
                ->whereIn('nik', $pegawaiNiks)
                ->whereBetween('tanggal', [$periodStart, $periodEnd])
                ->distinct('nik')
                ->count('nik');

        $nonTetapPresensiCount = empty($nonTetapNiks)
            ? 0
            : Presensi::query()
                ->whereIn('nik', $nonTetapNiks)
                ->whereBetween('tanggal', [$periodStart, $periodEnd])
                ->distinct('nik')
                ->count('nik');

        return [
            'pegawai_count' => $pegawai->count(),
            'non_tetap_count' => $nonTetap->count(),
            'non_tetap_breakdown' => $breakdown,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'pegawai_presensi_count' => $pegawaiPresensiCount,
            'non_tetap_presensi_count' => $nonTetapPresensiCount,
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
     * Bangun data rekap lengkap untuk halaman rekap dan ekspor CSV.
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

        $nikList = $filteredPersonnel->pluck('nik')->all();

        $presensiRecords = empty($nikList)
            ? collect()
            : Presensi::query()
                ->whereIn('nik', $nikList)
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->orderBy('tanggal', 'desc')
                ->orderBy('waktu', 'desc')
                ->get();

        $presensiByNik = $presensiRecords->groupBy(fn (Presensi $item): string => strtoupper(trim((string) $item->nik)));
        $personnelMap = $filteredPersonnel->keyBy(fn (array $person): string => strtoupper(trim((string) $person['nik'])));

        $rows = $filteredPersonnel->map(function (array $person) use ($presensiByNik): array {
            $upperNik = strtoupper(trim((string) $person['nik']));
            /** @var Collection<int, Presensi> $logs */
            $logs = $presensiByNik->get($upperNik, collect());

            $distinctDates = $logs
                ->map(fn (Presensi $p): string => $p->tanggal ? $p->tanggal->format('Y-m-d') : '')
                ->filter()
                ->unique();

            $masukLogs = $logs->filter(fn (Presensi $p): bool => $p->normalizedSlot() === 'masuk');
            $tepatWaktuCount = $masukLogs->filter(fn (Presensi $p): bool => ! $p->isLate())->count();
            $terlambatCount = $masukLogs->filter(fn (Presensi $p): bool => $p->isLate())->count();

            $approvedCount = $logs->filter(fn (Presensi $p): bool => strtolower((string) $p->approval_status) === 'approved')->count();
            $pendingCount = $logs->filter(fn (Presensi $p): bool => strtolower((string) $p->approval_status) === 'pending' || empty($p->approval_status))->count();
            $rejectedCount = $logs->filter(fn (Presensi $p): bool => strtolower((string) $p->approval_status) === 'rejected')->count();

            $latestLog = $logs->first();
            $latestMasuk = $masukLogs->first();

            return array_merge($person, [
                'hari_hadir' => $distinctDates->count(),
                'total_log' => $logs->count(),
                'masuk_count' => $masukLogs->count(),
                'istirahat_keluar_count' => $logs->filter(fn (Presensi $p): bool => $p->normalizedSlot() === 'istirahatKeluar')->count(),
                'istirahat_masuk_count' => $logs->filter(fn (Presensi $p): bool => $p->normalizedSlot() === 'istirahatMasuk')->count(),
                'pulang_count' => $logs->filter(fn (Presensi $p): bool => $p->normalizedSlot() === 'pulang')->count(),
                'tepat_waktu_count' => $tepatWaktuCount,
                'terlambat_count' => $terlambatCount,
                'approved_count' => $approvedCount,
                'pending_count' => $pendingCount,
                'rejected_count' => $rejectedCount,
                'last_punch_date' => $latestLog?->tanggal?->format('Y-m-d'),
                'last_punch_time' => $latestLog?->formattedWaktu(),
                'last_masuk_time' => $latestMasuk?->formattedWaktu(),
                'last_status' => $latestMasuk?->status ?? $latestLog?->status,
            ]);
        });

        if ($attendanceFilter === 'present') {
            $rows = $rows->filter(fn (array $row): bool => $row['total_log'] > 0)->values();
        } elseif ($attendanceFilter === 'absent') {
            $rows = $rows->filter(fn (array $row): bool => $row['total_log'] === 0)->values();
        } elseif ($attendanceFilter === 'late') {
            $rows = $rows->filter(fn (array $row): bool => $row['terlambat_count'] > 0)->values();
        }

        $visibleNiks = $rows->pluck('nik')->map(fn (string $n): string => strtoupper($n))->all();
        $dailyLogs = $presensiRecords
            ->filter(fn (Presensi $p): bool => in_array(strtoupper(trim((string) $p->nik)), $visibleNiks, true))
            ->map(function (Presensi $p) use ($personnelMap): array {
                $person = $personnelMap->get(strtoupper(trim((string) $p->nik)));

                return [
                    'id' => $p->id,
                    'nik' => $p->nik,
                    'name' => $person['name'] ?? $p->pgw_nama ?? '-',
                    'status_label' => $person['status_label'] ?? '-',
                    'department' => $person['department'] ?? '-',
                    'tanggal' => $p->tanggal?->format('Y-m-d') ?? '-',
                    'slot' => $p->slotLabel(),
                    'slot_key' => $p->normalizedSlot(),
                    'waktu' => $p->formattedWaktu(),
                    'status' => $p->status ?: 'Tepat Waktu',
                    'is_late' => $p->isLate(),
                    'approval_status' => strtolower((string) ($p->approval_status ?: 'pending')),
                    'approval_label' => $p->approvalLabel(),
                    'radius_label' => $p->radiusLabel(),
                    'lokasi' => $p->lokasi ?: '-',
                    'catatan' => $p->catatan ?: '',
                    'reviewed_by' => $p->reviewed_by ?: '',
                ];
            })
            ->values();

        $summary = [
            'total_personil' => $rows->count(),
            'personil_hadir' => $rows->filter(fn (array $row): bool => $row['total_log'] > 0)->count(),
            'total_hari_hadir' => $rows->sum('hari_hadir'),
            'total_tepat_waktu' => $rows->sum('tepat_waktu_count'),
            'total_terlambat' => $rows->sum('terlambat_count'),
            'total_approved' => $rows->sum('approved_count'),
            'total_pending' => $rows->sum('pending_count'),
            'total_rejected' => $rows->sum('rejected_count'),
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
     * Ambil rincian log presensi untuk satu pegawai pada modal detail.
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

        $logs = Presensi::query()
            ->where('nik', $person['nik'])
            ->whereBetween('tanggal', [$start, $end])
            ->orderBy('tanggal', 'desc')
            ->orderBy('waktu', 'asc')
            ->get()
            ->map(fn (Presensi $p): array => [
                'id' => $p->id,
                'tanggal' => $p->tanggal?->format('Y-m-d') ?? '-',
                'slot' => $p->slotLabel(),
                'slot_key' => $p->normalizedSlot(),
                'waktu' => $p->formattedWaktu(),
                'status' => $p->status ?: 'Tepat Waktu',
                'is_late' => $p->isLate(),
                'approval_status' => strtolower((string) ($p->approval_status ?: 'pending')),
                'approval_label' => $p->approvalLabel(),
                'radius_label' => $p->radiusLabel(),
                'lokasi' => $p->lokasi ?: '-',
                'catatan' => $p->catatan ?: '',
                'reviewed_by' => $p->reviewed_by ?: '',
            ])
            ->values();

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
