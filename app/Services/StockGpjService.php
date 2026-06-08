<?php

namespace App\Services;

use App\Models\StockPhInGpj;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class StockGpjService
{
    protected string $url;
    protected string $namespace;

    public function __construct()
    {
        $this->url = config('services.qad_wsa.url');
        $this->namespace = config('services.qad_wsa.namespace');
    }

    public function getInventoryGpj(): array
    {
        $soapBody = <<<XML
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
            <soap:Body>
                <getInvGpj01 xmlns="{$this->namespace}">
                    <parDBLogical>qaddb</parDBLogical>
                </getInvGpj01>
            </soap:Body>
        </soap:Envelope>
        XML;

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml;charset=UTF-8',
            'SOAPAction' => 'urn:iris:wsa-phapros:getInvGpj01',
        ])->send('POST', $this->url, [
            'body' => $soapBody,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Request WSA getInvGpj01 failed');
        }

        return $this->parseInventoryGpjResponse($response->body());
    }

    public function syncInventoryGpj(): int
    {
        $rows = collect($this->getInventoryGpj())
            ->map(function (array $row) {
                $row['row_key'] = $this->rowKey($row);
                $row['created_at'] = now();
                $row['updated_at'] = now();

                return $row;
            })
            ->values();

        if ($rows->isEmpty()) {
            return 0;
        }

        StockPhInGpj::query()
            ->upsert(
                $rows->all(),
                ['row_key'],
                [
                    'pt_desc',
                    'stock',
                    'pt_um',
                    'ld_loc',
                    'ld_lot',
                    'pt_promo',
                    'pilar',
                    'picscode',
                    'ptprice',
                    'umconv',
                    'rharga',
                    'cathrg',
                    'expdt',
                    'kelompok_ed',
                    'updated_at',
                ]
            );

        return $rows->count();
    }

    private function parseInventoryGpjResponse(string $xmlString): array
    {
        $xmlString = $this->normalizeXmlString($xmlString);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_PARSEHUGE | LIBXML_NONET);

        if (!$xml) {
            $errors = collect(libxml_get_errors())
                ->map(fn($error) => trim($error->message) . " on line {$error->line}")
                ->implode('; ');
            libxml_clear_errors();

            $rows = $this->parseInventoryGpjRowsByTag($xmlString);

            if (!empty($rows)) {
                return $rows;
            }

            throw new \Exception('Invalid XML response from WSA getInvGpj01: ' . ($errors ?: $this->xmlDebugSnippet($xmlString)));
        }
        libxml_clear_errors();

        $xml->registerXPathNamespace(
            'soap',
            'http://schemas.xmlsoap.org/soap/envelope/'
        );

        $body = $xml->xpath('//soap:Body')[0] ?? null;

        if (!$body) {
            return [];
        }

        $resp = $body
            ->children($this->namespace)
            ->getInvGpj01Response;

        $rows = [];

        if (isset($resp->tt_inv_gpj01->tt_inv_gpj01Row)) {
            foreach ($resp->tt_inv_gpj01->tt_inv_gpj01Row as $row) {
                $rows[] = [
                    'pt_part'     => (string) $row->tt_pt_part,
                    'pt_desc'     => html_entity_decode((string) $row->tt_pt_desc),
                    'stock'       => (float) $row->tt_stock,
                    'pt_um'       => (string) $row->tt_pt_um,
                    'ld_loc'      => (string) $row->tt_ld_loc,
                    'ld_lot'      => (string) $row->tt_ld_lot,
                    'pt_promo'    => (string) $row->tt_pt_promo,
                    'pilar'       => (string) $row->tt_pilar,
                    'picscode'    => (string) $row->tt_picscode,
                    'ptprice'     => (float) $row->tt_ptprice,
                    'umconv'      => (float) $row->tt_umconv,
                    'rharga'      => (float) $row->tt_rharga,
                    'cathrg'      => (string) $row->tt_cathrg,
                    'expdt'       => (string) $row->tt_expdt ?: null,
                    'kelompok_ed' => html_entity_decode((string) $row->tt_kelompok_ed),
                ];
            }
        }

        return $rows;
    }

    private function parseInventoryGpjRowsByTag(string $xmlString): array
    {
        preg_match_all('/<tt_inv_gpj01Row\b[^>]*>(.*?)<\/tt_inv_gpj01Row>/s', $xmlString, $matches);

        if (empty($matches[1])) {
            return [];
        }

        return collect($matches[1])
            ->map(function (string $rowXml) {
                return [
                    'pt_part'     => $this->tagValue($rowXml, 'tt_pt_part'),
                    'pt_desc'     => html_entity_decode($this->tagValue($rowXml, 'tt_pt_desc'), ENT_QUOTES | ENT_XML1, 'UTF-8'),
                    'stock'       => (float) $this->tagValue($rowXml, 'tt_stock'),
                    'pt_um'       => $this->tagValue($rowXml, 'tt_pt_um'),
                    'ld_loc'      => $this->tagValue($rowXml, 'tt_ld_loc'),
                    'ld_lot'      => $this->tagValue($rowXml, 'tt_ld_lot'),
                    'pt_promo'    => $this->tagValue($rowXml, 'tt_pt_promo'),
                    'pilar'       => $this->tagValue($rowXml, 'tt_pilar'),
                    'picscode'    => $this->tagValue($rowXml, 'tt_picscode'),
                    'ptprice'     => (float) $this->tagValue($rowXml, 'tt_ptprice'),
                    'umconv'      => (float) $this->tagValue($rowXml, 'tt_umconv'),
                    'rharga'      => (float) $this->tagValue($rowXml, 'tt_rharga'),
                    'cathrg'      => $this->tagValue($rowXml, 'tt_cathrg'),
                    'expdt'       => $this->tagValue($rowXml, 'tt_expdt') ?: null,
                    'kelompok_ed' => html_entity_decode($this->tagValue($rowXml, 'tt_kelompok_ed'), ENT_QUOTES | ENT_XML1, 'UTF-8'),
                ];
            })
            ->values()
            ->all();
    }

    private function tagValue(string $xmlString, string $tag): string
    {
        if (preg_match('/<' . preg_quote($tag, '/') . '\b[^>]*\/>/s', $xmlString)) {
            return '';
        }

        if (!preg_match('/<' . preg_quote($tag, '/') . '\b[^>]*>(.*?)<\/' . preg_quote($tag, '/') . '>/s', $xmlString, $matches)) {
            return '';
        }

        return trim($matches[1]);
    }

    private function rowKey(array $row): string
    {
        return sha1(implode('|', [
            Str::upper((string) ($row['pt_part'] ?? '')),
            Str::upper((string) ($row['ld_loc'] ?? '')),
            Str::upper((string) ($row['ld_lot'] ?? '')),
            (string) ($row['expdt'] ?? ''),
        ]));
    }

    private function normalizeXmlString(string $xmlString): string
    {
        $xmlString = trim($xmlString);
        $xmlString = preg_replace('/^\xEF\xBB\xBF/', '', $xmlString) ?? $xmlString;

        if (function_exists('mb_check_encoding') && !mb_check_encoding($xmlString, 'UTF-8')) {
            $xmlString = mb_convert_encoding($xmlString, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
        }

        $xmlStart = strpos($xmlString, '<');
        if ($xmlStart !== false && $xmlStart > 0) {
            $xmlString = substr($xmlString, $xmlStart);
        }

        // QAD data can contain bare ampersands in text nodes, which breaks XML parsing.
        $xmlString = preg_replace(
            '/&(?!amp;|lt;|gt;|quot;|apos;|#[0-9]+;|#x[0-9a-fA-F]+;)/',
            '&amp;',
            $xmlString
        ) ?? $xmlString;

        // Remove invalid XML 1.0 control characters.
        return preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $xmlString) ?? $xmlString;
    }

    private function xmlDebugSnippet(string $xmlString): string
    {
        $snippet = substr($xmlString, 0, 500);
        $snippet = preg_replace('/\s+/', ' ', $snippet) ?? $snippet;

        return 'unknown XML parse error; length=' . strlen($xmlString) . '; starts_with=' . json_encode($snippet);
    }
}
