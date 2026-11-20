<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiswaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'nisn' => $this->nisn,
            'nis' => $this->nis ?? ('100' . $this->id),
            'jenisKelamin' => $this->jenis_kelamin,
            'telepon' => $this->telepon,
            'kelasId' => $this->kelas_id,
            'status' => $this->status ?? 'Aktif',
            'tanggal_status' => $this->tanggal_status,
            'deviceId' => $this->device_id,
            'deviceName' => $this->device_name,
            'deviceLockedAt' => $this->device_locked_at?->format('d/m/Y H:i'),
            'isDeviceLocked' => ! empty($this->device_id),
            'kelas' => $this->whenLoaded('kelas', function () {
                return [
                    'id' => $this->kelas->id,
                    'namaKelas' => $this->kelas->nama_kelas,
                ];
            }),
        ];
    }
}
