<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Master Tahun Ajaran
        Schema::create('tahun_ajarans', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique(); // e.g. 2025/2026
            $table->boolean('is_aktif')->default(false);
            $table->timestamps();
        });

        // 2. Master Jurusan
        Schema::create('jurusans', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique(); // e.g. RPL
            $table->string('nama'); // e.g. Rekayasa Perangkat Lunak
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        // 3. Master Kelas
        Schema::create('kelases', function (Blueprint $table) {
            $table->id();
            $table->enum('tingkat', ['X', 'XI', 'XII'])->default('X');
            $table->string('nama_kelas'); // e.g. X RPL 1
            $table->foreignId('jurusan_id')->nullable()->constrained('jurusans')->nullOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            $table->integer('jumlah_siswa')->default(0);
            $table->timestamps();
        });

        // 4. Data Siswa
        Schema::create('siswas', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan'])->default('Laki-laki');
            $table->foreignId('kelas_id')->nullable()->constrained('kelases')->nullOnDelete();
            $table->string('telepon')->nullable();
            $table->timestamps();
        });

        // 5. Jadwal Mengajar Guru
        Schema::create('jadwals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->cascadeOnDelete();
            $table->string('hari'); // Senin, Selasa, etc.
            $table->integer('jam_ke'); // 1, 2, 3...
            $table->string('waktu')->nullable(); // 07.00 - 07.45
            $table->foreignId('kelas_id')->constrained('kelases')->cascadeOnDelete();
            $table->string('mata_pelajaran');
            $table->string('ruangan')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        // 6. Absensi Siswa
        Schema::create('absensis', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('kelas_id')->constrained('kelases')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->enum('status', ['Hadir', 'Sakit', 'Izin', 'Alpa'])->default('Hadir');
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['tanggal', 'siswa_id']);
        });

        // 7. Jurnal Mengajar Guru
        Schema::create('jurnals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('jam_ke');
            $table->foreignId('kelas_id')->constrained('kelases')->cascadeOnDelete();
            $table->string('mata_pelajaran');
            $table->string('materi');
            $table->text('uraian')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        // 8. Tujuan Pembelajaran (TP Kurikulum Merdeka)
        Schema::create('tujuan_pembelajarans', function (Blueprint $table) {
            $table->id();
            $table->string('kode'); // TP-01
            $table->string('judul');
            $table->enum('jenis', ['Formatif', 'Sumatif'])->default('Formatif');
            $table->integer('kktp')->default(75);
            $table->enum('semester', ['Ganjil', 'Genap'])->default('Ganjil');
            $table->foreignId('tahun_ajaran_id')->nullable()->constrained('tahun_ajarans')->nullOnDelete();
            $table->timestamps();
        });

        // 9. Nilai TP Siswa
        Schema::create('nilai_tps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tp_id')->constrained('tujuan_pembelajarans')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->float('nilai')->nullable();
            $table->timestamps();

            $table->unique(['tp_id', 'siswa_id']);
        });

        // 10. Materi Pembelajaran per TP
        Schema::create('materis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tp_id')->nullable()->constrained('tujuan_pembelajarans')->nullOnDelete();
            $table->string('judul');
            $table->string('tipe'); // Dokumen PDF/PPT, Video Tutorial, Modul Teks, Tugas Praktik
            $table->text('file_url')->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_published')->default(true);
            $table->enum('semester', ['Ganjil', 'Genap'])->default('Ganjil');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materis');
        Schema::dropIfExists('nilai_tps');
        Schema::dropIfExists('tujuan_pembelajarans');
        Schema::dropIfExists('jurnals');
        Schema::dropIfExists('absensis');
        Schema::dropIfExists('jadwals');
        Schema::dropIfExists('siswas');
        Schema::dropIfExists('kelases');
        Schema::dropIfExists('jurusans');
        Schema::dropIfExists('tahun_ajarans');
    }
};
