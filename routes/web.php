<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Guru\GuruDashboardController;
use App\Http\Controllers\Guru\GuruCbtController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Siswa\SiswaDashboardController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\WaliKelas\WaliKelasDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [WelcomeController::class, 'index']);

Route::get('/dashboard', function (Request $request) {
    $user = $request->user();
    
    if ($user && $user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }
    
    if ($user && $user->hasRole('wali_kelas')) {
        return redirect()->route('wali-kelas.dashboard');
    }

    if ($user && $user->hasRole('guru')) {
        return redirect()->route('guru.dashboard');
    }

    if ($user && $user->hasRole('murid')) {
        return redirect()->route('siswa.dashboard');
    }
    
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Protected Routes per Role
Route::middleware(['auth', 'verified'])->group(function () {
    // 1. Admin Routes
    Route::prefix('admin')->middleware('role:admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::post('/guru/store', [AdminDashboardController::class, 'storeGuruMaster'])->name('guru.store');
        Route::delete('/guru/{id}', [AdminDashboardController::class, 'deleteGuruMaster'])->name('guru.delete');
        Route::post('/kelas/store', [AdminDashboardController::class, 'storeKelas'])->name('kelas.store');
        Route::delete('/kelas/{id}', [AdminDashboardController::class, 'deleteKelas'])->name('kelas.delete');
        Route::post('/jurusan/store', [AdminDashboardController::class, 'storeJurusan'])->name('jurusan.store');
        Route::delete('/jurusan/{id}', [AdminDashboardController::class, 'deleteJurusan'])->name('jurusan.delete');
        Route::post('/mapel/store', [AdminDashboardController::class, 'storeMapel'])->name('mapel.store');
        Route::delete('/mapel/{id}', [AdminDashboardController::class, 'deleteMapel'])->name('mapel.delete');
        Route::post('/kelompok/store', [AdminDashboardController::class, 'storeKelompokMapel'])->name('kelompok.store');
        Route::delete('/kelompok/{id}', [AdminDashboardController::class, 'deleteKelompokMapel'])->name('kelompok.delete');
        Route::post('/siswa/store', [AdminDashboardController::class, 'storeSiswa'])->name('siswa.store');
        Route::post('/siswa/import-batch', [AdminDashboardController::class, 'importSiswaBatch'])->name('siswa.import-batch');
        Route::post('/siswa/import-file', [AdminDashboardController::class, 'importSiswaFile'])->name('siswa.import-file');
        Route::get('/siswa/template', [AdminDashboardController::class, 'downloadSiswaTemplate'])->name('siswa.template');
        Route::post('/siswa/bulk-delete', [AdminDashboardController::class, 'deleteSiswaBulk'])->name('siswa.bulk-delete');
        Route::delete('/siswa/{id}', [AdminDashboardController::class, 'deleteSiswa'])->name('siswa.delete');
        Route::post('/user/store', [AdminDashboardController::class, 'storeUser'])->name('user.store');
        Route::post('/user/reset-password', [AdminDashboardController::class, 'resetPassword'])->name('user.reset-password');
        Route::delete('/user/{id}', [AdminDashboardController::class, 'deleteUser'])->name('user.delete');

        // Backup & Restore
        Route::get('/backup/export', [BackupController::class, 'export'])->name('backup.export');
        Route::post('/backup/import', [BackupController::class, 'import'])->name('backup.import');
    });

    // 2. Wali Kelas Routes
    Route::prefix('wali-kelas')->middleware('role:wali_kelas|admin')->name('wali-kelas.')->group(function () {
        Route::get('/dashboard', [WaliKelasDashboardController::class, 'index'])->name('dashboard');
        Route::post('/siswa/store', [WaliKelasDashboardController::class, 'storeSiswa'])->name('siswa.store');
        Route::delete('/siswa/{id}', [WaliKelasDashboardController::class, 'deleteSiswa'])->name('siswa.delete');
        Route::post('/jadwal/store', [WaliKelasDashboardController::class, 'storeJadwal'])->name('jadwal.store');
        Route::delete('/jadwal/{id}', [WaliKelasDashboardController::class, 'deleteJadwal'])->name('jadwal.delete');
        Route::post('/absensi/store-batch', [WaliKelasDashboardController::class, 'storeAbsensiBatch'])->name('absensi.store-batch');
        Route::patch('/absensi/{id}', [WaliKelasDashboardController::class, 'updateAbsensi'])->name('absensi.update');
        Route::delete('/absensi/bulk-date', [WaliKelasDashboardController::class, 'deleteAbsensiBulkDate'])->name('absensi.bulk-delete');
        Route::delete('/absensi/{id}', [WaliKelasDashboardController::class, 'deleteAbsensi'])->name('absensi.delete');
        Route::post('/kasus/store', [WaliKelasDashboardController::class, 'storeKasus'])->name('kasus.store');
        Route::delete('/kasus/{id}', [WaliKelasDashboardController::class, 'deleteKasus'])->name('kasus.delete');
    });

    // 3. Guru Mapel Routes
    Route::prefix('guru')->middleware('role:guru|admin')->name('guru.')->group(function () {
        Route::get('/dashboard', [GuruDashboardController::class, 'index'])->name('dashboard');
        Route::get('/jurnal/export-pdf', [GuruDashboardController::class, 'exportJurnalPdf'])->name('jurnal.export-pdf');
        Route::get('/materi/create', [GuruDashboardController::class, 'createMateri'])->name('materi.create');
        Route::get('/materi/{id}/edit', [GuruDashboardController::class, 'editMateri'])->name('materi.edit');
        Route::post('/absensi/store-batch', [GuruDashboardController::class, 'storeAbsensiBatch'])->name('absensi.store-batch');
        Route::patch('/absensi/{id}', [GuruDashboardController::class, 'updateAbsensi'])->name('absensi.update');
        Route::delete('/absensi/bulk-date', [GuruDashboardController::class, 'deleteAbsensiBulkDate'])->name('absensi.bulk-delete');
        Route::delete('/absensi/{id}', [GuruDashboardController::class, 'deleteAbsensi'])->name('absensi.delete');
        Route::post('/nilai/store-batch', [GuruDashboardController::class, 'storeNilaiBatch'])->name('nilai.store-batch');
        Route::post('/jurnal/store', [GuruDashboardController::class, 'storeJurnal'])->name('jurnal.store');
        Route::delete('/jurnal/{id}', [GuruDashboardController::class, 'deleteJurnal'])->name('jurnal.delete');
        Route::post('/materi/store', [GuruDashboardController::class, 'storeMateri'])->name('materi.store');
        Route::delete('/materi/{id}', [GuruDashboardController::class, 'deleteMateri'])->name('materi.delete');
        Route::get('/materi/{id}/monitoring', [GuruDashboardController::class, 'getMateriMonitoring'])->name('materi.monitoring');
        Route::post('/tp/store', [GuruDashboardController::class, 'storeTp'])->name('tp.store');
        Route::delete('/tp/{id}', [GuruDashboardController::class, 'deleteTp'])->name('tp.delete');
        Route::post('/jadwal/store', [GuruDashboardController::class, 'storeJadwal'])->name('jadwal.store');
        Route::delete('/jadwal/{id}', [GuruDashboardController::class, 'deleteJadwal'])->name('jadwal.delete');
        // Kelas Ampuan — pivot sync & detach
        Route::post('/kelas-ampuan/save', [GuruDashboardController::class, 'saveKelasAmpuan'])->name('kelas-ampuan.save');
        Route::delete('/kelas-ampuan/{kelas_id}', [GuruDashboardController::class, 'removeKelasAmpuan'])->name('kelas-ampuan.remove');

        // CBT Routes
        Route::post('/cbt/bank-soal/store', [GuruCbtController::class, 'storeBankSoal'])->name('cbt.bank-soal.store');
        Route::delete('/cbt/bank-soal/{id}', [GuruCbtController::class, 'deleteBankSoal'])->name('cbt.bank-soal.delete');
        Route::post('/cbt/soal/store', [GuruCbtController::class, 'storeSoal'])->name('cbt.soal.store');
        Route::delete('/cbt/soal/{id}', [GuruCbtController::class, 'deleteSoal'])->name('cbt.soal.delete');
        Route::post('/cbt/soal/update-all-bobot', [GuruCbtController::class, 'updateAllBobot'])->name('cbt.soal.update-all-bobot');
        Route::post('/cbt/soal/import-bulk', [GuruCbtController::class, 'importBulkSoal'])->name('cbt.soal.import-bulk');
        Route::post('/cbt/jadwal/store', [GuruCbtController::class, 'storeJadwal'])->name('cbt.jadwal.store');
        Route::patch('/cbt/jadwal/{id}/toggle-status', [GuruCbtController::class, 'toggleJadwalStatus'])->name('cbt.jadwal.toggle-status');
        Route::delete('/cbt/jadwal/{id}', [GuruCbtController::class, 'deleteJadwal'])->name('cbt.jadwal.delete');
        Route::get('/cbt/jadwal/{id}/monitoring', [GuruCbtController::class, 'getMonitoringData'])->name('cbt.jadwal.monitoring');
        Route::post('/cbt/sesi/{id}/reset', [GuruCbtController::class, 'resetSesiSiswa'])->name('cbt.sesi.reset');
        Route::post('/cbt/sesi/{id}/kunci', [GuruCbtController::class, 'kunciSesiSiswa'])->name('cbt.sesi.kunci');
        Route::post('/cbt/sesi/{id}/buka-kunci', [GuruCbtController::class, 'bukaKunciSesiSiswa'])->name('cbt.sesi.buka-kunci');
        Route::delete('/cbt/sesi/{id}', [GuruCbtController::class, 'deleteSesiSiswa'])->name('cbt.sesi.delete');
        Route::post('/cbt/nilai/store', [GuruCbtController::class, 'storeNilai'])->name('cbt.nilai.store');
        Route::delete('/cbt/nilai/{id}', [GuruCbtController::class, 'deleteNilai'])->name('cbt.nilai.delete');
        Route::post('/cbt/transfer-nilai-tp', [GuruCbtController::class, 'transferToNilaiTp'])->name('cbt.transfer-nilai-tp');
    });

    // 4. Siswa Routes
    Route::prefix('siswa')->middleware('role:murid|admin')->name('siswa.')->group(function () {
        Route::get('/dashboard', [SiswaDashboardController::class, 'index'])->name('dashboard');
        Route::post('/tugas/submit', [SiswaDashboardController::class, 'submitTugas'])->name('tugas.submit');
        Route::post('/cbt/gabung-ruang-tunggu', [SiswaDashboardController::class, 'gabungRuangTunggu'])->name('cbt.gabung-ruang-tunggu');
        Route::post('/cbt/keluar-ruang-tunggu', [SiswaDashboardController::class, 'keluarRuangTunggu'])->name('cbt.keluar-ruang-tunggu');
        Route::post('/cbt/catat-pelanggaran', [SiswaDashboardController::class, 'catatPelanggaran'])->name('cbt.catat-pelanggaran');
        Route::post('/cbt/heartbeat', [SiswaDashboardController::class, 'heartbeatSesi'])->name('cbt.heartbeat');
        Route::post('/cbt/submit', [SiswaDashboardController::class, 'submitCbtExam'])->name('cbt.submit');
        Route::post('/materi/{id}/track-open', [SiswaDashboardController::class, 'trackMateriOpen'])->name('materi.track-open');
        Route::post('/materi/{id}/track-complete', [SiswaDashboardController::class, 'trackMateriComplete'])->name('materi.track-complete');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
