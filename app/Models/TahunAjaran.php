<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TahunAjaran extends Model
{
    protected $fillable = ['kode', 'is_aktif'];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    public static function getActive(): ?self
    {
        return self::where('is_aktif', true)->first();
    }
}
