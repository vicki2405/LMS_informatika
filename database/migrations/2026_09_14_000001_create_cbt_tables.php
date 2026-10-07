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
        // 1. Bank Soal CBT
        Schema::create('cbt_bank_soals', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->string('kode')->nullable()->index();
            $table->foreignId('jurusan_id')->nullable()->constrained('jurusans')->nullOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelases')->nullOnDelete();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajarans')->nullOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            $table->text('deskripsi')->nullable();
            $table->integer('jumlah_pilihan')->default(5); // A, B, C, D, E
            $table->timestamps();
        });

        // 2. Butir Soal CBT (Pilihan Ganda A, B, C, D, E)
        Schema::create('cbt_soals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_soal_id')->constrained('cbt_bank_soals')->cascadeOnDelete();
            $table->integer('nomor_urut')->default(1);
            $table->text('pertanyaan');
            $table->text('opsi_a');
            $table->text('opsi_b');
            $table->text('opsi_c');
            $table->text('opsi_d');
            $table->text('opsi_e');
            $table->string('kunci_jawaban', 5)->default('A'); // A, B, C, D, E
            $table->integer('bobot')->default(1);
            $table->text('pembahasan')->nullable();
            $table->timestamps();

            $table->index(['bank_soal_id', 'nomor_urut']);
        });

        // 3. Jadwal Pelaksanaan CBT & Pengaturan Aktif/Nonaktif
        Schema::create('cbt_jadwals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_soal_id')->constrained('cbt_bank_soals')->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelases')->nullOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            $table->string('judul_ujian');
            $table->dateTime('waktu_mulai')->nullable();
            $table->dateTime('waktu_selesai')->nullable();
            $table->integer('durasi_menit')->default(60);
            $table->string('token', 20)->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();
        });

        // 4. Hasil & Nilai Siswa CBT
        Schema::create('cbt_nilais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_id')->constrained('cbt_jadwals')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->foreignId('bank_soal_id')->nullable()->constrained('cbt_bank_soals')->cascadeOnDelete();
            $table->integer('jumlah_soal')->default(0);
            $table->integer('jumlah_benar')->default(0);
            $table->integer('jumlah_salah')->default(0);
            $table->decimal('nilai', 5, 2)->default(0.00);
            $table->string('status')->default('Tuntas'); // Tuntas / Belum Tuntas / Selesai
            $table->dateTime('waktu_mulai')->nullable();
            $table->dateTime('waktu_selesai')->nullable();
            $table->timestamps();

            $table->index(['jadwal_id', 'siswa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_nilais');
        Schema::dropIfExists('cbt_jadwals');
        Schema::dropIfExists('cbt_soals');
        Schema::dropIfExists('cbt_bank_soals');
    }
};
