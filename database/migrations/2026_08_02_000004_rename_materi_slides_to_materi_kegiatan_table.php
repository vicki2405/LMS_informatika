<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evolve materi_slides into flexible learning phases (materi_kegiatan).
     */
    public function up(): void
    {
        Schema::rename('materi_slides', 'materi_kegiatan');

        Schema::table('materi_kegiatan', function (Blueprint $table) {
            $table->string('label')->nullable()->after('urutan');
            $table->string('file_path')->nullable()->after('konten');
            $table->text('file_url')->nullable()->after('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materi_kegiatan', function (Blueprint $table) {
            $table->dropColumn(['label', 'file_path', 'file_url']);
        });

        Schema::rename('materi_kegiatan', 'materi_slides');
    }
};
