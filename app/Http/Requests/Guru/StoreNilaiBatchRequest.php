<?php

namespace App\Http\Requests\Guru;

use Illuminate\Foundation\Http\FormRequest;

class StoreNilaiBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['guru', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'tp_id' => ['required', 'integer', 'exists:tujuan_pembelajarans,id'],
            'nilai' => ['required', 'array'],
            'nilai.*.siswa_id' => ['required', 'integer', 'exists:siswas,id'],
            'nilai.*.skor' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
