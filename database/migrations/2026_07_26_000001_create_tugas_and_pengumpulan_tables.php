<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelases')->cascadeOnDelete();
            $table->string('mata_pelajaran');
            $table->string('judul');
            $table->text('instruksi')->nullable();
            $table->dateTime('tenggat');
            $table->timestamps();
        });

        Schema::create('pengumpulan_tugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tugas_id')->constrained('tugas')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->enum('status', ['Belum Dikerjakan', 'Sudah Dikirim', 'Dinilai'])->default('Belum Dikerjakan');
            $table->string('file_url')->nullable();
            $table->text('catatan_siswa')->nullable();
            $table->integer('nilai')->nullable();
            $table->dateTime('dikirim_pada')->nullable();
            $table->timestamps();

            $table->unique(['tugas_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumpulan_tugas');
        Schema::dropIfExists('tugas');
    }
};
