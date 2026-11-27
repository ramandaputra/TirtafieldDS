<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak {{ $pageTitle }} | {{ $recap['selected_person']['name'] ?? 'Tirta Kepri' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('build/favicon.png') }}">
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 10mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #e2e8f0;
            color: #111827;
            margin: 0;
            padding: 14px;
            font-size: 9pt;
            line-height: 1.2;
        }
        .no-print-bar {
            max-width: 210mm;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            background: #0f172a;
            color: #f1f5f9;
            padding: 9px 14px;
            border-radius: 12px;
        }
        .no-print-bar .actions {
            display: flex;
            gap: 8px;
        }
        .btn-toolbar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 36px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
            color: #f1f5f9;
            padding: 0 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }
        .btn-toolbar:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.35);
        }
        .btn-toolbar-primary {
            background: #38bdf8;
            color: #070c16;
            border-color: #38bdf8;
        }
        .btn-toolbar-primary:hover {
            background: #7dd3fc;
            border-color: #7dd3fc;
        }
        .sheet {
            max-width: 210mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 8mm 10mm;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.16);
            position: relative;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 230px;
            opacity: 0.07;
            pointer-events: none;
            z-index: 0;
        }
        .sheet-content {
            position: relative;
            z-index: 1;
        }
        .doc-header {
            text-align: center;
            margin-bottom: 8px;
        }
        .doc-title {
            margin: 0;
            font-size: 12pt;
            font-weight: 700;
            color: #0f172a;
        }
        .doc-subtitle {
            margin: 2px 0 0;
            font-size: 8.5pt;
            color: #475569;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8.5pt;
        }
        .info-table td {
            padding: 1.5px 4px;
            border: none;
            vertical-align: middle;
        }
        .info-label {
            width: 80px;
            font-weight: 700;
        }
        .info-sep {
            width: 10px;
        }
        .absensi-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            table-layout: fixed;
        }
        .absensi-table tr {
            page-break-inside: avoid;
        }
        .absensi-table th,
        .absensi-table td {
            border: 1px solid #1e293b;
            padding: 2.5px 4px;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }
        .absensi-table th {
            background-color: #e6e6e6;
            font-weight: 700;
            color: #0f172a;
            padding: 4px;
            font-size: 7.8pt;
            line-height: 1.15;
        }
        .absensi-table td.col-ket {
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 7.6pt;
            font-weight: 700;
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
        .ket-danger {
            color: #dc2626;
        }
        .ket-muted {
            color: #475569;
            font-weight: 600;
        }
        .footer-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 7.5pt;
        }
        .footer-grid td {
            border: none;
            padding: 1px 0;
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
            <a href="{{ route($category === \App\Services\AttendanceRecapService::CATEGORY_PEGAWAI ? 'rekap.pegawai' : 'rekap.non-tetap', ['nik' => $person['nik'] ?? '', 'bulan' => $recap['bulan'], 'tahun' => $recap['tahun']]) }}"
               class="btn-toolbar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 19 5 12 12 5" />
                </svg>
                <span>Kembali</span>
            </a>
            <button type="button" onclick="window.print()" class="btn-toolbar btn-toolbar-primary">
                Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <div class="sheet">
        @if (file_exists(public_path('build/favicon.png')))
            <img src="{{ asset('build/favicon.png') }}" alt="" class="watermark" aria-hidden="true">
        @endif

        <div class="sheet-content">
            <div class="doc-header">
                <h1 class="doc-title">LAPORAN ABSENSI</h1>
            </div>

            @if ($person)
                <table class="info-table">
                    <tr>
                        <td class="info-label">NIK</td>
                        <td class="info-sep">:</td>
                        <td style="width: 45%;">{{ $person['nik'] }}</td>
                        <td class="info-label">Periode</td>
                        <td class="info-sep">:</td>
                        <td>{{ $recap['period_label'] }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Nama</td>
                        <td class="info-sep">:</td>
                        <td colspan="4">{{ $person['name'] }} ({{ $person['status_label'] }})</td>
                    </tr>
                </table>

                <table class="absensi-table">
                    <thead>
                        <tr>
                            <th style="width: 11%;">Tanggal</th>
                            <th style="width: 9%;">Hari</th>
                            <th style="width: 12%;">Jadwal</th>
                            <th style="width: 10%;">Masuk</th>
                            <th style="width: 11%;">Keluar Siang</th>
                            <th style="width: 11%;">Masuk Siang</th>
                            <th style="width: 10%;">Pulang</th>
                            <th style="width: 26%;">Status / Keterangan</th>
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

                                $fpNote = trim((string) ($log['fp_notes'] ?? ''));
                                $isDangerKet = $fpNote !== '' || $log['status_kehadiran'] === '(-)' || $log['is_libur'];
                                if ($fpNote !== '') {
                                    $ketSingleLine = $fpNote;
                                } else {
                                    $ketSingleLine = $log['status_kehadiran'];
                                }
                            @endphp
                            <tr class="{{ $log['is_libur'] ? 'row-libur' : '' }}">
                                <td>{{ \Carbon\Carbon::parse($log['tanggal'])->format('d/m/Y') }}</td>
                                <td><strong>{{ $log['hari'] }}</strong></td>
                                <td>{{ $jadwal }}</td>
                                <td><span class="{{ $s1Class }}">{{ $log['scan1'] }}</span></td>
                                <td><span class="{{ $s2Class }}">{{ $log['scan2'] }}</span></td>
                                <td><span class="{{ $s3Class }}">{{ $log['scan3'] }}</span></td>
                                <td><span class="{{ $s4Class }}">{{ $log['scan4'] }}</span></td>
                                <td class="col-ket {{ $isDangerKet ? 'ket-danger' : ($log['has_scan'] ? '' : 'ket-muted') }}">
                                    {{ $ketSingleLine }}
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
                        <td style="width: 60%;">
                            <strong>Keterangan:</strong>
                            <span style="color: #15803d; font-weight: 700;">Hijau</span> = Tepat Waktu,
                            <span style="color: #dc2626; font-weight: 700;">Merah</span> = Terlambat / Pulang Cepat / Libur
                        </td>
                        <td style="width: 40%; text-align: right;">
                            <em>Pusimpel Tirta Kepri · {{ $printedDate }}</em>
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
