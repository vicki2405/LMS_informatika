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
        Schema::table('users', function (Blueprint $table) {
            $table->string('active_session_id')->nullable()->index()->after('remember_token');
            $table->string('active_device_token')->nullable()->after('active_session_id');
            $table->string('active_device_ip', 45)->nullable()->after('active_device_token');
            $table->string('active_device_agent', 255)->nullable()->after('active_device_ip');
            $table->timestamp('last_activity_at')->nullable()->after('active_device_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['active_session_id']);
            $table->dropColumn([
                'active_session_id',
                'active_device_token',
                'active_device_ip',
                'active_device_agent',
                'last_activity_at',
            ]);
        });
    }
};
