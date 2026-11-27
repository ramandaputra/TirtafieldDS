<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nik',
        'name',
        'email',
        'pin',
        'password',
        'role',
        'department',
        'position',
        'phone',
        'office_id',
        'office_name',
        'avatar_url',
        'status_pegawai',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id', 'id');
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'nik', 'nik');
    }

    /**
     * Verifikasi PIN atau password terhadap kredensial aktif pengguna.
     */
    public function verifySecret(?string $secret): bool
    {
        if (empty($secret)) {
            return false;
        }

        $secret = trim($secret);

        if (! empty($this->pin)) {
            return (string) $this->pin === $secret;
        }

        if (! empty($this->password)) {
            if ((string) $this->password === $secret || md5($secret) === $this->password) {
                return true;
            }

            try {
                if (Hash::check($secret, $this->password)) {
                    return true;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'staffIT' => 'Staff IT',
            'direktur' => 'Direktur',
            'staffPusimpel' => 'Staff Pusimpel',
            default => 'Pegawai',
        };
    }
}
