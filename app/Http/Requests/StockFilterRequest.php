<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source' => $this->normalizeArrayInput('source'),
            'pilar' => $this->normalizeArrayInput('pilar'),
            'cabang' => $this->normalizeArrayInput('cabang'),
            'kelompok_ed' => $this->normalizeArrayInput('kelompok_ed'),
        ]);
    }

    public function rules(): array
    {
        return [
            'source'      => ['nullable', 'array'],
            'source.*'    => ['string', 'distinct', 'in:GPJ,AAM,KFTD,METD'],
            'pilar'       => ['nullable', 'array'],
            'pilar.*'     => ['string', 'distinct', 'max:50'],
            'cabang'      => ['nullable', 'array'],
            'cabang.*'    => ['string', 'distinct', 'max:100'],
            'kelompok_ed' => ['nullable', 'array'],
            'kelompok_ed.*' => ['string', 'distinct', 'in:sudah_ed,1_3,4_7,8_12,13_17,gt_18'],
        ];
    }

    private function normalizeArrayInput(string $key): ?array
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return collect(is_array($value) ? $value : [$value])
            ->filter(fn($item) => $item !== null && $item !== '')
            ->map(fn($item) => (string) $item)
            ->unique()
            ->sort()
            ->values()
            ->all() ?: null;
    }
}
