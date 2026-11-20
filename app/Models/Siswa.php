<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Siswa extends Model
{
    protected $fillable = [
        'user_id', 'nis', 'nama', 'jenis_kelamin', 'kelas_id', 'telepon', 'status', 'tanggal_status',
        'device_id', 'device_name', 'device_locked_at',
    ];

    protected $casts = [
        'device_locked_at' => 'datetime',
    ];

    public function isDeviceLockedToOther(?string $deviceId): bool
    {
        return ! empty($this->device_id) && $this->device_id !== $deviceId;
    }

    public function lockToDevice(string $deviceId, ?string $deviceName = null): void
    {
        $this->update([
            'device_id' => $deviceId,
            'device_name' => $deviceName ?: 'Perangkat Siswa',
            'device_locked_at' => now(),
        ]);
    }

    public function unlockDevice(): void
    {
        $this->update([
            'device_id' => null,
            'device_name' => null,
            'device_locked_at' => null,
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}
