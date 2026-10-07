<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtSoal extends Model
{
    use HasFactory;

    protected $table = 'cbt_soals';

    protected $fillable = [
        'bank_soal_id',
        'nomor_urut',
        'pertanyaan',
        'tipe_soal',
        'stimulus_teks',
        'data_interaktif',
        'opsi_a',
        'opsi_b',
        'opsi_c',
        'opsi_d',
        'opsi_e',
        'kunci_jawaban',
        'bobot',
        'pembahasan',
        'media_soal',
        'tipe_media_soal',
        'posisi_media_soal',
        'gambar_opsi_a',
        'gambar_opsi_b',
        'gambar_opsi_c',
        'gambar_opsi_d',
        'gambar_opsi_e',
    ];

    protected $casts = [
        'nomor_urut' => 'integer',
        'bobot' => 'float',
        'data_interaktif' => 'array',
    ];

    public function bankSoal(): BelongsTo
    {
        return $this->belongsTo(CbtBankSoal::class, 'bank_soal_id');
    }
}
