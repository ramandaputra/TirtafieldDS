<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthAndRecapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        $this->seedTestData();
    }

    public function test_login_page_is_accessible_for_guests(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Masuk ke Tirtafield DS');
        $response->assertSee('NIK / NIP atau Email');
        $response->assertSee('PIN atau Kata Sandi');
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/rekap/pegawai')->assertRedirect('/login');
        $this->get('/rekap/capeg-honor-pkwt-kontrak')->assertRedirect('/login');
    }

    public function test_user_can_login_with_nik_and_pin_and_see_two_action_buttons(): void
    {
        $response = $this->post('/login', [
            'nik' => 'P242101',
            'pin' => '123456',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();

        $dashboard = $this->get('/');
        $dashboard->assertStatus(200);
        $dashboard->assertSee('Rekap Absensi Pegawai');
        $dashboard->assertSee('Rekap Absensi Capeg, Honor, PKWT, Kontrak');
        $dashboard->assertSee('2 Personil');
        $dashboard->assertSee('4 Personil');
    }

    public function test_user_can_login_with_email_and_pin(): void
    {
        $response = $this->post('/login', [
            'nik' => 'pusimpel@tirtakepri.co.id',
            'pin' => '123456',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_invalid_pin_or_inactive_user(): void
    {
        $wrongPin = $this->post('/login', [
            'nik' => 'P242101',
            'pin' => '999999',
        ]);

        $wrongPin->assertSessionHasErrors('pin');
        $this->assertGuest();

        $inactive = $this->post('/login', [
            'nik' => 'NONAKTIF',
            'pin' => '123456',
        ]);

        $inactive->assertSessionHasErrors('nik');
        $this->assertGuest();
    }

    public function test_rekap_pegawai_displays_only_pegawai_tetap_and_direksi(): void
    {
        $user = User::where('nik', 'P242101')->firstOrFail();

        $response = $this->actingAs($user)->get('/rekap/pegawai?start_date=2026-11-01&end_date=2026-11-30');

        $response->assertStatus(200);
        $response->assertSee('Pegawai Tetap Satu');
        $response->assertSee('Direktur Utama');
        $response->assertDontSee('Capeg Satu');
        $response->assertDontSee('Honor Satu');
        $response->assertDontSee('PKWT Satu');
        $response->assertDontSee('Kontrak Satu');
    }

    public function test_rekap_non_tetap_displays_capeg_honor_pkwt_kontrak_and_supports_sub_status_filter(): void
    {
        $user = User::where('nik', 'P242101')->firstOrFail();

        $all = $this->actingAs($user)->get('/rekap/capeg-honor-pkwt-kontrak?start_date=2026-11-01&end_date=2026-11-30');
        $all->assertStatus(200);
        $all->assertSee('Capeg Satu');
        $all->assertSee('Honor Satu');
        $all->assertSee('PKWT Satu');
        $all->assertSee('Kontrak Satu');
        $all->assertDontSee('Pegawai Tetap Satu');

        $capegOnly = $this->actingAs($user)->get('/rekap/capeg-honor-pkwt-kontrak?start_date=2026-11-01&end_date=2026-11-30&sub_status=Capeg');
        $capegOnly->assertStatus(200);
        $capegOnly->assertSee('Capeg Satu');
        $capegOnly->assertDontSee('Honor Satu');
        $capegOnly->assertDontSee('Kontrak Satu');
    }

    public function test_detail_json_and_csv_export_work_correctly(): void
    {
        $user = User::where('nik', 'P242101')->firstOrFail();

        $detail = $this->actingAs($user)->getJson('/rekap/non_tetap/detail/K202609?start_date=2026-11-01&end_date=2026-11-30');
        $detail->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.person.nik', 'K202609')
            ->assertJsonCount(2, 'data.logs');

        $csv = $this->actingAs($user)->get('/rekap/pegawai/export?start_date=2026-11-01&end_date=2026-11-30');
        $csv->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));

        $content = $csv->streamedContent();
        $this->assertStringContainsString('Pegawai Tetap Satu', $content);
        $this->assertStringNotContainsString('Kontrak Satu', $content);
    }

    public function test_user_can_logout(): void
    {
        $user = User::where('nik', 'P242101')->firstOrFail();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('presensi');
        Schema::dropIfExists('employee');
        Schema::dropIfExists('offices');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nik')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('pin')->default('123456');
            $table->string('password')->default('123456');
            $table->string('role')->default('karyawan');
            $table->string('department')->default('Umum');
            $table->string('position')->default('Staf');
            $table->string('phone')->nullable();
            $table->string('office_id')->default('hq');
            $table->string('office_name')->default('Kantor Pusat');
            $table->text('avatar_url')->nullable();
            $table->string('status_pegawai')->default('Karyawan Tetap');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('offices', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('nama_kantor');
            $table->string('alamat')->nullable();
            $table->decimal('latitude', 11, 8)->default(0);
            $table->decimal('longitude', 11, 8)->default(0);
            $table->integer('radius')->default(100);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee', function (Blueprint $table): void {
            $table->increments('pgw_id');
            $table->string('pgw_nup')->nullable();
            $table->string('pgw_nama');
            $table->integer('pgw_sttskerja')->nullable();
            $table->integer('dept_id')->nullable();
            $table->integer('subdept_id')->nullable();
            $table->integer('occ_id')->nullable();
            $table->string('occ_add_desc')->nullable();
            $table->string('seksi_bidang')->nullable();
            $table->string('pgw_nohp')->nullable();
            $table->date('pgw_tglmasuk')->nullable();
            $table->integer('ishapus')->default(0);
            $table->integer('ispensiun')->nullable();
            $table->string('office_id')->nullable();
            $table->string('office_name')->nullable();
        });

        Schema::create('presensi', function (Blueprint $table): void {
            $table->id();
            $table->string('pgw_id')->nullable();
            $table->string('nik')->nullable();
            $table->string('pgw_nup')->nullable();
            $table->string('pgw_nama')->nullable();
            $table->date('tanggal');
            $table->string('slot_type');
            $table->string('waktu');
            $table->string('lokasi')->nullable();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('alamat')->nullable();
            $table->string('jarak_hq')->nullable();
            $table->text('foto_url')->nullable();
            $table->decimal('biometric_score', 5, 2)->default(99.4);
            $table->text('catatan')->nullable();
            $table->string('status')->default('Tepat Waktu');
            $table->string('approval_status')->default('pending');
            $table->string('tipe_pengajuan')->default('dalamRadius');
            $table->string('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });
    }

    private function seedTestData(): void
    {
        DB::table('offices')->insert([
            'id' => 'hq',
            'nama_kantor' => 'Kantor Pusat Perumda Air Minum Tirta Kepri',
            'alamat' => 'Tanjungpinang',
            'is_primary' => true,
            'is_active' => true,
        ]);

        DB::table('users')->insert([
            [
                'nik' => 'P242101',
                'name' => 'Pusimpel',
                'email' => 'pusimpel@tirtakepri.co.id',
                'pin' => '123456',
                'password' => '123456',
                'role' => 'staffIT',
                'status_pegawai' => 'Karyawan Tetap',
                'is_active' => true,
            ],
            [
                'nik' => 'K202609',
                'name' => 'Kontrak Satu',
                'email' => 'kontrak@tirtakepri.co.id',
                'pin' => '131518',
                'password' => '131518',
                'role' => 'karyawan',
                'status_pegawai' => 'Karyawan Tetap',
                'is_active' => true,
            ],
            [
                'nik' => 'NONAKTIF',
                'name' => 'Akun Nonaktif',
                'email' => 'nonaktif@tirtakepri.co.id',
                'pin' => '123456',
                'password' => '123456',
                'role' => 'karyawan',
                'status_pegawai' => 'Karyawan Tetap',
                'is_active' => false,
            ],
        ]);

        DB::table('employee')->insert([
            [
                'pgw_nup' => '020101',
                'pgw_nama' => 'Pegawai Tetap Satu',
                'pgw_sttskerja' => 1,
                'dept_id' => 3,
                'occ_id' => 6,
                'ishapus' => 0,
                'office_id' => 'hq',
            ],
            [
                'pgw_nup' => 'D001',
                'pgw_nama' => 'Direktur Utama',
                'pgw_sttskerja' => 3,
                'dept_id' => 1,
                'occ_id' => 7,
                'ishapus' => 0,
                'office_id' => 'hq',
            ],
            [
                'pgw_nup' => '020242',
                'pgw_nama' => 'Capeg Satu',
                'pgw_sttskerja' => 2,
                'dept_id' => 3,
                'occ_id' => 1,
                'ishapus' => 0,
                'office_id' => 'hq',
            ],
            [
                'pgw_nup' => 'H202507',
                'pgw_nama' => 'Honor Satu',
                'pgw_sttskerja' => 4,
                'dept_id' => 6,
                'occ_id' => 5,
                'ishapus' => 0,
                'office_id' => 'hq',
            ],
            [
                'pgw_nup' => 'PA002',
                'pgw_nama' => 'PKWT Satu',
                'pgw_sttskerja' => 9,
                'dept_id' => 2,
                'occ_id' => 21,
                'ishapus' => 0,
                'office_id' => 'hq',
            ],
            [
                'pgw_nup' => 'K202609',
                'pgw_nama' => 'Kontrak Satu',
                'pgw_sttskerja' => 4,
                'dept_id' => 6,
                'occ_id' => 2,
                'ishapus' => 0,
                'office_id' => 'hq',
            ],
            [
                'pgw_nup' => 'P242101',
                'pgw_nama' => 'Pusimpel',
                'pgw_sttskerja' => null,
                'dept_id' => null,
                'occ_id' => null,
                'ishapus' => 0,
                'office_id' => 'hq',
            ],
        ]);

        DB::table('presensi')->insert([
            [
                'nik' => '020101',
                'pgw_nama' => 'Pegawai Tetap Satu',
                'tanggal' => '2026-11-16',
                'slot_type' => 'masuk',
                'waktu' => '2026-11-16 07:55:00',
                'status' => 'Tepat Waktu',
                'approval_status' => 'approved',
                'tipe_pengajuan' => 'dalamRadius',
            ],
            [
                'nik' => 'K202609',
                'pgw_nama' => 'Kontrak Satu',
                'tanggal' => '2026-11-16',
                'slot_type' => 'masuk',
                'waktu' => '2026-11-16 08:45:00',
                'status' => 'Terlambat (30 Menit)',
                'approval_status' => 'approved',
                'tipe_pengajuan' => 'dalamRadius',
            ],
            [
                'nik' => 'K202609',
                'pgw_nama' => 'Kontrak Satu',
                'tanggal' => '2026-11-16',
                'slot_type' => 'pulang',
                'waktu' => '2026-11-16 17:05:00',
                'status' => 'Tepat Waktu',
                'approval_status' => 'approved',
                'tipe_pengajuan' => 'dalamRadius',
            ],
        ]);
    }
}
