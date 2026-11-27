<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $table = 'employee';

    protected $primaryKey = 'pgw_id';

    public $timestamps = false;

    /**
     * @var array<string, string>
     */
    public const DEPARTMENT_MAP = [
        1 => 'Dewan Direksi',
        2 => 'Dewan Pengawas',
        3 => 'Bidang Hubungan Langganan',
        4 => 'Bagian Keuangan',
        5 => 'Bidang Teknik',
        6 => 'PUSIMPEL IT & System',
        7 => 'Sekretariat',
        8 => 'Satuan Pengawas Intern',
        9 => 'SPKA',
        11 => 'Bagian Umum',
        12 => 'Bidang Teknik & Produksi',
        13 => 'Operasional & Umum',
        14 => 'Bidang Produksi',
    ];

    /**
     * @var array<string, string>
     */
    public const OCCUPATION_MAP = [
        1 => 'Staf (Capeg)',
        2 => 'Staf (Kontrak)',
        3 => 'Kepala Bagian',
        4 => 'Kepala Cabang',
        5 => 'Staf (Honor)',
        6 => 'Staf',
        7 => 'Direktur',
        12 => 'Kepala Sub Bagian',
        13 => 'Kepala Shift',
        14 => 'Ketua Dewan Pengawas',
        15 => 'Kepala Sub Seksi',
        16 => 'Supervisor',
        17 => 'Kepala Seksi',
        19 => 'Kepala Satuan',
        20 => 'Pengawas / Kepala Seksi',
        21 => 'Anggota Dewan Pengawas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pgw_sttskerja' => 'integer',
            'dept_id' => 'integer',
            'subdept_id' => 'integer',
            'occ_id' => 'integer',
            'ishapus' => 'integer',
            'ispensiun' => 'integer',
            'pgw_tglmasuk' => 'date:Y-m-d',
        ];
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'nik', 'pgw_nup');
    }

    /**
     * Tentukan kategori rekap: 'pegawai' atau 'non_tetap'.
     */
    public function recapCategory(): ?string
    {
        $nup = strtoupper(trim((string) $this->pgw_nup));

        if ($this->pgw_sttskerja === null) {
            return null;
        }

        if ($this->pgw_sttskerja === 1) {
            return 'pegawai';
        }

        if ($this->pgw_sttskerja === 3 && ($nup === 'D001' || $this->occ_id === 7 || $this->dept_id === 1)) {
            return 'pegawai';
        }

        if (in_array($this->pgw_sttskerja, [2, 3, 4, 9], true)) {
            return 'non_tetap';
        }

        return null;
    }

    /**
     * Label status kepegawaian terperinci (Pegawai Tetap, Direksi, Capeg, Honor, PKWT, Kontrak).
     */
    public function statusLabel(): string
    {
        $nup = strtoupper(trim((string) $this->pgw_nup));

        if ($this->pgw_sttskerja === 1) {
            return 'Pegawai Tetap';
        }

        if ($this->pgw_sttskerja === 2) {
            return 'Capeg';
        }

        if ($this->pgw_sttskerja === 9) {
            return 'PKWT';
        }

        if ($this->pgw_sttskerja === 3) {
            if ($nup === 'D001' || $this->occ_id === 7 || $this->dept_id === 1) {
                return 'Direksi';
            }

            return str_starts_with($nup, 'H') ? 'Honor' : 'Kontrak';
        }

        if ($this->pgw_sttskerja === 4) {
            if (str_starts_with($nup, 'K')) {
                return 'Kontrak';
            }

            return 'Honor';
        }

        return 'Lainnya';
    }

    public function departmentLabel(): string
    {
        if (! empty($this->dept_id) && isset(self::DEPARTMENT_MAP[(int) $this->dept_id])) {
            return self::DEPARTMENT_MAP[(int) $this->dept_id];
        }

        return 'Unit Umum';
    }

    public function positionLabel(): string
    {
        if (! empty($this->seksi_bidang)) {
            return (string) $this->seksi_bidang;
        }

        if (! empty($this->occ_id) && isset(self::OCCUPATION_MAP[(int) $this->occ_id])) {
            $base = self::OCCUPATION_MAP[(int) $this->occ_id];
            if (! empty($this->occ_add_desc)) {
                return $base.' - '.$this->occ_add_desc;
            }

            return $base;
        }

        if (! empty($this->occ_add_desc)) {
            return (string) $this->occ_add_desc;
        }

        return 'Staf';
    }
}
