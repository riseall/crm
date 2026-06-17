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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pilar' => $this->normalizePilarInput(),
            'product' => $this->normalizeArrayInput('product'),
            'distributor' => $this->normalizeArrayInput('distributor'),
        ]);
    }

    public function rules(): array
    {
        return [
            'bulan'       => ['nullable', 'integer', 'between:1,12'],
            'tahun'       => ['nullable', 'integer', 'min:2000'],
            'start_date'  => ['nullable', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date'    => ['nullable', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
            'pilar'       => ['nullable', 'array'],
            'pilar.*'     => ['string', 'distinct', 'in:ETHIPROM,ETHICOM,ETHICAL,OGB,OTC,EXPORT'],
            'product'     => ['nullable', 'array'],
            'product.*'   => ['string', 'distinct', 'max:50'],
            'distributor' => ['nullable', 'array'],
            'distributor.*' => ['string', 'distinct', 'in:KFTD,AAM,METD'],
            'search'       => ['nullable'],
            'search.value' => ['nullable', 'string', 'max:100'],
        ];
    }

    // -------------------------------------------------------
    // Helpers — periode aktif
    // -------------------------------------------------------

    public function bulan(): int
    {
        if ($this->filled('start_date')) {
            return Carbon::parse($this->input('start_date'))->month;
        }

        return (int) $this->get('bulan', now()->month);
    }

    public function tahun(): int
    {
        if ($this->filled('start_date')) {
            return Carbon::parse($this->input('start_date'))->year;
        }

        return (int) $this->get('tahun', now()->year);
    }

    public function startDate(): string
    {
        if ($this->filled('start_date')) {
            return Carbon::parse($this->input('start_date'))->toDateString();
        }

        return Carbon::create($this->tahun(), $this->bulan(), 1)->startOfMonth()->toDateString();
    }

    public function endDate(): string
    {
        if ($this->filled('end_date')) {
            return Carbon::parse($this->input('end_date'))->toDateString();
        }

        return Carbon::create($this->tahun(), $this->bulan(), 1)->endOfMonth()->toDateString();
    }

    // Periode bulan sebelumnya — untuk growth calculation
    public function prevStartDate(): string
    {
        $start = Carbon::parse($this->startDate());
        $days = $start->diffInDays(Carbon::parse($this->endDate())) + 1;

        return $start->copy()->subDays($days)->toDateString();
    }

    public function prevEndDate(): string
    {
        return Carbon::parse($this->startDate())->subDay()->toDateString();
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

    private function normalizePilarInput(): ?array
    {
        $pilars = $this->normalizeArrayInput('pilar');

        if ($pilars === null) {
            return null;
        }

        return collect($pilars)
            ->map(fn($pilar) => strtoupper($pilar) === 'GENERIK' ? 'OGB' : strtoupper($pilar))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
