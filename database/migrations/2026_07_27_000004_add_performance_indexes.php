<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add performance indexes.
     */
    public function up(): void
    {
        Schema::table('absensis', function (Blueprint $table) {
            $table->index(['kelas_id', 'tanggal']);
            $table->index('siswa_id');
        });

        Schema::table('nilai_tps', function (Blueprint $table) {
            $table->index(['siswa_id', 'tp_id']);
        });

        Schema::table('jurnals', function (Blueprint $table) {
            $table->index(['guru_id', 'tanggal']);
            $table->index('kelas_id');
        });

        Schema::table('kelases', function (Blueprint $table) {
            $table->index('guru_id');
            $table->index('jurusan_id');
        });

        Schema::table('siswas', function (Blueprint $table) {
            $table->index('kelas_id');
        });

        Schema::table('jadwals', function (Blueprint $table) {
            $table->index('kelas_id');
            $table->index('guru_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('absensis', function (Blueprint $table) {
            $table->dropIndex(['kelas_id', 'tanggal']);
            $table->dropIndex(['siswa_id']);
        });

        Schema::table('nilai_tps', function (Blueprint $table) {
            $table->dropIndex(['siswa_id', 'tp_id']);
        });

        Schema::table('jurnals', function (Blueprint $table) {
            $table->dropIndex(['guru_id', 'tanggal']);
            $table->dropIndex(['kelas_id']);
        });

        Schema::table('kelases', function (Blueprint $table) {
            $table->dropIndex(['guru_id']);
            $table->dropIndex(['jurusan_id']);
        });

        Schema::table('siswas', function (Blueprint $table) {
            $table->dropIndex(['kelas_id']);
        });

        Schema::table('jadwals', function (Blueprint $table) {
            $table->dropIndex(['kelas_id']);
            $table->dropIndex(['guru_id']);
        });
    }
};
