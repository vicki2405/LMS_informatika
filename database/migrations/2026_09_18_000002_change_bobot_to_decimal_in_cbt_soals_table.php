<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Support decimal question weights (e.g. 2.5) in cbt_soals
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cbt_soals MODIFY bobot DECIMAL(6, 2) NOT NULL DEFAULT 1.00');
        } else {
            Schema::table('cbt_soals', function (Blueprint $table) {
                $table->decimal('bobot', 6, 2)->default(1.00)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cbt_soals MODIFY bobot INT NOT NULL DEFAULT 1');
        } else {
            Schema::table('cbt_soals', function (Blueprint $table) {
                $table->integer('bobot')->default(1)->change();
            });
        }
    }
};
