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
        Schema::table('materis', function (Blueprint $table) {
            if (! Schema::hasColumn('materis', 'cbt_jadwal_id')) {
                $table->foreignId('cbt_jadwal_id')->nullable()->after('mapel_id')->constrained('cbt_jadwals')->nullOnDelete();
            }
            if (! Schema::hasColumn('materis', 'theme')) {
                $table->string('theme', 30)->default('dark')->after('tipe');
            }
        });

        Schema::table('materi_kegiatan', function (Blueprint $table) {
            if (! Schema::hasColumn('materi_kegiatan', 'layout')) {
                $table->string('layout', 30)->default('standard')->after('label');
            }
        });

        if (! Schema::hasTable('materi_reads')) {
            Schema::create('materi_reads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('materi_id')->constrained('materis')->cascadeOnDelete();
                $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
                $table->string('status', 30)->default('sedang_membaca');
                $table->dateTime('opened_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['materi_id', 'siswa_id']);
                $table->index(['materi_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materi_reads');

        Schema::table('materi_kegiatan', function (Blueprint $table) {
            if (Schema::hasColumn('materi_kegiatan', 'layout')) {
                $table->dropColumn('layout');
            }
        });

        Schema::table('materis', function (Blueprint $table) {
            if (Schema::hasColumn('materis', 'cbt_jadwal_id')) {
                $table->dropForeign(['cbt_jadwal_id']);
                $table->dropColumn('cbt_jadwal_id');
            }
            if (Schema::hasColumn('materis', 'theme')) {
                $table->dropColumn('theme');
            }
        });
    }
};
