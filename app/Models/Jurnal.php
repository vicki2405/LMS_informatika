<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurnal extends Model
{
    protected $fillable = ['guru_id', 'tanggal', 'jam_ke', 'kelas_id', 'mata_pelajaran', 'materi', 'uraian', 'catatan', 'tahun_pelajaran', 'semester'];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'guru_id');
    }
}
