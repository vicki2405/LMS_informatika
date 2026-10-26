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
        Schema::table('jadwals', function (Blueprint $table) {
            $table->string('jam_ke', 50)->change();
        });

        Schema::table('cbt_soals', function (Blueprint $table) {
            $table->string('kunci_jawaban', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cbt_soals', function (Blueprint $table) {
            $table->string('kunci_jawaban', 5)->change();
        });

        Schema::table('jadwals', function (Blueprint $table) {
            $table->integer('jam_ke')->change();
        });
    }
};
