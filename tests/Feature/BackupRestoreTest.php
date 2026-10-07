<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Siswa;
use App\Models\Kelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_and_restore_database(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\KelasSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create a student to backup
        $kelasObj = Kelas::first();
        $siswa = Siswa::create([
            'nama' => 'TEST BACKUP STUDENT',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $kelasObj->id,
            'telepon' => '081234567890',
            'status' => 'Aktif',
            'tanggal_status' => null,
        ]);

        // 1. Export database
        $response = $this
            ->actingAs($admin)
            ->get(route('admin.backup.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        $jsonContent = $response->streamedContent();
        $backupData = json_decode($jsonContent, true);

        $this->assertIsArray($backupData);
        $this->assertArrayHasKey('users', $backupData);
        $this->assertArrayHasKey('siswas', $backupData);
        $this->assertArrayHasKey('kelases', $backupData);

        // Verify our student is in the backup array
        $siswaInBackup = collect($backupData['siswas'])->firstWhere('nama', 'TEST BACKUP STUDENT');
        $this->assertNotNull($siswaInBackup);

        // Delete student from DB to simulate data loss
        $siswa->delete();
        $this->assertDatabaseMissing('siswas', ['nama' => 'TEST BACKUP STUDENT']);

        // Create temporary file with the backup contents
        $tempFile = UploadedFile::fake()->createWithContent(
            'backup.json',
            $jsonContent
        );

        // 2. Restore database
        $restoreResponse = $this
            ->actingAs($admin)
            ->post(route('admin.backup.import'), [
                'backup_file' => $tempFile,
            ]);

        $restoreResponse->assertSessionHasNoErrors();
        $restoreResponse->assertRedirect();

        // 3. Verify student is restored back into DB!
        $this->assertDatabaseHas('siswas', [
            'nama' => 'TEST BACKUP STUDENT',
            'telepon' => '081234567890',
        ]);
    }
}
