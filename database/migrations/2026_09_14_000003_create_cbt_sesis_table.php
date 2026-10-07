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
        Schema::create('cbt_sesis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_id')->constrained('cbt_jadwals')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswas')->onDelete('cascade');
            $table->enum('status', ['menunggu', 'mengerjakan', 'selesai', 'terkunci'])->default('menunggu');
            $table->string('token_entered', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->integer('soal_terjawab')->default(0);
            $table->integer('jumlah_pelanggaran')->default(0); // Deteksi keluar tab / pindah jendela / AI
            $table->integer('sisa_waktu_detik')->nullable();
            $table->timestamp('waktu_gabung')->nullable();
            $table->timestamp('waktu_mulai')->nullable();
            $table->timestamp('waktu_selesai')->nullable();
            $table->timestamp('last_heartbeat')->nullable();
            $table->timestamps();

            $table->unique(['jadwal_id', 'siswa_id']);
            $table->index(['jadwal_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_sesis');
    }
};
