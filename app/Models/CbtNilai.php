<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtNilai extends Model
{
    use HasFactory;

    protected $table = 'cbt_nilais';

    protected $fillable = [
        'jadwal_id',
        'siswa_id',
        'bank_soal_id',
        'jumlah_soal',
        'jumlah_benar',
        'jumlah_salah',
        'nilai',
        'status',
        'waktu_mulai',
        'waktu_selesai',
    ];

    protected $casts = [
        'jumlah_soal' => 'integer',
        'jumlah_benar' => 'integer',
        'jumlah_salah' => 'integer',
        'nilai' => 'float',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(CbtJadwal::class, 'jadwal_id')->withTrashed();
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function bankSoal(): BelongsTo
    {
        return $this->belongsTo(CbtBankSoal::class, 'bank_soal_id');
    }
}
