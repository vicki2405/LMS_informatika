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
        Schema::table('kasus_siswas', function (Blueprint $table) {
            $table->integer('poin')->default(5)->after('kategori');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kasus_siswas', function (Blueprint $table) {
            $table->dropColumn('poin');
        });
    }
};
