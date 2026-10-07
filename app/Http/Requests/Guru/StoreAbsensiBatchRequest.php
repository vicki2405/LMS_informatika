<?php

namespace App\Http\Requests\Guru;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbsensiBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['guru', 'wali_kelas', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'absensi' => ['required', 'array'],
            'absensi.*.siswa_id' => ['required', 'integer', 'exists:siswas,id'],
            'absensi.*.status' => ['required', 'string', 'in:Hadir,Sakit,Izin,Alpa'],
            'absensi.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
