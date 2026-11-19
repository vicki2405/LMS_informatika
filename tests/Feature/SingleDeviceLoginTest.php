<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleDeviceLoginTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;
    protected Siswa $student;
    protected User $teacherUser;
    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->kelas = Kelas::create([
            'nama_kelas' => 'XII RPL 1',
            'tingkat' => 'XII',
        ]);

        $this->student = Siswa::create([
            'nama' => 'Budi Pratama',
            'nis' => '10088',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $this->kelas->id,
            'status' => 'Aktif',
        ]);

        $this->studentUser = User::factory()->create([
            'name' => 'Budi Pratama',
            'username' => '10088',
            'email' => 'budi@sekolah.sch.id',
            'password' => bcrypt('password123'),
        ]);
        $this->studentUser->assignRole('murid');
        $this->student->update(['user_id' => $this->studentUser->id]);

        $this->teacherUser = User::factory()->create([
            'name' => 'Guru Pengawas',
            'username' => 'guru1',
            'email' => 'guru@sekolah.sch.id',
            'password' => bcrypt('password123'),
        ]);
        $this->teacherUser->assignRole('guru');
    }

    public function test_student_login_registers_active_device_session(): void
    {
        $response = $this->post('/login', [
            'email' => 'budi@sekolah.sch.id',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->studentUser);

        $freshUser = $this->studentUser->fresh();
        $this->assertNotNull($freshUser->active_session_id);
        $this->assertNotNull($freshUser->last_activity_at);
    }

    public function test_student_cannot_login_concurrently_from_another_device(): void
    {
        // First login (Device 1)
        $this->post('/login', [
            'email' => '10088',
            'password' => 'password123',
        ]);

        $device1SessionId = $this->studentUser->fresh()->active_session_id;
        $this->assertNotNull($device1SessionId);

        // Simulate a second device with a fresh session attempting login
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $secondDeviceResponse = $this->post('/login', [
            'email' => '10088',
            'password' => 'password123',
        ]);

        $secondDeviceResponse->assertSessionHasErrors(['email']);
        $this->assertGuest();

        // Ensure original session lock remains intact
        $this->assertEquals($device1SessionId, $this->studentUser->fresh()->active_session_id);
    }

    public function test_student_can_login_after_normal_logout(): void
    {
        // Login on Device 1
        $this->post('/login', [
            'email' => 'budi@sekolah.sch.id',
            'password' => 'password123',
        ]);
        $this->assertAuthenticatedAs($this->studentUser);

        // Logout from Device 1
        $logoutResponse = $this->post('/logout');
        $logoutResponse->assertRedirect('/');
        $this->assertGuest();

        $this->assertNull($this->studentUser->fresh()->active_session_id);

        // Now Device 2 logs in
        $newLoginResponse = $this->post('/login', [
            'email' => 'budi@sekolah.sch.id',
            'password' => 'password123',
        ]);
        $newLoginResponse->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->studentUser);
    }

    public function test_teacher_reset_allows_student_to_login_on_new_device(): void
    {
        // Student is active on Device 1
        $this->studentUser->update([
            'active_session_id' => 'session_device_1',
            'last_activity_at' => now(),
        ]);

        // Teacher triggers reset device login
        $response = $this->actingAs($this->teacherUser)
            ->post(route('guru.cbt.siswa.reset-login', $this->student->id));

        $response->assertSessionHas('message');
        $this->assertEquals('RESET', $this->studentUser->fresh()->active_session_id);

        // Student now logs in from Device 2
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $loginResponse = $this->post('/login', [
            'email' => 'budi@sekolah.sch.id',
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->studentUser);
        $this->assertNotEquals('RESET', $this->studentUser->fresh()->active_session_id);
    }

    public function test_obsolete_session_is_invalidated_by_middleware(): void
    {
        // User has session ID in DB, but request has different session ID
        $this->studentUser->update([
            'active_session_id' => 'other_active_session',
            'last_activity_at' => now(),
        ]);

        $response = $this->actingAs($this->studentUser)->get('/siswa/dashboard');

        // Middleware detects mismatch and redirects to login with error
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_teacher_and_admin_accounts_are_not_restricted(): void
    {
        // Teacher has an existing session record
        $this->teacherUser->update([
            'active_session_id' => 'teacher_session_1',
            'last_activity_at' => now(),
        ]);

        // Second device login for teacher succeeds without blocking
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $response = $this->post('/login', [
            'email' => 'guru@sekolah.sch.id',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->teacherUser);
    }

    public function test_expired_session_allows_login_without_teacher_reset(): void
    {
        // Session was active 3 hours ago (> 120 minutes)
        $this->studentUser->update([
            'active_session_id' => 'old_stale_session',
            'last_activity_at' => now()->subHours(3),
        ]);

        $response = $this->post('/login', [
            'email' => 'budi@sekolah.sch.id',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->studentUser);
    }
}
