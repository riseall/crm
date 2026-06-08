<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source'      => ['nullable', 'string', 'in:GPJ,AAM,KFTD,METD'],
            'pilar'       => ['nullable', 'string', 'max:50'],
            'cabang'      => ['nullable', 'string', 'max:100'],
            'kelompok_ed' => ['nullable', 'string', 'in:sudah_ed,1_3,4_7,8_12,13_17,gt_18'],
        ];
    }
}
