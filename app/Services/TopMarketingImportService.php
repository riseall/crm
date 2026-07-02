<?php

namespace App\Services;

use App\Models\TopMarketing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class TopMarketingImportService
{
    private const REQUIRED_HEADERS = ['pilar', 'val_net'];

    private const HEADER_ALIASES = [
        'divisi' => ['divisi', 'division'],
        'region' => ['region', 'regional'],
        'pm' => ['pm', 'product_manager', 'product manager'],
        'pilar' => ['pilar'],
        'kode_ph' => ['kode_ph', 'kode ph', 'kode produk', 'kode_produk', 'pt_ph', 'ph'],
        'nama_prod' => ['nama_prod', 'nama prod', 'nama_produk', 'nama produk', 'produk', 'product'],
        'segment' => ['segment', 'segmen'],
        'cbg_new' => ['cbg_new', 'id_cbg'],
        'unit' => ['unit', 'qty', 'quantity'],
        'val_net' => ['val_net', 'val nett'],
    ];

    public function import(UploadedFile $file, int $month, int $year): array
    {
        $rows = $this->readRows($file);
        $records = $this->buildRecords($rows, $month, $year);

        DB::connection('db_dwh')->transaction(function () use ($month, $year, $records) {
            TopMarketing::query()
                ->where('bulan', $month)
                ->where('tahun', (string) $year)
                ->delete();

            foreach (array_chunk($records, 500) as $chunk) {
                TopMarketing::query()->insert($chunk);
            }
        });

        Cache::flush();

        return [
            'month' => $month,
            'year' => $year,
            'inserted' => count($records),
        ];
    }

    private function buildRecords(array $rows, int $month, int $year): array
    {
        if (count($rows) < 2) {
            throw new InvalidArgumentException('File tidak memiliki baris data.');
        }

        $headerMap = $this->mapHeaders(array_shift($rows));
        $missing = array_values(array_filter(self::REQUIRED_HEADERS, fn($key) => !array_key_exists($key, $headerMap)));

        if (!empty($missing)) {
            throw new InvalidArgumentException('Header wajib belum ada: ' . implode(', ', $missing) . '.');
        }

        $records = [];
        $errors = [];
        $now = now();

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $record = [
                'tahun' => (string) $year,
                'bulan' => $month,
                'divisi' => $this->cell($row, $headerMap, 'divisi'),
                'region' => $this->cell($row, $headerMap, 'region'),
                'pm' => $this->cell($row, $headerMap, 'pm'),
                'pilar' => $this->cell($row, $headerMap, 'pilar'),
                'kode_ph' => $this->cell($row, $headerMap, 'kode_ph'),
                'nama_prod' => $this->cell($row, $headerMap, 'nama_prod'),
                'segment' => $this->cell($row, $headerMap, 'segment'),
                'cbg_new' => $this->cell($row, $headerMap, 'cbg_new'),
                'unit' => $this->decimalCell($row, $headerMap, 'unit'),
                'val_net' => $this->decimalCell($row, $headerMap, 'val_net'),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach (self::REQUIRED_HEADERS as $key) {
                if ($record[$key] === null || $record[$key] === '') {
                    $errors[] = "Baris {$line}: {$key} wajib diisi.";
                }
            }

            if ($record['unit'] === null) {
                $record['unit'] = 0;
            }

            if ($record['val_net'] === null) {
                $errors[] = "Baris {$line}: val_net harus berupa angka.";
            }

            if (count($errors) >= 10) {
                break;
            }

            $records[] = $record;
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        if (empty($records)) {
            throw new InvalidArgumentException('Tidak ada data valid untuk diupload.');
        }

        return $records;
    }

    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            return $this->readCsv($file->getRealPath());
        }

        if ($extension === 'xlsx') {
            return $this->readXlsx($file->getRealPath());
        }

        throw new InvalidArgumentException('Format file harus .xlsx atau .csv.');
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('File CSV tidak bisa dibaca.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak bisa dibuka.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetPath = $this->firstWorksheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Worksheet pertama tidak ditemukan.');
        }

        $sheet = simplexml_load_string($sheetXml);
        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($sheet->xpath('//x:sheetData/x:row') as $rowNode) {
            $row = [];
            foreach ($rowNode->c as $cell) {
                $attributes = $cell->attributes();
                $reference = (string) $attributes['r'];
                $columnIndex = $this->columnIndex($reference);
                $type = (string) $attributes['t'];
                $value = isset($cell->v) ? (string) $cell->v : '';

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = isset($cell->is->t) ? (string) $cell->is->t : '';
                }

                $row[$columnIndex] = $value;
            }

            if (!empty($row)) {
                $normalizedRow = [];
                for ($i = 0; $i <= max(array_keys($row)); $i++) {
                    $normalizedRow[] = $row[$i] ?? '';
                }
                $rows[] = $normalizedRow;
            }
        }

        return $rows;
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $shared = simplexml_load_string($xml);
        $values = [];

        foreach ($shared->si as $item) {
            if (isset($item->t)) {
                $values[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }
            $values[] = $text;
        }

        return $values;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
            return 'xl/worksheets/sheet1.xml';
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, 'xl/worksheets/') === 0 && substr($name, -4) === '.xml') {
                return $name;
            }
        }

        throw new RuntimeException('Worksheet Excel tidak ditemukan.');
    }

    private function mapHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $index => $header) {
            $normalized[$this->normalizeHeader((string) $header)] = $index;
        }

        $map = [];
        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $key = $this->normalizeHeader($alias);
                if (array_key_exists($key, $normalized)) {
                    $map[$field] = $normalized[$key];
                    break;
                }
            }
        }

        return $map;
    }

    private function normalizeHeader(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim((string) $value, '_');
    }

    private function cell(array $row, array $headerMap, string $key): ?string
    {
        if (!array_key_exists($key, $headerMap)) {
            return null;
        }

        $value = trim((string) ($row[$headerMap[$key]] ?? ''));

        return $value === '' ? null : $value;
    }

    private function decimalCell(array $row, array $headerMap, string $key): ?float
    {
        $value = $this->cell($row, $headerMap, $key);

        if ($value === null) {
            return null;
        }

        $value = str_replace([' ', ','], ['', ''], $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
