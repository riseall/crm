<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class SellingInFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bulan'       => ['nullable', 'integer', 'between:1,12'],
            'tahun'       => ['nullable', 'integer', 'min:2000'],
            'pilar'       => ['nullable', 'string', 'in:ETHICAL,GENERIK,OTC,EXPORT'],
            'product'     => ['nullable', 'string', 'max:50'],
            'distributor' => ['nullable', 'string', 'in:KFTD,AAM,METD'],
            'search'       => ['nullable'],
            'search.value' => ['nullable', 'string', 'max:100'],
        ];
    }

    // -------------------------------------------------------
    // Helpers — periode aktif
    // -------------------------------------------------------

    public function bulan(): int
    {
        return (int) $this->get('bulan', now()->month);
    }

    public function tahun(): int
    {
        return (int) $this->get('tahun', now()->year);
    }

    public function startDate(): string
    {
        return Carbon::create($this->tahun(), $this->bulan(), 1)->startOfMonth()->toDateString();
    }

    public function endDate(): string
    {
        return Carbon::create($this->tahun(), $this->bulan(), 1)->endOfMonth()->toDateString();
    }

    // Periode bulan sebelumnya — untuk growth calculation
    public function prevStartDate(): string
    {
        return Carbon::create($this->tahun(), $this->bulan(), 1)
            ->subMonth()->startOfMonth()->toDateString();
    }

    public function prevEndDate(): string
    {
        return Carbon::create($this->tahun(), $this->bulan(), 1)
            ->subMonth()->endOfMonth()->toDateString();
    }
}
