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
        Schema::table('cbt_soals', function (Blueprint $table) {
            $table->string('media_soal')->nullable()->after('pertanyaan');
            $table->string('tipe_media_soal', 20)->nullable()->after('media_soal'); // 'image' | 'audio'
            $table->string('gambar_opsi_a')->nullable()->after('opsi_a');
            $table->string('gambar_opsi_b')->nullable()->after('opsi_b');
            $table->string('gambar_opsi_c')->nullable()->after('opsi_c');
            $table->string('gambar_opsi_d')->nullable()->after('opsi_d');
            $table->string('gambar_opsi_e')->nullable()->after('opsi_e');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cbt_soals', function (Blueprint $table) {
            $table->dropColumn([
                'media_soal',
                'tipe_media_soal',
                'gambar_opsi_a',
                'gambar_opsi_b',
                'gambar_opsi_c',
                'gambar_opsi_d',
                'gambar_opsi_e',
            ]);
        });
    }
};
