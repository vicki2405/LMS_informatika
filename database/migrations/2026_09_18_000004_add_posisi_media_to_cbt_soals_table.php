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
            if (!Schema::hasColumn('cbt_soals', 'posisi_media_soal')) {
                $table->string('posisi_media_soal', 20)->default('bawah')->after('tipe_media_soal');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cbt_soals', function (Blueprint $table) {
            if (Schema::hasColumn('cbt_soals', 'posisi_media_soal')) {
                $table->dropColumn('posisi_media_soal');
            }
        });
    }
};
