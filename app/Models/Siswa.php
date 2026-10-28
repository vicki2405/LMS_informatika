<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Siswa extends Model
{
    protected $fillable = ['user_id', 'nis', 'nama', 'jenis_kelamin', 'kelas_id', 'telepon', 'status', 'tanggal_status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}
