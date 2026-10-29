<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TujuanPembelajaran extends Model
{
    protected $table = 'tujuan_pembelajarans';
    protected $fillable = ['kode', 'mapel', 'judul', 'jenis', 'kktp', 'semester', 'tahun_ajaran_id'];

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function nilaiTps()
    {
        return $this->hasMany(NilaiTp::class, 'tp_id');
    }
}
