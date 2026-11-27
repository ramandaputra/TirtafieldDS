<?php

namespace App\Http\Controllers;

use App\Services\AttendanceRecapService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AttendanceRecapService $recapService,
    ) {}

    public function index(): View
    {
        $summary = null;
        $dbError = null;

        try {
            $summary = $this->recapService->getDashboardSummary();
        } catch (\Throwable $e) {
            $dbError = 'Tidak dapat memuat ringkasan dari database Tirtafield saat ini.';
        }

        return view('dashboard.index', [
            'summary' => $summary,
            'dbError' => $dbError,
        ]);
    }
}
