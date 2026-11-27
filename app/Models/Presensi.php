<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presensi extends Model
{
    protected $table = 'presensi';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pgw_id',
        'nik',
        'pgw_nup',
        'pgw_nama',
        'tanggal',
        'slot_type',
        'waktu',
        'lokasi',
        'latitude',
        'longitude',
        'alamat',
        'jarak_hq',
        'foto_url',
        'biometric_score',
        'catatan',
        'status',
        'approval_status',
        'tipe_pengajuan',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'latitude' => 'float',
            'longitude' => 'float',
            'biometric_score' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nik', 'nik');
    }

    public function normalizedSlot(): string
    {
        $raw = strtolower(str_replace(['_', ' ', '-'], '', (string) $this->slot_type));

        return match ($raw) {
            'masuk' => 'masuk',
            'istirahatkeluar' => 'istirahatKeluar',
            'istirahatmasuk' => 'istirahatMasuk',
            'pulang' => 'pulang',
            default => 'masuk',
        };
    }

    public function slotLabel(): string
    {
        return match ($this->normalizedSlot()) {
            'istirahatKeluar' => 'Istirahat Keluar',
            'istirahatMasuk' => 'Istirahat Masuk',
            'pulang' => 'Pulang',
            default => 'Masuk',
        };
    }

    public function formattedWaktu(): string
    {
        $raw = trim((string) $this->waktu);
        if ($raw === '') {
            return '-';
        }

        $clean = preg_replace('/\s*WIB\s*$/i', '', $raw) ?? $raw;

        try {
            return Carbon::parse($clean)->format('H:i');
        } catch (\Throwable) {
            if (preg_match('/^\d{2}:\d{2}/', $clean, $matches)) {
                return $matches[0];
            }

            return $clean;
        }
    }

    public function isLate(): bool
    {
        return stripos((string) $this->status, 'terlambat') !== false;
    }

    public function lateMinutes(): ?int
    {
        if (preg_match('/(\d+)\s*(?:menit|mnt|m)/i', (string) $this->status, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\[TERLAMBAT[^\]]*?(\d+)\s*(?:menit|mnt|m)/i', (string) $this->catatan, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public function approvalLabel(): string
    {
        return match (strtolower(trim((string) $this->approval_status))) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu',
        };
    }

    public function radiusLabel(): string
    {
        $raw = strtolower(str_replace(['_', ' '], '', (string) $this->tipe_pengajuan));

        return match ($raw) {
            'dalamradius' => 'Dalam Radius',
            'lemburdinihari' => 'Lembur Dini Hari',
            default => 'Luar Radius',
        };
    }
}
