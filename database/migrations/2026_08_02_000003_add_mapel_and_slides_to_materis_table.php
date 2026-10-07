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
            $table->foreignId('mapel_id')->nullable()->after('kelas_id')->constrained('mata_pelajarans')->nullOnDelete();
            $table->string('file_path')->nullable()->after('file_url');
        });

        Schema::create('materi_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materi_id')->constrained('materis')->cascadeOnDelete();
            $table->unsignedInteger('urutan')->default(0);
            $table->string('judul')->nullable();
            $table->text('konten')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materi_slides');

        Schema::table('materis', function (Blueprint $table) {
            $table->dropForeign(['mapel_id']);
            $table->dropColumn('mapel_id');
            $table->dropColumn('file_path');
        });
    }
};
