<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Siswa extends Model
{
    protected $fillable = ['nama', 'nis', 'jenis_kelamin', 'kelas_id', 'telepon', 'status', 'tanggal_status'];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}
