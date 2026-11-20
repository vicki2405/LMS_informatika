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
        Schema::table('siswas', function (Blueprint $table) {
            $table->string('device_id')->nullable()->index()->after('telepon');
            $table->string('device_name')->nullable()->after('device_id');
            $table->timestamp('device_locked_at')->nullable()->after('device_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('siswas', function (Blueprint $table) {
            $table->dropIndex(['device_id']);
            $table->dropColumn(['device_id', 'device_name', 'device_locked_at']);
        });
    }
};
