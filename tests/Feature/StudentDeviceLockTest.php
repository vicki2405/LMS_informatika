<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentDeviceLockTest extends TestCase
{
    use RefreshDatabase;

    private User $sharedUser;
    private User $guruUser;
    private Guru $guru;
    private Kelas $kelas;
    private Siswa $siswa1;
    private Siswa $siswa2;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'murid']);
        Role::firstOrCreate(['name' => 'guru']);
        Role::firstOrCreate(['name' => 'admin']);

        $this->kelas = Kelas::create([
            'nama_kelas' => 'X TKR 1',
            'tingkat' => 'X',
        ]);

        $this->sharedUser = User::create([
            'name' => 'Siswa Kelas X TKR 1',
            'username' => 'siswa_xtkr1',
            'email' => 'siswa.xtkr1@sekolah.sch.id',
            'password' => bcrypt('123456'),
            'kelas_id' => $this->kelas->id,
        ]);
        $this->sharedUser->assignRole('murid');

        $this->guruUser = User::create([
            'name' => 'Guru Pengajar',
            'username' => 'guru_pengajar',
            'email' => 'guru@sekolah.sch.id',
            'password' => bcrypt('password'),
        ]);
        $this->guruUser->assignRole('guru');

        $this->guru = Guru::create([
            'user_id' => $this->guruUser->id,
            'nama' => 'Guru Pengajar',
            'nip' => '198001012010011001',
        ]);
        $this->guru->kelasAmpuan()->attach($this->kelas->id);

        $this->siswa1 = Siswa::create([
            'nama' => 'Ahmad Santoso',
            'nis' => '1001',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $this->kelas->id,
            'status' => 'Aktif',
        ]);

        $this->siswa2 = Siswa::create([
            'nama' => 'Budi Pratama',
            'nis' => '1002',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $this->kelas->id,
            'status' => 'Aktif',
        ]);
    }

    public function test_student_can_bind_identity_to_device(): void
    {
        $response = $this->actingAs($this->sharedUser)
            ->postJson(route('siswa.bind-identity'), [
                'siswa_id' => $this->siswa1->id,
                'device_id' => 'device_hp_ahmad_1',
                'device_name' => 'Chrome (Android)',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->siswa1->refresh();
        $this->assertEquals('device_hp_ahmad_1', $this->siswa1->device_id);
        $this->assertEquals('Chrome (Android)', $this->siswa1->device_name);
        $this->assertNotNull($this->siswa1->device_locked_at);
    }

    public function test_second_device_cannot_bind_already_locked_student(): void
    {
        // Ahmad locks device 1
        $this->siswa1->lockToDevice('device_hp_ahmad_1', 'Chrome (Android)');

        // Device 2 attempts to select Ahmad
        $response = $this->actingAs($this->sharedUser)
            ->postJson(route('siswa.bind-identity'), [
                'siswa_id' => $this->siswa1->id,
                'device_id' => 'device_hp_budi_2',
                'device_name' => 'Safari (iPhone)',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        // Ahmad remains bound to device 1
        $this->siswa1->refresh();
        $this->assertEquals('device_hp_ahmad_1', $this->siswa1->device_id);
    }

    public function test_student_can_unbind_identity_and_rebind_to_another_device(): void
    {
        $this->siswa1->lockToDevice('device_hp_ahmad_1', 'Chrome (Android)');

        // Student unbinds from device 1
        $response = $this->actingAs($this->sharedUser)
            ->postJson(route('siswa.unbind-identity'), [
                'siswa_id' => $this->siswa1->id,
                'device_id' => 'device_hp_ahmad_1',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->siswa1->refresh();
        $this->assertNull($this->siswa1->device_id);

        // Now device 2 can bind
        $bindResponse = $this->actingAs($this->sharedUser)
            ->postJson(route('siswa.bind-identity'), [
                'siswa_id' => $this->siswa1->id,
                'device_id' => 'device_hp_ahmad_2',
                'device_name' => 'Firefox (Android)',
            ]);

        $bindResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->siswa1->refresh();
        $this->assertEquals('device_hp_ahmad_2', $this->siswa1->device_id);
    }

    public function test_teacher_can_reset_student_device_lock(): void
    {
        $this->siswa1->lockToDevice('device_hp_ahmad_1', 'Chrome (Android)');

        $response = $this->actingAs($this->guruUser)
            ->postJson(route('guru.siswa.reset-device', $this->siswa1->id));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->siswa1->refresh();
        $this->assertNull($this->siswa1->device_id);
        $this->assertNull($this->siswa1->device_name);
        $this->assertNull($this->siswa1->device_locked_at);

        // After teacher reset, new device can bind immediately
        $bindResponse = $this->actingAs($this->sharedUser)
            ->postJson(route('siswa.bind-identity'), [
                'siswa_id' => $this->siswa1->id,
                'device_id' => 'device_hp_ahmad_new',
                'device_name' => 'Samsung Browser (Android)',
            ]);

        $bindResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->siswa1->refresh();
        $this->assertEquals('device_hp_ahmad_new', $this->siswa1->device_id);
    }

    public function test_teacher_can_bulk_reset_student_device_locks(): void
    {
        $this->siswa1->lockToDevice('device_hp_1', 'Chrome (Android)');
        $this->siswa2->lockToDevice('device_hp_2', 'Safari (iOS)');

        $response = $this->actingAs($this->guruUser)
            ->postJson(route('guru.siswa.reset-devices-bulk'), [
                'siswa_ids' => [$this->siswa1->id, $this->siswa2->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->siswa1->refresh();
        $this->siswa2->refresh();

        $this->assertNull($this->siswa1->device_id);
        $this->assertNull($this->siswa2->device_id);
    }
}
