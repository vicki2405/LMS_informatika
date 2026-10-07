<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['admin', 'wali_kelas']) ?? false;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'string', 'in:L,P'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'nisn' => ['nullable', 'string', 'max:20'],
            'nis' => ['nullable', 'string', 'max:20'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'in:Aktif,Pindah,Keluar'],
            'tanggal_status' => ['nullable', 'date'],
        ];
    }
}
