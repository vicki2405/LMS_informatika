<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NilaiTp extends Model
{
    protected $table = 'nilai_tps';
    protected $fillable = ['tp_id', 'siswa_id', 'nilai', 'catatan', 'guru_id'];

    public function tp()
    {
        return $this->belongsTo(TujuanPembelajaran::class, 'tp_id');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'guru_id');
    }
}
