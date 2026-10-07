<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MateriKegiatan extends Model
{
    protected $table = 'materi_kegiatan';

    protected $fillable = [
        'materi_id',
        'urutan',
        'label',
        'layout',
        'judul',
        'konten',
        'file_path',
        'file_url',
    ];

    public function materi(): BelongsTo
    {
        return $this->belongsTo(Materi::class, 'materi_id');
    }
}
