<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $bulan = $this->input('bulan');
        $tahun = $this->input('tahun');

        if (is_array($bulan)) {
            $bulan = collect($bulan)
                ->filter(fn($value) => $value !== null && $value !== '')
                ->map(fn($value) => (int) $value)
                ->unique()
                ->values()
                ->all();
        } else {
            $bulan = $bulan === null || $bulan === '' ? null : (int) $bulan;
        }

        $this->merge([
            'bulan' => $bulan === [] ? null : $bulan,
            'tahun' => $tahun === null || $tahun === '' ? null : (int) $tahun,
        ]);
    }

    public function rules(): array
    {
        return [
            'bulan'       => ['nullable', function ($attribute, $value, $fail) {
                $values = is_array($value) ? $value : [$value];

                foreach ($values as $month) {
                    if (!is_int($month) || $month < 1 || $month > 12) {
                        $fail('Bulan harus bernilai 1 sampai 12.');
                        return;
                    }
                }
            }],
            'tahun'       => 'nullable|integer|min:2020|max:' . date('Y'),
            'level'       => 'nullable|in:GM,RM,SAM,AM,MR',
            'pilar'       => 'nullable|in:ETHICAL,OGB,GENERIK,OTC',
            'distributor' => 'nullable|in:KFTD,AAM,METD',
            'coverage_id' => 'nullable|string',
            'coverage_nik' => 'nullable|string',
            'outlet_id' => 'nullable|string',
        ];
    }

    // -------------------------------------------------------
    // Helpers — dipakai di Service agar tidak repeat
    // -------------------------------------------------------
    public function bulan(): string
    {
        $bulan = $this->get('bulan', date('m'));

        if (is_array($bulan)) {
            $bulan = $bulan[0] ?? date('m');
        }

        return str_pad($bulan, 2, '0', STR_PAD_LEFT);
    }

    public function bulanList(): array
    {
        $bulan = $this->get('bulan', date('m'));

        if (!is_array($bulan)) {
            $bulan = [$bulan];
        }

        return collect($bulan)
            ->map(fn($value) => (int) $value)
            ->filter(fn($value) => $value >= 1 && $value <= 12)
            ->unique()
            ->values()
            ->all();
    }

    public function tahun(): string
    {
        // return $this->get('tahun', date('Y'));
        return $this->input('tahun') ?? (string) date('Y');
    }

    public function startDate(): string
    {
        return "{$this->tahun()}-{$this->bulan()}-01";
    }

    public function endDate(): string
    {
        return date('Y-m-t', strtotime($this->startDate()));
    }

    public function prevStartDate(): string
    {
        return date('Y-m-01', strtotime($this->startDate() . ' -1 month'));
    }

    public function prevEndDate(): string
    {
        return date('Y-m-t', strtotime($this->prevStartDate()));
    }
}
