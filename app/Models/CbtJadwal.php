<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CbtJadwal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cbt_jadwals';

    protected $fillable = [
        'bank_soal_id',
        'jurusan_id',
        'kelas_id',
        'guru_id',
        'judul_ujian',
        'waktu_mulai',
        'waktu_selesai',
        'durasi_menit',
        'token',
        'acak_soal',
        'acak_opsi',
        'status',
        'mode_ujian',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'durasi_menit' => 'integer',
        'acak_soal' => 'boolean',
        'acak_opsi' => 'boolean',
    ];

    public function bankSoal(): BelongsTo
    {
        return $this->belongsTo(CbtBankSoal::class, 'bank_soal_id');
    }

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function nilais(): HasMany
    {
        return $this->hasMany(CbtNilai::class, 'jadwal_id');
    }

    public function sesis(): HasMany
    {
        return $this->hasMany(CbtSesi::class, 'jadwal_id');
    }
}
