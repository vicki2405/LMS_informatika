<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    protected $fillable = ['guru_id', 'hari', 'jam_ke', 'waktu', 'kelas_id', 'mata_pelajaran', 'ruangan', 'keterangan'];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'guru_id');
    }
}
