<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtSesi extends Model
{
    use HasFactory;

    protected $table = 'cbt_sesis';

    protected $fillable = [
        'jadwal_id',
        'siswa_id',
        'status',
        'token_entered',
        'ip_address',
        'user_agent',
        'soal_terjawab',
        'jumlah_pelanggaran',
        'sisa_waktu_detik',
        'waktu_gabung',
        'waktu_mulai',
        'waktu_selesai',
        'last_heartbeat',
    ];

    protected $casts = [
        'soal_terjawab' => 'integer',
        'jumlah_pelanggaran' => 'integer',
        'sisa_waktu_detik' => 'integer',
        'waktu_gabung' => 'datetime',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'last_heartbeat' => 'datetime',
    ];

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(CbtJadwal::class, 'jadwal_id')->withTrashed();
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
