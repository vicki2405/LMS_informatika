<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswas', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('users')
            ->whereNotNull('kelas_id')
            ->orderBy('id')
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $siswa = DB::table('siswas')
                        ->whereNull('user_id')
                        ->where('kelas_id', $user->kelas_id)
                        ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower(trim($user->name))])
                        ->first();

                    if ($siswa) {
                        DB::table('siswas')
                            ->where('id', $siswa->id)
                            ->update(['user_id' => $user->id]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('siswas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
