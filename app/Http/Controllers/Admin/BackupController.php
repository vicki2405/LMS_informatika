<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Get the ordered list of tables to maintain foreign key integrity on insertion/deletion.
     */
    private function getTables(): array
    {
        $spatieConfig = config('permission.table_names', []);
        
        $rolesTable = $spatieConfig['roles'] ?? 'roles';
        $permissionsTable = $spatieConfig['permissions'] ?? 'permissions';
        $modelHasRolesTable = $spatieConfig['model_has_roles'] ?? 'model_has_roles';
        $roleHasPermissionsTable = $spatieConfig['role_has_permissions'] ?? 'role_has_permissions';
        $modelHasPermissionsTable = $spatieConfig['model_has_permissions'] ?? 'model_has_permissions';

        return [
            'users',
            $rolesTable,
            $permissionsTable,
            $modelHasRolesTable,
            $roleHasPermissionsTable,
            $modelHasPermissionsTable,
            'tahun_ajarans',
            'jurusans',
            'gurus',
            'kelases',
            'guru_kelas',
            'siswas',
            'mata_pelajarans',
            'kelompok_mapels',
            'cbt_bank_soals',
            'cbt_soals',
            'cbt_jadwals',
            'cbt_sesis',
            'cbt_nilais',
            'jadwals',
            'absensis',
            'jurnals',
            'tujuan_pembelajarans',
            'nilai_tps',
            'materis',
            'materi_kegiatan',
            'materi_reads',
            'tugas',
            'pengumpulan_tugas',
            'kasus_siswas',
        ];
    }

    /**
     * Export all tables to a single pretty JSON file.
     */
    public function export(): StreamedResponse
    {
        $backupData = [];
        $tables = $this->getTables();

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $backupData[$table] = DB::table($table)->get()->map(function ($row) {
                    return (array) $row;
                })->toArray();
            } else {
                $backupData[$table] = [];
            }
        }

        $jsonContent = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $filename = 'backup_sistem_sekolah_' . date('Y-m-d_H-i-s') . '.json';

        return response()->streamDownload(function () use ($jsonContent) {
            echo $jsonContent;
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Import JSON file, clear existing tables, restore data, and align sequences.
     */
    public function import(Request $request): RedirectResponse
    {
        @ini_set('max_execution_time', '300');

        $request->validate([
            'backup_file' => 'required|file|mimes:json,txt',
        ]);

        $file = $request->file('backup_file');
        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return redirect()->back()->withErrors(['backup_file' => 'File backup tidak valid. Gagal melakukan decode JSON.']);
        }

        $tables = $this->getTables();

        // Check if file contains at least one recognized table
        $hasKey = false;
        foreach ($tables as $table) {
            if (isset($data[$table])) {
                $hasKey = true;
                break;
            }
        }

        if (!$hasKey) {
            return redirect()->back()->withErrors(['backup_file' => 'File backup tidak cocok dengan skema database sistem sekolah.']);
        }

        $stats = [];

        DB::beginTransaction();

        try {
            Schema::disableForeignKeyConstraints();

            // 1. Delete existing records in reverse order
            foreach (array_reverse($tables) as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            // 2. Insert records in correct parent-child order with batch chunking & column filtering
            foreach ($tables as $table) {
                if (Schema::hasTable($table) && isset($data[$table]) && is_array($data[$table]) && count($data[$table]) > 0) {
                    $validColumns = array_flip(Schema::getColumnListing($table));
                    $cleanRows = [];

                    foreach ($data[$table] as $rawRow) {
                        $rowArray = (array) $rawRow;
                        // Filter out any columns that do not exist in current table schema
                        $filtered = array_intersect_key($rowArray, $validColumns);
                        if (!empty($filtered)) {
                            $cleanRows[] = $filtered;
                        }
                    }

                    if (!empty($cleanRows)) {
                        $chunks = array_chunk($cleanRows, 200);
                        foreach ($chunks as $chunk) {
                            DB::table($table)->insert($chunk);
                        }
                        $stats[$table] = count($cleanRows);
                    }
                }
            }

            Schema::enableForeignKeyConstraints();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Schema::enableForeignKeyConstraints();
            return redirect()->back()->withErrors(['backup_file' => 'Gagal merestore data: ' . $e->getMessage()]);
        }

        // 3. Reset auto-increment / sequences safely outside transaction
        try {
            if (config('database.default') === 'pgsql') {
                foreach ($tables as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'id')) {
                        $seqName = "{$table}_id_seq";
                        $seqExists = DB::select("SELECT EXISTS (SELECT 1 FROM pg_class WHERE relkind = 'S' AND relname = '{$seqName}')");
                        if (!empty($seqExists[0]->exists)) {
                            $maxId = DB::table($table)->max('id') ?? 0;
                            $nextId = $maxId + 1;
                            DB::statement("SELECT setval('{$seqName}', {$nextId}, false)");
                        }
                    }
                }
            }

            if (config('database.default') === 'mysql') {
                foreach ($tables as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'id')) {
                        $maxId = DB::table($table)->max('id') ?? 0;
                        $nextId = $maxId + 1;
                        DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$nextId}");
                    }
                }
            }
        } catch (\Exception $e) {
            logger()->warning('Failed to reset auto-increment sequences after restore: ' . $e->getMessage());
        }

        $totalSiswa = $stats['siswas'] ?? 0;
        $totalKelas = $stats['kelases'] ?? 0;
        $totalGuru = $stats['gurus'] ?? 0;
        $totalAbsensi = $stats['absensis'] ?? 0;

        $msg = "Data sistem sekolah berhasil direstore! Dipulihkan: {$totalSiswa} Siswa, {$totalKelas} Kelas, {$totalGuru} Guru, {$totalAbsensi} Absensi.";

        return redirect()->back()->with('message', $msg);
    }
}
