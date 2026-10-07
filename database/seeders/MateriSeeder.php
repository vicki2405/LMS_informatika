<?php

namespace Database\Seeders;

use App\Models\Materi;
use App\Models\TujuanPembelajaran;
use Illuminate\Database\Seeder;

class MateriSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tp1 = TujuanPembelajaran::query()->where('kode', '=', 'TP-01')->first();
        $tp2 = TujuanPembelajaran::query()->where('kode', '=', 'TP-02')->first();
        $tp4 = TujuanPembelajaran::query()->where('kode', '=', 'TP-04')->first();

        $materis = [
            [
                'tp_id' => $tp1?->id,
                'judul' => 'Panduan Modul Vue 3 Modular & Options vs Composition API',
                'tipe' => 'Dokumen PDF/PPT',
                'file_url' => 'https://drive.google.com/file/d/sample-modul-vue3/view',
                'deskripsi' => 'Ringkasan modul konsep dasar komponen Vue 3, pengenalan props, event emitter, dan reactive ref/reactive.',
                'is_published' => true,
                'semester' => 'Ganjil',
            ],
            [
                'tp_id' => $tp1?->id,
                'judul' => 'Video Tutorial: Live Coding Component & Props Vue 3',
                'tipe' => 'Video Tutorial',
                'file_url' => 'https://www.youtube.com/watch?v=sample-vue3-tutorial',
                'deskripsi' => 'Video langkah demi langkah membuat komponen Vue 3 modular dan passing props antar parent-child component.',
                'is_published' => true,
                'semester' => 'Ganjil',
            ],
            [
                'tp_id' => $tp2?->id,
                'judul' => 'Instruksi Tugas Praktik: Custom Event & Modal Component',
                'tipe' => 'Tugas Praktik',
                'file_url' => '',
                'deskripsi' => 'Tugas praktik mandiri: buatlah modal dialog interaktif dengan custom event emitter pada Vue 3.',
                'is_published' => true,
                'semester' => 'Ganjil',
            ],
            [
                'tp_id' => $tp4?->id,
                'judul' => 'Modul Integrasi REST API Laravel 11 & Inertia.js',
                'tipe' => 'Dokumen PDF/PPT',
                'file_url' => 'https://drive.google.com/file/d/sample-rest-api/view',
                'deskripsi' => 'Panduan teknis menghubungkan controller Laravel dengan komponen Vue via Inertia router.',
                'is_published' => true,
                'semester' => 'Genap',
            ],
        ];

        foreach ($materis as $m) {
            Materi::firstOrCreate(['judul' => $m['judul']], $m);
        }
    }
}
