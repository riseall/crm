<?php

namespace App\Http\Requests;

use Carbon\Carbon;
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
        $pilar = $this->normalizeArrayInput('pilar');
        $distributor = $this->normalizeArrayInput('distributor');
        $product = $this->normalizeArrayInput('product');
        $divisi = $this->normalizeArrayInput('divisi');
        $region = $this->normalizeArrayInput('region');
        $pm = $this->normalizeArrayInput('pm');
        $employee = $this->normalizeArrayInput('employee');

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
            'start_date' => $this->input('start_date') ?: null,
            'end_date' => $this->input('end_date') ?: null,
            'pilar' => $pilar,
            'distributor' => $distributor,
            'product' => $product,
            'divisi' => $divisi,
            'region' => $region,
            'pm' => $pm,
            'employee' => $employee,
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
            'start_date'  => ['nullable', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date'    => ['nullable', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
            'level'       => 'nullable|in:GM,RM,SAM,AM,MR',
            'pilar'       => ['nullable', 'array'],
            'pilar.*'     => ['string', 'distinct', 'in:ETHIPROM,ETHICOM,ETHICAL,OGB,GENERIK,OTC'],
            'distributor' => ['nullable', 'array'],
            'distributor.*' => ['string', 'distinct', 'in:KFTD,AAM,METD'],
            'product'     => ['nullable', 'array'],
            'product.*'   => ['string', 'distinct', 'max:50'],
            'divisi'      => ['nullable', 'array'],
            'divisi.*'    => ['string', 'distinct', 'max:50'],
            'region'      => ['nullable', 'array'],
            'region.*'    => ['string', 'distinct', 'max:50'],
            'pm'          => ['nullable', 'array'],
            'pm.*'        => ['string', 'distinct', 'max:50'],
            'employee'    => ['nullable', 'array'],
            'employee.*'  => ['string', 'distinct', 'max:50'],
            'coverage_id' => 'nullable|string',
            'coverage_nik' => 'nullable|string',
            'outlet_id' => 'nullable|string',
            'unmapped' => 'nullable',
        ];
    }

    // -------------------------------------------------------
    // Helpers — dipakai di Service agar tidak repeat
    // -------------------------------------------------------
    public function bulan(): string
    {
        if ($this->filled('start_date')) {
            return Carbon::parse($this->input('start_date'))->format('m');
        }

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
        if ($this->filled('start_date')) {
            return Carbon::parse($this->input('start_date'))->format('Y');
        }

        return $this->input('tahun') ?? (string) date('Y');
    }

    public function startDate(): string
    {
        if ($this->filled('start_date')) {
            return Carbon::parse($this->input('start_date'))->startOfDay()->toDateTimeString();
        }

        return "{$this->tahun()}-{$this->bulan()}-01";
    }

    public function endDate(): string
    {
        if ($this->filled('end_date')) {
            return Carbon::parse($this->input('end_date'))->endOfDay()->toDateTimeString();
        }

        return date('Y-m-t', strtotime($this->startDate()));
    }

    public function prevStartDate(): string
    {
        $start = Carbon::parse($this->startDate());
        $days = $start->diffInDays(Carbon::parse($this->endDate())) + 1;

        return $start->copy()->subDays($days)->startOfDay()->toDateTimeString();
    }

    public function prevEndDate(): string
    {
        return Carbon::parse($this->startDate())->subDay()->endOfDay()->toDateTimeString();
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
