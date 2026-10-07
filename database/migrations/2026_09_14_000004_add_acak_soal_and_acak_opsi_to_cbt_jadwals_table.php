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
        Schema::table('cbt_jadwals', function (Blueprint $table) {
            $table->boolean('acak_soal')->default(true)->after('token');
            $table->boolean('acak_opsi')->default(true)->after('acak_soal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cbt_jadwals', function (Blueprint $table) {
            $table->dropColumn(['acak_soal', 'acak_opsi']);
        });
    }
};
