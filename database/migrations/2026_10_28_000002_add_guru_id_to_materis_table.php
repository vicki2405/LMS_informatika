<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materis', function (Blueprint $table) {
            $table->foreignId('guru_id')
                ->nullable()
                ->after('id')
                ->constrained('gurus')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('materis', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guru_id');
        });
    }
};
