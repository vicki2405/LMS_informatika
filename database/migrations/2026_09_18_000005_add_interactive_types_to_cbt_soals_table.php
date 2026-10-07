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
            $table->string('tipe_soal', 30)->default('pilihan_ganda')->after('nomor_urut');
            $table->json('data_interaktif')->nullable()->after('kunci_jawaban');
            $table->longText('stimulus_teks')->nullable()->after('pertanyaan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cbt_soals', function (Blueprint $table) {
            $table->dropColumn(['tipe_soal', 'data_interaktif', 'stimulus_teks']);
        });
    }
};
