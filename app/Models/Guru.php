<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Guru extends Model
{
    protected $table = 'gurus';

    public function setMataPelajaranAttribute($value): void
    {
        $this->attributes['mapel_utama'] = $value;
    }

    public function getMataPelajaranAttribute(): ?string
    {
        return $this->attributes['mapel_utama'] ?? null;
    }

    
    protected $fillable = [
        'nip',
        'nama',
        'telepon',
        'mapel_utama',
        'mata_pelajaran',
        'user_id',
    ];

    /**
     * Get the user account associated with the guru.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the kelas where this guru is wali kelas.
     */
    public function kelasBinaan(): HasOne
    {
        return $this->hasOne(Kelas::class, 'guru_id');
    }

    /**
     * Get all kelas that this guru teaches (via guru_kelas pivot).
     */
    public function kelasAmpuan(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'guru_kelas', 'guru_id', 'kelas_id')
                    ->withTimestamps();
    }

    /**
     * Get the schedules associated with the guru.
     */
    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'guru_id');
    }

    /**
     * Get the journals associated with the guru.
     */
    public function jurnals(): HasMany
    {
        return $this->hasMany(Jurnal::class, 'guru_id');
    }
}

