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
        Schema::create('kasus_siswas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('nama_kasus');
            $table->enum('kategori', ['Ringan', 'Sedang', 'Berat'])->default('Ringan');
            $table->text('tindakan')->nullable();
            $table->enum('status', ['Dalam Pemantauan', 'Selesai'])->default('Dalam Pemantauan');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kasus_siswas');
    }
};
