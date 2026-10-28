<?php

use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\GuruApiController;
use App\Http\Controllers\Api\SiswaApiController;
use App\Http\Controllers\Api\WaliKelasApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Sistem Manajemen Sekolah (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    
    // 1. Authentication Endpoints
    Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthApiController::class, 'me']);
        Route::post('/auth/logout', [AuthApiController::class, 'logout']);

        // 2. Admin API Endpoints
        Route::prefix('admin')->middleware('role:admin')->group(function () {
            Route::get('/guru', [AdminApiController::class, 'getGuru']);
            Route::post('/guru', [AdminApiController::class, 'storeGuru']);
            Route::delete('/guru/{id}', [AdminApiController::class, 'deleteGuru']);
            Route::get('/siswa', [AdminApiController::class, 'getSiswa']);
            Route::post('/siswa', [AdminApiController::class, 'storeSiswa']);
            Route::delete('/siswa/{id}', [AdminApiController::class, 'deleteSiswa']);
            Route::get('/kelas', [AdminApiController::class, 'getKelas']);
            Route::get('/jurusan', [AdminApiController::class, 'getJurusan']);
            Route::get('/users', [AdminApiController::class, 'getUsers']);
            Route::get('/tahun-ajaran', [AdminApiController::class, 'getTahunAjaran']);
            Route::post('/tahun-ajaran', [AdminApiController::class, 'storeTahunAjaran']);
            Route::post('/tahun-ajaran/{id}/set-default', [AdminApiController::class, 'setDefaultTahunAjaran']);
            Route::delete('/tahun-ajaran/{id}', [AdminApiController::class, 'deleteTahunAjaran']);
        });

        // 3. Guru API Endpoints
        Route::prefix('guru')->middleware('role:guru|admin')->group(function () {
            Route::get('/jadwal', [GuruApiController::class, 'getJadwal']);
            Route::post('/jadwal', [GuruApiController::class, 'storeJadwal']);
            Route::delete('/jadwal/{id}', [GuruApiController::class, 'deleteJadwal']);
            Route::get('/kelas', [GuruApiController::class, 'getKelas']);
            Route::post('/kelas-ampuan', [GuruApiController::class, 'saveKelasAmpuan']);
            Route::delete('/kelas-ampuan/{id}', [GuruApiController::class, 'removeKelasAmpuan']);
            Route::get('/absensi', [GuruApiController::class, 'getAbsensi']);
            Route::post('/absensi/batch', [GuruApiController::class, 'storeAbsensiBatch']);
            Route::get('/jurnal', [GuruApiController::class, 'getJurnal']);
            Route::post('/jurnal', [GuruApiController::class, 'storeJurnal']);
            Route::delete('/jurnal/{id}', [GuruApiController::class, 'deleteJurnal']);
            Route::get('/tp', [GuruApiController::class, 'getTp']);
            Route::post('/tp', [GuruApiController::class, 'storeTp']);
            Route::delete('/tp/{id}', [GuruApiController::class, 'deleteTp']);
            Route::get('/nilai', [GuruApiController::class, 'getNilai']);
            Route::post('/nilai/batch', [GuruApiController::class, 'storeNilaiBatch']);
            Route::post('/nilai/clear-kolom', [GuruApiController::class, 'clearNilaiTpKolom']);
            Route::post('/nilai/swap-tp', [GuruApiController::class, 'swapOrMoveNilaiTp']);
            Route::get('/materi', [GuruApiController::class, 'getMateri']);
            Route::post('/materi/{id}/toggle-status', [GuruApiController::class, 'toggleMateriStatus']);
            
            // CBT API
            Route::get('/cbt/bank-soal', [GuruApiController::class, 'getCbtBankSoal']);
            Route::post('/cbt/bank-soal', [GuruApiController::class, 'storeCbtBankSoal']);
            Route::delete('/cbt/bank-soal/{id}', [GuruApiController::class, 'deleteCbtBankSoal']);
            Route::post('/cbt/soal', [GuruApiController::class, 'storeCbtSoal']);
            Route::delete('/cbt/soal/{id}', [GuruApiController::class, 'deleteCbtSoal']);
            Route::post('/cbt/soal/update-all-bobot', [GuruApiController::class, 'updateAllCbtBobot']);
            Route::post('/cbt/soal/import-bulk', [GuruApiController::class, 'importBulkCbtSoal']);
            Route::get('/cbt/jadwal', [GuruApiController::class, 'getCbtJadwal']);
            Route::post('/cbt/jadwal', [GuruApiController::class, 'storeCbtJadwal']);
            Route::delete('/cbt/jadwal/{id}', [GuruApiController::class, 'deleteCbtJadwal']);
            Route::patch('/cbt/jadwal/{id}/status', [GuruApiController::class, 'toggleCbtJadwal']);
            Route::get('/cbt/nilai', [GuruApiController::class, 'getCbtNilai']);
            Route::get('/cbt/jadwal/{id}/monitoring', [GuruApiController::class, 'getMonitoringData']);
            Route::post('/cbt/sesi/{id}/reset', [GuruApiController::class, 'resetSesiSiswa']);
            Route::post('/cbt/sesi/{id}/kunci', [GuruApiController::class, 'kunciSesiSiswa']);
            Route::post('/cbt/sesi/{id}/buka-kunci', [GuruApiController::class, 'bukaKunciSesiSiswa']);
            Route::delete('/cbt/sesi/{id}', [GuruApiController::class, 'deleteSesiSiswa']);
            Route::post('/cbt/transfer-nilai-tp', [GuruApiController::class, 'transferCbtToNilaiTp']);
        });

        // 4. Wali Kelas API Endpoints
        Route::prefix('wali-kelas')->middleware('role:wali_kelas|admin')->group(function () {
            Route::get('/siswa', [WaliKelasApiController::class, 'getSiswa']);
            Route::get('/ledger-nilai', [WaliKelasApiController::class, 'getLedgerNilai']);
            Route::get('/kasus', [WaliKelasApiController::class, 'getKasus']);
            Route::post('/kasus', [WaliKelasApiController::class, 'storeKasus']);
            Route::delete('/kasus/{id}', [WaliKelasApiController::class, 'deleteKasus']);
        });

        // 5. Siswa API Endpoints
        Route::prefix('siswa')->middleware('role:murid|admin')->group(function () {
            Route::get('/materi', [SiswaApiController::class, 'getMateri']);
            Route::get('/nilai-tp', [SiswaApiController::class, 'getNilai']);
            Route::get('/jadwal', [SiswaApiController::class, 'getJadwal']);
            Route::get('/tugas', [SiswaApiController::class, 'getTugas']);
            Route::post('/tugas/submit', [SiswaApiController::class, 'submitTugas']);
            Route::get('/cbt/daftar-siswa', [SiswaApiController::class, 'getDaftarSiswa']);
            Route::get('/cbt/jadwal', [SiswaApiController::class, 'getCbtJadwals']);
            Route::get('/cbt/nilai', [SiswaApiController::class, 'getCbtNilais']);
            Route::post('/cbt/start', [SiswaApiController::class, 'startCbtExam']);
            Route::post('/cbt/submit', [SiswaApiController::class, 'submitCbtExam']);
        });
    });
});
