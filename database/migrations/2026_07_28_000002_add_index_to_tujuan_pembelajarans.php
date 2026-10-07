<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes for mapel filtering on tujuan_pembelajarans.
     */
    public function up(): void
    {
        Schema::table('tujuan_pembelajarans', function (Blueprint $table) {
            $table->index('mapel');
            $table->index('semester');
            $table->index(['mapel', 'semester']); // Composite index for combined filter
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tujuan_pembelajarans', function (Blueprint $table) {
            $table->dropIndex(['mapel']);
            $table->dropIndex(['semester']);
            $table->dropIndex(['mapel', 'semester']);
        });
    }
};
