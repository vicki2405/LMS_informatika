<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materi_reads', function (Blueprint $table) {
            if (! Schema::hasColumn('materi_reads', 'progress_persen')) {
                $table->integer('progress_persen')->default(0)->after('status');
            }
            if (! Schema::hasColumn('materi_reads', 'terakhir_dibaca_at')) {
                $table->dateTime('terakhir_dibaca_at')->nullable()->after('opened_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('materi_reads', function (Blueprint $table) {
            if (Schema::hasColumn('materi_reads', 'progress_persen')) {
                $table->dropColumn('progress_persen');
            }
            if (Schema::hasColumn('materi_reads', 'terakhir_dibaca_at')) {
                $table->dropColumn('terakhir_dibaca_at');
            }
        });
    }
};
