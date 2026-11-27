<?php

namespace App\Http\Controllers;

use App\Services\AttendanceRecapService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecapController extends Controller
{
    public function __construct(
        private readonly AttendanceRecapService $recapService,
    ) {}

    public function pegawai(Request $request): View
    {
        return $this->renderCategory($request, AttendanceRecapService::CATEGORY_PEGAWAI);
    }

    public function nonTetap(Request $request): View
    {
        return $this->renderCategory($request, AttendanceRecapService::CATEGORY_NON_TETAP);
    }

    public function cetak(Request $request, string $category): View
    {
        $resolvedCategory = $this->normalizeCategory($category);
        $recap = $this->recapService->buildMonthlyRecap($resolvedCategory, $request->query());
        $isPegawai = $resolvedCategory === AttendanceRecapService::CATEGORY_PEGAWAI;

        return view('recap.cetak', [
            'category' => $resolvedCategory,
            'pageTitle' => $isPegawai
                ? 'Laporan Absensi Pegawai'
                : 'Laporan Absensi Capeg, Honor, PKWT, Kontrak',
            'recap' => $recap,
        ]);
    }

    public function detail(Request $request, string $category, string $nik): JsonResponse
    {
        $resolvedCategory = $this->normalizeCategory($category);

        try {
            $detail = $this->recapService->getEmployeeDetail(
                $resolvedCategory,
                $nik,
                $request->query('start_date'),
                $request->query('end_date')
            );
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca rincian presensi dari database.',
            ], 500);
        }

        if (! $detail) {
            return response()->json([
                'success' => false,
                'message' => 'Data personil tidak ditemukan pada kategori ini.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }

    public function exportCsv(Request $request, string $category): StreamedResponse
    {
        $resolvedCategory = $this->normalizeCategory($category);

        if ($request->filled('nik') || ($request->filled('bulan') && $request->filled('tahun'))) {
            $recap = $this->recapService->buildMonthlyRecap($resolvedCategory, $request->query());
            $person = $recap['selected_person'];
            $safeNik = preg_replace('/[^A-Za-z0-9\-]/', '_', (string) ($person['nik'] ?? 'pegawai'));
            $filename = sprintf('absensi-%s-%s-%s.csv', $safeNik, $recap['bulan'], $recap['tahun']);

            return response()->streamDownload(function () use ($recap, $person): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }

                fwrite($handle, "\xEF\xBB\xBF");

                if ($person) {
                    fputcsv($handle, ['NIK', $person['nik']]);
                    fputcsv($handle, ['Nama Pegawai', $person['name']]);
                    fputcsv($handle, ['Status', $person['status_label']]);
                    fputcsv($handle, ['Periode', $recap['period_label']]);
                    fputcsv($handle, []);
                }

                fputcsv($handle, [
                    'Tanggal',
                    'Hari',
                    'Jadwal Masuk',
                    'Jadwal Pulang',
                    'Scan Pagi (Masuk)',
                    'Scan Siang (Keluar)',
                    'Scan Siang (Masuk)',
                    'Scan Sore (Pulang)',
                    'Status',
                    'Keterangan',
                ]);

                foreach ($recap['daily_logs'] as $log) {
                    fputcsv($handle, [
                        Carbon::parse($log['tanggal'])->format('d/m/Y'),
                        $log['hari'],
                        $log['jadwal_in'],
                        $log['jadwal_out'],
                        $log['scan1'],
                        $log['scan2'],
                        $log['scan3'],
                        $log['scan4'],
                        $log['status_kehadiran'],
                        $log['catatan'] !== '' ? $log['catatan'] : '-',
                    ]);
                }

                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $recap = $this->recapService->buildRecap($resolvedCategory, $request->query());

        $slug = $resolvedCategory === AttendanceRecapService::CATEGORY_PEGAWAI
            ? 'rekap-absensi-pegawai'
            : 'rekap-absensi-capeg-honor-pkwt-kontrak';

        $filename = sprintf(
            '%s-%s-sd-%s.csv',
            $slug,
            $recap['filters']['start_date'],
            $recap['filters']['end_date']
        );

        return response()->streamDownload(function () use ($recap): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'No',
                'NIK / NUP',
                'Nama Lengkap',
                'Status Kepegawaian',
                'Bidang / Unit',
                'Jabatan',
                'Penempatan',
                'Hari Hadir',
                'Hadir Penuh',
                'Hadir Hari Libur',
                'Belum Pulang',
                'Pulang Saja',
                'Izin / Cuti / Dinas',
                'Tanpa Keterangan (-)',
                'Scan Masuk',
                'Siang Keluar',
                'Siang Masuk',
                'Scan Pulang',
                'Tepat Waktu',
                'Terlambat',
                'Total Log',
            ]);

            foreach ($recap['rows'] as $index => $row) {
                fputcsv($handle, [
                    $index + 1,
                    $row['nik'],
                    $row['name'],
                    $row['status_label'],
                    $row['department'],
                    $row['position'],
                    $row['office_name'],
                    $row['hari_hadir'],
                    $row['hadir_penuh_count'],
                    $row['hadir_we_count'],
                    $row['belum_pulang_count'],
                    $row['pulang_saja_count'],
                    $row['izin_count'],
                    $row['alpa_count'],
                    $row['masuk_count'],
                    $row['istirahat_keluar_count'],
                    $row['istirahat_masuk_count'],
                    $row['pulang_count'],
                    $row['tepat_waktu_count'],
                    $row['terlambat_count'],
                    $row['total_log'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function renderCategory(Request $request, string $category): View
    {
        $recap = null;
        $dbError = null;

        try {
            $recap = $this->recapService->buildMonthlyRecap($category, $request->query());
        } catch (\Throwable) {
            $dbError = 'Terjadi kendala saat mengambil data rekap absensi.';
        }

        $isPegawai = $category === AttendanceRecapService::CATEGORY_PEGAWAI;

        return view('recap.index', [
            'category' => $category,
            'pageTitle' => $isPegawai
                ? 'Rekap Absensi Pegawai'
                : 'Rekap Absensi Capeg, Honor, PKWT, Kontrak',
            'recap' => $recap,
            'dbError' => $dbError,
        ]);
    }

    private function normalizeCategory(string $category): string
    {
        return match ($category) {
            AttendanceRecapService::CATEGORY_NON_TETAP, 'capeg-honor-pkwt-kontrak' => AttendanceRecapService::CATEGORY_NON_TETAP,
            default => AttendanceRecapService::CATEGORY_PEGAWAI,
        };
    }
}
