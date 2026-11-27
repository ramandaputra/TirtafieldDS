<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak {{ $pageTitle }} | {{ $recap['selected_person']['name'] ?? 'Tirta Kepri' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #e2e8f0;
            color: #111827;
            margin: 0;
            padding: 16px;
            font-size: 10pt;
            line-height: 1.35;
        }
        .no-print-bar {
            max-width: 210mm;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            background: #0f172a;
            color: #f1f5f9;
            padding: 10px 16px;
            border-radius: 12px;
        }
        .no-print-bar .actions {
            display: flex;
            gap: 8px;
        }
        .btn-toolbar {
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
            color: #f1f5f9;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-toolbar-primary {
            background: #38bdf8;
            color: #070c16;
            border-color: #38bdf8;
        }
        .sheet {
            max-width: 210mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 14mm 12mm;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.16);
            position: relative;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 260px;
            opacity: 0.08;
            pointer-events: none;
            z-index: 0;
        }
        .sheet-content {
            position: relative;
            z-index: 1;
        }
        .doc-title {
            text-align: center;
            margin: 0 0 14px;
            font-size: 14pt;
            font-weight: 700;
            color: #0f172a;
        }
        .doc-subtitle {
            text-align: center;
            margin: -10px 0 16px;
            font-size: 9.5pt;
            color: #475569;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9.5pt;
        }
        .info-table td {
            padding: 2px 4px;
            border: none;
            vertical-align: top;
        }
        .info-label {
            width: 110px;
            font-weight: 700;
        }
        .info-sep {
            width: 12px;
        }
        .absensi-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.8pt;
        }
        .absensi-table th,
        .absensi-table td {
            border: 1px solid #1e293b;
            padding: 5px 6px;
            text-align: center;
            vertical-align: middle;
        }
        .absensi-table th {
            background-color: #e6e6e6;
            font-weight: 700;
            color: #0f172a;
        }
        .row-libur td {
            color: #cc0000;
            background-color: #fffafa;
        }
        .scan-ok {
            color: #15803d;
            font-weight: 700;
        }
        .scan-pulang {
            color: #1d4ed8;
            font-weight: 700;
        }
        .scan-danger {
            color: #dc2626;
            font-weight: 700;
        }
        .scan-muted {
            color: #6b7280;
        }
        .ket-text {
            font-size: 8.2pt;
            font-weight: 700;
        }
        .ket-danger {
            color: #dc2626;
            font-style: italic;
        }
        .footer-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 8.5pt;
        }
        .footer-grid td {
            border: none;
            padding: 2px 0;
            vertical-align: bottom;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .sheet {
                max-width: 100%;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .absensi-table th {
                background-color: #e6e6e6 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    @php
        $person = $recap['selected_person'] ?? null;
        $dailyLogs = $recap['daily_logs'] ?? collect();
        $printedDate = \Carbon\Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('d F Y');
    @endphp

    <div class="no-print-bar">
        <div style="font-size: 13px; font-weight: 600;">
            Pratinjau Cetak · {{ $person['name'] ?? '-' }} ({{ $recap['period_label'] ?? '' }})
        </div>
        <div class="actions">
            <a href="{{ route($category === \App\Services\AttendanceRecapService::CATEGORY_PEGAWAI ? 'rekap.pegawai' : 'rekap.non-tetap', ['nik' => $person['nik'] ?? '', 'bulan' => $recap['bulan'], 'tahun' => $recap['tahun'], 'sub_status' => $recap['sub_status'] ?? '']) }}"
               class="btn-toolbar">
                Kembali
            </a>
            <button type="button" onclick="window.print()" class="btn-toolbar btn-toolbar-primary">
                Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <div class="sheet">
        @if (file_exists(public_path('favicon.png')))
            <img src="{{ asset('favicon.png') }}" alt="" class="watermark" aria-hidden="true">
        @endif

        <div class="sheet-content">
            <h1 class="doc-title">
                {{ $category === \App\Services\AttendanceRecapService::CATEGORY_PEGAWAI ? 'Laporan Absensi Pegawai' : 'Laporan Absensi Capeg, Honor, PKWT & Kontrak' }}
            </h1>
            <p class="doc-subtitle">PERUMDA Air Minum Tirta Kepri</p>

            @if ($person)
                <table class="info-table">
                    <tr>
                        <td class="info-label">NIK</td>
                        <td class="info-sep">:</td>
                        <td>{{ $person['nik'] }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Nama Pegawai</td>
                        <td class="info-sep">:</td>
                        <td>{{ $person['name'] }} ({{ $person['status_label'] }})</td>
                    </tr>
                    <tr>
                        <td class="info-label">Periode</td>
                        <td class="info-sep">:</td>
                        <td>{{ $recap['period_label'] }}</td>
                    </tr>
                </table>

                <table class="absensi-table">
                    <thead>
                        <tr>
                            <th style="width: 11%;">Tanggal</th>
                            <th style="width: 9%;">Hari</th>
                            <th style="width: 11%;">Jadwal</th>
                            <th style="width: 11%;">Scan Pagi<br>(Masuk)</th>
                            <th style="width: 11%;">Scan Siang<br>(Keluar)</th>
                            <th style="width: 11%;">Scan Siang<br>(Masuk)</th>
                            <th style="width: 11%;">Scan Sore<br>(Pulang)</th>
                            <th style="width: 25%;">Status / Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dailyLogs as $log)
                            @php
                                $s1Class = $log['scan1'] === '-' ? 'scan-muted' : (! empty($log['scan1_late']) ? 'scan-danger' : 'scan-ok');
                                $s2Class = $log['scan2'] === '-' ? 'scan-muted' : (! empty($log['scan2_early']) ? 'scan-danger' : 'scan-ok');
                                $s3Class = $log['scan3'] === '-' ? 'scan-muted' : (! empty($log['scan3_late']) ? 'scan-danger' : 'scan-ok');
                                $s4Class = $log['scan4'] === '-' ? 'scan-muted' : 'scan-pulang';
                                $jadwal = $log['jadwal_in'] !== '-' ? ($log['jadwal_in'].' - '.$log['jadwal_out']) : '-';
                            @endphp
                            <tr class="{{ $log['is_libur'] ? 'row-libur' : '' }}">
                                <td>{{ \Carbon\Carbon::parse($log['tanggal'])->format('d/m/Y') }}</td>
                                <td><strong>{{ $log['hari'] }}</strong></td>
                                <td>{{ $jadwal }}</td>
                                <td><span class="{{ $s1Class }}">{{ $log['scan1'] }}</span></td>
                                <td><span class="{{ $s2Class }}">{{ $log['scan2'] }}</span></td>
                                <td><span class="{{ $s3Class }}">{{ $log['scan3'] }}</span></td>
                                <td><span class="{{ $s4Class }}">{{ $log['scan4'] }}</span></td>
                                <td>
                                    <div class="ket-text">{{ $log['status_kehadiran'] }}</div>
                                    @if ($log['catatan'] !== '' && $log['catatan'] !== $log['status_kehadiran'])
                                        <div class="ket-text {{ ($log['is_late'] || ! empty($log['scan2_early'])) ? 'ket-danger' : '' }}">
                                            {{ $log['catatan'] }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">Belum ada data pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <table class="footer-grid">
                    <tr>
                        <td style="width: 55%;">
                            <strong>Keterangan Warna:</strong><br>
                            <span style="color: #15803d; font-weight: 700;">Hijau</span> : Tepat Waktu<br>
                            <span style="color: #dc2626; font-weight: 700;">Merah</span> : Terlambat / Pulang Kecepatan / Hari Libur
                        </td>
                        <td style="width: 45%; text-align: right;">
                            <em>Dicetak oleh Pusimpel Tirta Kepri · {{ $printedDate }}</em>
                        </td>
                    </tr>
                </table>
            @else
                <p>Data pegawai tidak ditemukan.</p>
            @endif
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
