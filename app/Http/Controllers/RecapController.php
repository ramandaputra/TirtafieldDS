<?php

namespace App\Http\Controllers;

use App\Services\AttendanceRecapService;
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

            // BOM UTF-8 agar Microsoft Excel membaca karakter Indonesia dengan benar
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
                'Presensi Masuk',
                'Istirahat Keluar',
                'Istirahat Masuk',
                'Presensi Pulang',
                'Tepat Waktu',
                'Terlambat',
                'Disetujui',
                'Menunggu',
                'Ditolak',
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
                    $row['masuk_count'],
                    $row['istirahat_keluar_count'],
                    $row['istirahat_masuk_count'],
                    $row['pulang_count'],
                    $row['tepat_waktu_count'],
                    $row['terlambat_count'],
                    $row['approved_count'],
                    $row['pending_count'],
                    $row['rejected_count'],
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
            $recap = $this->recapService->buildRecap($category, $request->query());
        } catch (\Throwable) {
            $dbError = 'Terjadi kendala saat mengambil data rekap absensi dari database Tirtafield.';
        }

        $isPegawai = $category === AttendanceRecapService::CATEGORY_PEGAWAI;

        return view('recap.index', [
            'category' => $category,
            'pageTitle' => $isPegawai
                ? 'Rekap Absensi Pegawai'
                : 'Rekap Absensi Capeg, Honor, PKWT, Kontrak',
            'pageSubtitle' => $isPegawai
                ? 'Rekapitulasi kehadiran pegawai tetap dan direksi dari database Tirtafield.'
                : 'Rekapitulasi kehadiran calon pegawai (Capeg), tenaga Honor, PKWT, dan Kontrak.',
            'subStatusOptions' => $isPegawai ? [] : AttendanceRecapService::NON_TETAP_STATUSES,
            'recap' => $recap,
            'dbError' => $dbError,
        ]);
    }

    private function normalizeCategory(string $category): string
    {
        return match ($category) {
            AttendanceRecapService::CATEGORY_PEGAWAI => AttendanceRecapService::CATEGORY_PEGAWAI,
            AttendanceRecapService::CATEGORY_NON_TETAP, 'non-tetap' => AttendanceRecapService::CATEGORY_NON_TETAP,
            default => abort(404),
        };
    }
}
