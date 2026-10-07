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
            if (! Schema::hasColumn('cbt_jadwals', 'mode_ujian')) {
                $table->enum('mode_ujian', ['formal', 'game'])->default('formal')->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cbt_jadwals', function (Blueprint $table) {
            if (Schema::hasColumn('cbt_jadwals', 'mode_ujian')) {
                $table->dropColumn('mode_ujian');
            }
        });
    }
};
