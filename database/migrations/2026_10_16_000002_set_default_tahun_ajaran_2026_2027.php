<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure 2026/2027 exists and is active
        $targetTa = DB::table('tahun_ajarans')->where('kode', '2026/2027')->first();
        if (! $targetTa) {
            $targetId = DB::table('tahun_ajarans')->insertGetId([
                'kode' => '2026/2027',
                'is_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $targetId = $targetTa->id;
            DB::table('tahun_ajarans')->where('id', $targetId)->update([
                'is_aktif' => true,
                'updated_at' => now(),
            ]);
        }

        // 2. Set all other years to inactive
        DB::table('tahun_ajarans')->where('id', '!=', $targetId)->update([
            'is_aktif' => false,
            'updated_at' => now(),
        ]);

        // 3. Migrate all existing Tujuan Pembelajaran to point to 2026/2027
        DB::table('tujuan_pembelajarans')->update([
            'tahun_ajaran_id' => $targetId,
        ]);

        // 4. Migrate any past Jurnal Mengajar records with 2025/2026 or old labels to 2026/2027
        DB::table('jurnals')
            ->where('tahun_pelajaran', 'LIKE', '%2025/2026%')
            ->orWhereNull('tahun_pelajaran')
            ->orWhere('tahun_pelajaran', '')
            ->update([
                'tahun_pelajaran' => 'Tahun Pelajaran 2026/2027',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $oldTa = DB::table('tahun_ajarans')->where('kode', '2025/2026')->first();
        if ($oldTa) {
            DB::table('tahun_ajarans')->update(['is_aktif' => false]);
            DB::table('tahun_ajarans')->where('id', $oldTa->id)->update(['is_aktif' => true]);
        }
    }
};
