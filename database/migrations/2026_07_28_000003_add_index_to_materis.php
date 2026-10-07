<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materis', function (Blueprint $table) {
            $table->index('is_published');
            $table->index(['kelas_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::table('materis', function (Blueprint $table) {
            $table->dropIndex(['is_published']);
            $table->dropIndex(['kelas_id', 'is_published']);
        });
    }
};
