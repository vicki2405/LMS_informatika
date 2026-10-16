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
        Schema::table('siswas', function (Blueprint $table) {
            $table->index('nis');
            $table->index('nama');
        });

        Schema::table('kelases', function (Blueprint $table) {
            $table->index('nama_kelas');
        });

        Schema::table('absensis', function (Blueprint $table) {
            $table->index('tanggal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('siswas', function (Blueprint $table) {
            $table->dropIndex(['nis']);
            $table->dropIndex(['nama']);
        });

        Schema::table('kelases', function (Blueprint $table) {
            $table->dropIndex(['nama_kelas']);
        });

        Schema::table('absensis', function (Blueprint $table) {
            $table->dropIndex(['tanggal']);
        });
    }
};
