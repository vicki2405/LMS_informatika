<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cbt:clean-auto-tp', function () {
    $tps = \App\Models\TujuanPembelajaran::where('kode', 'like', 'TP-CBT%')
        ->orWhere('judul', 'like', 'CBT:%')
        ->get();

    $count = $tps->count();
    if ($count === 0) {
        $this->info('Tidak ditemukan Tujuan Pembelajaran (TP) otomatis dari CBT.');
        return 0;
    }

    $this->warn("Ditemukan {$count} Tujuan Pembelajaran (TP) otomatis dari CBT:");
    foreach ($tps as $tp) {
        $this->line("- [ID: {$tp->id}] {$tp->kode}: {$tp->judul} ({$tp->mapel})");
        \App\Models\NilaiTp::where('tp_id', $tp->id)->delete();
        $tp->delete();
    }

    $this->info("Berhasil membersihkan {$count} TP otomatis dan nilai terkait. Buku nilai kembali bersih dan akurat.");
    return 0;
})->purpose('Bersihkan Tujuan Pembelajaran yang otomatis dibuat dari CBT');
