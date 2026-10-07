<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Materi extends Model
{
    protected $fillable = [
        'tp_id',
        'kelas_id',
        'mapel_id',
        'cbt_jadwal_id',
        'judul',
        'tipe',
        'theme',
        'file_url',
        'file_path',
        'deskripsi',
        'is_published',
        'semester',
    ];

    public function tp(): BelongsTo
    {
        return $this->belongsTo(TujuanPembelajaran::class, 'tp_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mapel_id');
    }

    public function cbtJadwal(): BelongsTo
    {
        return $this->belongsTo(CbtJadwal::class, 'cbt_jadwal_id');
    }

    public function kegiatans(): HasMany
    {
        return $this->hasMany(MateriKegiatan::class, 'materi_id')->orderBy('urutan', 'asc');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MateriRead::class, 'materi_id');
    }
}
