<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MateriRead extends Model
{
    protected $table = 'materi_reads';

    protected $fillable = [
        'materi_id',
        'siswa_id',
        'status',
        'progress_persen',
        'opened_at',
        'terakhir_dibaca_at',
        'completed_at',
    ];

    protected $casts = [
        'progress_persen' => 'integer',
        'opened_at' => 'datetime',
        'terakhir_dibaca_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function materi(): BelongsTo
    {
        return $this->belongsTo(Materi::class, 'materi_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
