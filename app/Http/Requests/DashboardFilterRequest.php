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

        $this->merge([
            'bulan' => $bulan === null || $bulan === '' ? null : (int) $bulan,
            'tahun' => $tahun === null || $tahun === '' ? null : (int) $tahun,
        ]);
    }

    public function rules(): array
    {
        return [
            'bulan'       => 'nullable|integer|min:1|max:12',
            'tahun'       => 'nullable|integer|min:2020|max:' . date('Y'),
            'level'       => 'nullable|in:GM,RM,SAM,AM,MR',
            'pilar'       => 'nullable|in:ETHICAL,OGB,GENERIK,OTC',
            'distributor' => 'nullable|in:KFTD,AAM,METD',
            'coverage_id' => 'nullable|string',
        ];
    }

    // -------------------------------------------------------
    // Helpers — dipakai di Service agar tidak repeat
    // -------------------------------------------------------
    public function bulan(): string
    {
        return str_pad($this->get('bulan', date('m')), 2, '0', STR_PAD_LEFT);
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
