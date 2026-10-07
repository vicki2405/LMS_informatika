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
        Schema::create('kelompok_mapels', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        // Make kode in mata_pelajarans nullable if not already
        if (Schema::hasTable('mata_pelajarans')) {
            Schema::table('mata_pelajarans', function (Blueprint $table) {
                $table->string('kode')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelompok_mapels');
    }
};
