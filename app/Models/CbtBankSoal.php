<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtBankSoal extends Model
{
    use HasFactory;

    protected $table = 'cbt_bank_soals';

    protected $fillable = [
        'judul',
        'kode',
        'jurusan_id',
        'kelas_id',
        'mata_pelajaran_id',
        'guru_id',
        'deskripsi',
        'jumlah_pilihan',
    ];

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function soals(): HasMany
    {
        return $this->hasMany(CbtSoal::class, 'bank_soal_id')->orderBy('nomor_urut', 'asc');
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(CbtJadwal::class, 'bank_soal_id');
    }

    public function nilais(): HasMany
    {
        return $this->hasMany(CbtNilai::class, 'bank_soal_id');
    }
}
