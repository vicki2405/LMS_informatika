<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Kelas extends Model
{
    protected $table = 'kelases';
    protected $fillable = ['tingkat', 'nama_kelas', 'jurusan_id', 'guru_id', 'jumlah_siswa'];

    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class);
    }

    public function waliKelas()
    {
        return $this->belongsTo(Guru::class, 'guru_id');
    }

    public function siswas()
    {
        return $this->hasMany(Siswa::class);
    }

    /**
     * Get all guru that teach this class (via guru_kelas pivot).
     */
    public function guruPengampu(): BelongsToMany
    {
        return $this->belongsToMany(Guru::class, 'guru_kelas', 'kelas_id', 'guru_id')
                    ->withTimestamps();
    }
}
