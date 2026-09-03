<?php

namespace App\Http\Controllers;

use App\Models\CrmMstCust;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class VisitController extends Controller
{
    public function index(Request $request, DashboardService $dashboardService)
    {
        $anggota = null;
        if ($request->has('nik')) {
            try {
                $data = $dashboardService->getAnggotaDetail($request->nik, $request);
                $anggota = $data['anggota'] ?? null;
            } catch (\Exception $e) {
                Log::warning('Gagal meload detail anggota di VisitController: ' . $e->getMessage());
            }
        }

        return view('page.visit.index', [
            'pilars'     => ['ETHIPROM', 'ETHICOM', 'OGB', 'OTC'],
            'anggota'    => $anggota,
            'initialNik' => $request->nik
        ]);
    }

    /**
     * Dashboard Utama: Fetch data kunjungan nasional (semua cabang).
     */
    public function getVisitData(Request $request)
    {
        $visitDate = $this->resolveVisitDate($request);
        $fetchResult = $this->fetchAndNormalizeVisits($visitDate, null);

        return $this->buildResponse($request, $fetchResult, $visitDate);
    }

    /**
     * Detail Sales: Fetch data kunjungan ter-scope ke cabang sales berdasarkan lookup NIK.
     */
    public function getVisitDataBySales(Request $request)
    {
        $visitDate = $this->resolveVisitDate($request);
        $nik = $request->get('nik');

        $branchCode = null;
        $fallbackNote = null;

        if ($nik) {
            try {
                $mapping = DB::connection('db_dwh')
                    ->table('crm_mst_mappings')
                    ->where('cov_nik', $nik)
                    ->whereNull('deleted_at')
                    ->first();

                if ($mapping && !empty($mapping->cov_ket_cbg)) {
                    $branchCode = trim($mapping->cov_ket_cbg);
                } else {
                    $fallbackNote = "Branch code untuk NIK {$nik} tidak ditemukan di mapping CRM. Memuat data nasional sebagai fallback.";
                    Log::info('VisitController::getVisitDataBySales fallback to national', ['nik' => $nik]);
                }
            } catch (\Exception $e) {
                $fallbackNote = "Gagal memverifikasi branch code untuk NIK {$nik}. Memuat data nasional sebagai fallback.";
                Log::warning('Error lookup branch_code dari crm_mst_mappings: ' . $e->getMessage());
            }
        }

        $fetchResult = $this->fetchAndNormalizeVisits($visitDate, $branchCode);

        // Jika terjadi fallback dan tidak ada error API SFA yang lebih kritis, tampilkan catatan fallback
        if ($fallbackNote && empty($fetchResult['errorMessage'])) {
            $fetchResult['errorMessage'] = $fallbackNote;
        }

        return $this->buildResponse($request, $fetchResult, $visitDate);
    }

    /**
     * Reusable fetch & normalize logic:
     * Menembak API SFA (dengan opsi filter branch_code), link ke CRM master customer & mapping,
     * lalu normalisasi payload untuk Map & DataTables.
     */
    protected function fetchAndNormalizeVisits(string $visitDate, ?string $branchCode = null): array
    {
        $url = 'https://sfa-phapros.klikpeta.com/sfa/visits';
        $apiKey = config('services.sfa.api_key');

        // Cache key membedakan nasional vs per-cabang (v8: filtered office attendance check-ins)
        $cacheKey = !empty($branchCode)
            ? 'sfa_visits_v8_branch_' . $branchCode . '_' . md5($visitDate)
            : 'sfa_visits_v8_' . md5($visitDate);

        $ttl = 900; // 15 menit

        return Cache::remember($cacheKey, $ttl, function () use ($url, $apiKey, $visitDate, $branchCode) {
            $rawVisits = [];
            $apiSuccess = false;
            $errorMessage = null;
            $batchSize = 8; // Concurrency optimal agar API SFA tidak drop koneksi

            if ($apiKey) {
                try {
                    // 1. Fetch halaman pertama (termasuk parameter branch_code jika diisi)
                    $firstParams = [
                        'visit_date' => $visitDate,
                        'page'       => 1,
                        'per_page'   => 100,
                    ];
                    if (!empty($branchCode)) {
                        $firstParams['branch_code'] = $branchCode;
                    }

                    $firstResponse = Http::withHeaders([
                        'X-API-KEY' => $apiKey,
                        'Accept'    => 'application/json'
                    ])->timeout(25)->get($url, $firstParams);

                    if ($firstResponse->successful()) {
                        $json = $firstResponse->json();
                        $rawVisits = $json['data'] ?? $json['result'] ?? (is_array($json) ? $json : []);
                        $totalPages = isset($json['meta']['total_pages']) ? (int) $json['meta']['total_pages'] : 1;
                        $totalDataExpected = isset($json['meta']['total_data']) ? (int) $json['meta']['total_data'] : null;
                        $apiSuccess = true;

                        // 2. Fetch sisa halaman (2..N) via concurrent Http::pool
                        if ($totalPages > 1) {
                            $failedPages = [];
                            $pages = range(2, $totalPages);

                            foreach (array_chunk($pages, $batchSize) as $pageBatch) {
                                $responses = Http::pool(
                                    fn($pool) =>
                                    collect($pageBatch)->map(
                                        function ($page) use ($pool, $apiKey, $url, $visitDate, $branchCode) {
                                            $pageParams = [
                                                'visit_date' => $visitDate,
                                                'page'       => $page,
                                                'per_page'   => 100,
                                            ];
                                            if (!empty($branchCode)) {
                                                $pageParams['branch_code'] = $branchCode;
                                            }

                                            return $pool->withHeaders([
                                                'X-API-KEY' => $apiKey,
                                                'Accept'    => 'application/json'
                                            ])->timeout(25)->get($url, $pageParams);
                                        }
                                    )->all()
                                );

                                foreach ($responses as $i => $response) {
                                    $page = $pageBatch[$i];
                                    if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                                        $pageJson = $response->json();
                                        $pageVisits = $pageJson['data'] ?? $pageJson['result'] ?? [];
                                        $rawVisits = array_merge($rawVisits, $pageVisits);
                                    } else {
                                        $failedPages[] = $page;
                                    }
                                }
                            }

                            // 3. Retry loop jika ada halaman batch yang gagal/timeout
                            if (!empty($failedPages)) {
                                $retryPages = $failedPages;
                                $failedPages = [];

                                foreach ($retryPages as $retryPage) {
                                    try {
                                        $pageParams = [
                                            'visit_date' => $visitDate,
                                            'page'       => $retryPage,
                                            'per_page'   => 100,
                                        ];
                                        if (!empty($branchCode)) {
                                            $pageParams['branch_code'] = $branchCode;
                                        }

                                        $retryRes = Http::withHeaders([
                                            'X-API-KEY' => $apiKey,
                                            'Accept'    => 'application/json'
                                        ])->timeout(25)->get($url, $pageParams);

                                        if ($retryRes->successful()) {
                                            $pageJson = $retryRes->json();
                                            $pageVisits = $pageJson['data'] ?? $pageJson['result'] ?? [];
                                            $rawVisits = array_merge($rawVisits, $pageVisits);
                                        } else {
                                            $failedPages[] = $retryPage;
                                            Log::warning('SFA retry page gagal', [
                                                'page'        => $retryPage,
                                                'branch_code' => $branchCode,
                                                'status'      => $retryRes->status()
                                            ]);
                                        }
                                    } catch (\Exception $e) {
                                        $failedPages[] = $retryPage;
                                        Log::warning('SFA retry page exception', [
                                            'page'        => $retryPage,
                                            'branch_code' => $branchCode,
                                            'error'       => $e->getMessage()
                                        ]);
                                    }
                                }
                            }

                            if (!empty($failedPages)) {
                                $errorMessage = 'SFA API: ' . count($failedPages) . ' dari ' . $totalPages . ' halaman gagal diambil (halaman: ' . implode(',', $failedPages) . '). Data kemungkinan tidak lengkap.';
                            }
                        }

                        // 4. Guard jika selisih data akibat page gagal
                        if ($totalDataExpected !== null && count($rawVisits) < $totalDataExpected && !empty($failedPages)) {
                            Log::warning('SFA visits count mismatch due to failed pages', [
                                'visit_date'  => $visitDate,
                                'branch_code' => $branchCode,
                                'expected'    => $totalDataExpected,
                                'fetched'     => count($rawVisits),
                            ]);
                            if (!$errorMessage) {
                                $errorMessage = 'Data kunjungan tidak lengkap: ' . $totalDataExpected . ' tercatat di SFA, ' . count($rawVisits) . ' berhasil diambil.';
                            }
                        }
                    } else {
                        $errorBody = $firstResponse->json();
                        $msg = is_array($errorBody) ? ($errorBody['message'] ?? ('HTTP ' . $firstResponse->status())) : ('HTTP ' . $firstResponse->status());
                        $errorMessage = 'SFA API Error: ' . $msg;
                        Log::warning('SFA Visit API returned status ' . $firstResponse->status(), [
                            'branch_code' => $branchCode,
                            'body'        => $firstResponse->body()
                        ]);
                    }
                } catch (\Exception $e) {
                    $errorMessage = 'SFA Connection Error: ' . $e->getMessage();
                    Log::error('SFA Visit API Exception: ' . $e->getMessage(), ['branch_code' => $branchCode]);
                }
            } else {
                $errorMessage = 'KLIKPETA_API_KEY tidak terkonfigurasi di file .env.';
            }

            // 5. Deduplikasi Data Kunjungan (Menghapus record ganda dari SFA API pagination)
            $dedupedVisits = [];
            foreach ($rawVisits as $item) {
                $vId = null;
                if (!empty($item['id'])) {
                    $vId = 'id_' . $item['id'];
                } else {
                    $nik = isset($item['nik_pic']) ? $item['nik_pic'] : (isset($item['nik']) ? $item['nik'] : (isset($item['sales_nik']) ? $item['sales_nik'] : ''));
                    $loc = isset($item['location_code']) ? $item['location_code'] : (isset($item['outlet_code']) ? $item['outlet_code'] : '');
                    $vDate = isset($item['visit_date']) ? $item['visit_date'] : $visitDate;
                    $vTime = isset($item['visit_time']) ? $item['visit_time'] : '';
                    $lat = isset($item['visit_latitude']) ? $item['visit_latitude'] : (isset($item['lat']) ? $item['lat'] : 0);
                    $lng = isset($item['visit_longitude']) ? $item['visit_longitude'] : (isset($item['lng']) ? $item['lng'] : 0);
                    $vId = "sig_{$nik}_{$loc}_{$vDate}_{$vTime}_{$lat}_{$lng}";
                }

                if (isset($dedupedVisits[$vId])) {
                    // Gabungkan product detailing jika ada
                    if (!empty($item['product_detailing']) && is_array($item['product_detailing'])) {
                        $existing = isset($dedupedVisits[$vId]['product_detailing']) && is_array($dedupedVisits[$vId]['product_detailing'])
                            ? $dedupedVisits[$vId]['product_detailing']
                            : [];
                        $merged = array_merge($existing, $item['product_detailing']);
                        $dedupedVisits[$vId]['product_detailing'] = collect($merged)->unique(function ($p) {
                            return ($p['code'] ?? '') . '_' . ($p['name'] ?? '');
                        })->values()->all();
                    }
                } else {
                    $dedupedVisits[$vId] = $item;
                }
            }
            $visits = array_values($dedupedVisits);

            // 🐎 Ponytail: Filter out absensi/kunjungan kantor internal (KANTOR-*, PHAPROS CAB., dll).
            // Data ini adalah absensi pegawai di kantor cabang/pusat, bukan kunjungan sales ke outlet/pelanggan.
            $visits = array_values(array_filter($visits, function ($item) {
                $code = strtoupper(trim((string) (
                    isset($item['location_code']) && !empty($item['location_code'])
                        ? $item['location_code']
                        : (isset($item['outlet_code']) && !empty($item['outlet_code'])
                            ? $item['outlet_code']
                            : (isset($item['customer_id']) ? $item['customer_id'] : ''))
                )));
                $name = strtoupper(trim((string) (
                    isset($item['outlet_name']) && !empty($item['outlet_name'])
                        ? $item['outlet_name']
                        : (isset($item['location_name']) && !empty($item['location_name'])
                            ? $item['location_name']
                            : '')
                )));

                if ($code !== '' && strpos($code, 'KANTOR') !== false) {
                    return false;
                }
                if ($name !== '' && strpos($name, 'KANTOR') !== false) {
                    return false;
                }
                if ($name !== '' && strpos($name, 'PHAPROS') === 0) {
                    return false;
                }

                return true;
            }));


            // Link with CRM Master Customer using id_konversi (matched via SFA location_code)
            $locationCodes = collect($visits)
                ->map(function ($item) {
                    return !empty($item['location_code'])
                        ? $item['location_code']
                        : (!empty($item['outlet_code']) ? $item['outlet_code'] : null);
                })
                ->filter()
                ->unique()
                ->values()
                ->all();

            $masterCustMap = collect();
            if (!empty($locationCodes)) {
                try {
                    $masterCustMap = CrmMstCust::whereIn('id_konversi', $locationCodes)
                        ->with(['coverages.mapping.user'])
                        ->get()
                        ->keyBy('id_konversi');
                } catch (\Exception $e) {
                    Log::warning('Error querying CrmMstCust via id_konversi: ' . $e->getMessage());
                }
            }

            // Users lookup from auth table (connection: mysql)
            $userByNik = collect();
            try {
                $userByNik = DB::table('users')->pluck('name', 'nik');
            } catch (\Exception $e) {
                Log::warning('Error querying users table: ' . $e->getMessage());
            }

            // Link with CRM Mappings using NIK PIC & Names
            $mappingByNik = collect();
            $mappingByName = collect();
            try {
                $allMappings = DB::connection('db_dwh')
                    ->table('crm_mst_mappings')
                    ->whereNull('deleted_at')
                    ->get();

                $mappingByNik = $allMappings->keyBy('cov_nik');
                $mappingByName = $allMappings->keyBy(fn($m) => mb_strtolower(trim($m->cov_desc ?? '')));
            } catch (\Exception $e) {
                Log::warning('Error querying crm_mst_mappings: ' . $e->getMessage());
            }

            // Query Sellout Netto from crm_sellout_summary via id_konversi and pilar
            $selloutPilarMap = collect();
            $selloutOutletMap = collect();
            if (!empty($locationCodes)) {
                try {
                    $selloutPilarRows = DB::connection('db_dwh')
                        ->table('crm_sellout_summary')
                        ->whereIn('id_konversi', $locationCodes)
                        ->groupBy('id_konversi', 'pilar_sub')
                        ->select('id_konversi', 'pilar_sub', DB::raw('SUM(netto) as total_netto'))
                        ->get();

                    $selloutPilarMap = $selloutPilarRows->groupBy('id_konversi');

                    $selloutOutletMap = DB::connection('db_dwh')
                        ->table('crm_sellout_summary')
                        ->whereIn('id_konversi', $locationCodes)
                        ->groupBy('id_konversi')
                        ->select('id_konversi', DB::raw('SUM(netto) as total_netto'))
                        ->pluck('total_netto', 'id_konversi');
                } catch (\Exception $e) {
                    Log::warning('Error querying crm_sellout_summary: ' . $e->getMessage());
                }
            }

            // Normalize live visit items for Map and DataTable (Strict PHP 7.4 compatible)
            $normalizedVisits = collect($visits)->map(function ($item, $idx) use ($visitDate, $masterCustMap, $selloutPilarMap, $selloutOutletMap, $userByNik, $mappingByNik, $mappingByName) {
                $lat = (float) ($item['visit_latitude'] ?? $item['lat'] ?? 0);
                $lng = (float) ($item['visit_longitude'] ?? $item['lng'] ?? $item['long'] ?? 0);
                $locationCode = isset($item['location_code']) && !empty($item['location_code'])
                    ? $item['location_code']
                    : (isset($item['outlet_code']) && !empty($item['outlet_code']) ? $item['outlet_code'] : null);
                $cust = ($locationCode && $masterCustMap->has($locationCode)) ? $masterCustMap->get($locationCode) : null;

                // Resolve SFA identity
                $sfaNik = isset($item['nik_pic']) && trim((string)$item['nik_pic']) !== ''
                    ? trim((string)$item['nik_pic'])
                    : (isset($item['nik']) ? trim((string)$item['nik']) : (isset($item['sales_nik']) ? trim((string)$item['sales_nik']) : null));
                $sfaName = isset($item['nama_pic']) && trim((string)$item['nama_pic']) !== ''
                    ? trim((string)$item['nama_pic'])
                    : (isset($item['sales_name']) ? trim((string)$item['sales_name']) : (isset($item['user_name']) ? trim((string)$item['user_name']) : null));

                // Resolve Mapping from visiting sales NIK / Name
                $matchedMapping = null;
                if ($sfaNik && $mappingByNik->has($sfaNik)) {
                    $matchedMapping = $mappingByNik->get($sfaNik);
                } elseif ($sfaName) {
                    $nameKey = mb_strtolower(trim($sfaName));
                    if ($mappingByName->has($nameKey)) {
                        $matchedMapping = $mappingByName->get($nameKey);
                    }
                }

                $nik = $sfaNik ? $sfaNik : ($matchedMapping ? $matchedMapping->cov_nik : (isset($item['branch_code']) ? $item['branch_code'] : '-'));
                $officialName = ($nik && $userByNik->has($nik))
                    ? $userByNik->get($nik)
                    : ($matchedMapping ? $matchedMapping->cov_desc : $sfaName);
                $salesName = $officialName ? $officialName : (isset($item['branch_name']) ? $item['branch_name'] : 'Sales Force');

                // Match specific coverage in master cust
                $matchedCov = null;
                if ($cust && $cust->coverages && $cust->coverages->isNotEmpty()) {
                    if ($nik && $nik !== '-') {
                        $matchedCov = $cust->coverages->first(function ($c) use ($nik) {
                            return optional($c->mapping)->cov_nik == $nik;
                        });
                    }
                    if (!$matchedCov && $matchedMapping && !empty($matchedMapping->cov_pilar)) {
                        $pilarTarget = $matchedMapping->cov_pilar;
                        $matchedCov = $cust->coverages->first(function ($c) use ($pilarTarget) {
                            return $c->pilar == $pilarTarget;
                        });
                    }
                    if (!$matchedCov) {
                        $matchedCov = $cust->coverages->first();
                    }
                }

                // Resolve Pilar accurately: SFA payload -> Sales Mapping Pilar -> Coverage Pilar -> Cust Divisi
                $pilar = !empty($item['pilar'])
                    ? $item['pilar']
                    : ($matchedMapping && !empty($matchedMapping->cov_pilar)
                        ? $matchedMapping->cov_pilar
                        : ($matchedCov && !empty($matchedCov->pilar)
                            ? $matchedCov->pilar
                            : ($cust && !empty($cust->divisi) ? $cust->divisi : '-')));

                // Resolve Outlet Name prioritizing crm_mst_cust (nama_outlet)
                $outletName = ($cust && !empty($cust->nama_outlet))
                    ? $cust->nama_outlet
                    : (isset($item['outlet_name']) && !empty($item['outlet_name'])
                        ? $item['outlet_name']
                        : (isset($item['location_name']) && !empty($item['location_name'])
                            ? $item['location_name']
                            : ('Outlet #' . ($idx + 1))));
                $customerId = $locationCode ? $locationCode : (isset($item['outlet_code']) ? $item['outlet_code'] : '-');
                $targetKonversi = $locationCode ? $locationCode : (isset($item['outlet_code']) ? $item['outlet_code'] : null);

                // Calculate Netto matching NIK & Pilar
                $totalNetto = 0.0;
                if ($targetKonversi) {
                    if ($pilar && $pilar !== '-' && $selloutPilarMap->has($targetKonversi)) {
                        $pilarRows = $selloutPilarMap->get($targetKonversi);
                        $matchingRows = $pilarRows->filter(function ($r) use ($pilar) {
                            $ps = strtoupper(trim((string)$r->pilar_sub));
                            $p = strtoupper(trim((string)$pilar));
                            return $ps === $p || ($p === 'ETHICAL' && in_array($ps, ['ETHICOM', 'ETHIPROM'], true));
                        });
                        if ($matchingRows->isNotEmpty()) {
                            $totalNetto = (float) $matchingRows->sum('total_netto');
                        } else {
                            $totalNetto = (float) ($selloutOutletMap->get($targetKonversi) ?? 0.0);
                        }
                    } elseif ($selloutOutletMap->has($targetKonversi)) {
                        $totalNetto = (float) $selloutOutletMap->get($targetKonversi);
                    }
                }

                return [
                    'id'                  => isset($item['id']) ? $item['id'] : ($idx + 1),
                    'nik'                 => $nik ? $nik : (isset($item['branch_code']) ? $item['branch_code'] : '-'),
                    'sfa_nik'             => $sfaNik,
                    'sales_name'          => $salesName ? $salesName : (isset($item['branch_name']) ? $item['branch_name'] : 'Sales Force'),
                    'pic_name'            => $sfaName ? $sfaName : (isset($item['branch_name']) ? $item['branch_name'] : 'Sales Force'),
                    'outlet_name'         => $outletName,
                    'customer_id'         => $customerId,
                    'location_code'       => $locationCode,
                    'id_konversi'         => $locationCode,
                    'total_netto'         => $totalNetto,
                    'outlet_code'         => isset($item['outlet_code']) ? $item['outlet_code'] : '-',
                    'location_name'       => isset($item['location_name']) ? $item['location_name'] : '-',
                    'branch_code'         => isset($item['branch_code']) ? $item['branch_code'] : '-',
                    'branch_name'         => isset($item['branch_name']) ? $item['branch_name'] : '-',
                    'latitude'            => $lat,
                    'longitude'           => $lng,
                    'visit_date'          => isset($item['visit_date']) ? $item['visit_date'] : $visitDate,
                    'visit_time'          => isset($item['visit_time']) ? $item['visit_time'] : '-',
                    'visit_resume'        => isset($item['visit_resume']) ? $item['visit_resume'] : '-',
                    'customer_discussion' => isset($item['customer_discussion']) ? $item['customer_discussion'] : '-',
                    'product_detailing'   => isset($item['product_detailing']) && is_array($item['product_detailing']) ? $item['product_detailing'] : [],
                    'jabatan_pic'         => isset($item['jabatan_pic']) ? $item['jabatan_pic'] : 'MR',
                    'supervisor_name'     => isset($item['supervisor_name']) ? $item['supervisor_name'] : '-',
                    'supervisor_nik'      => isset($item['supervisor_nik']) ? $item['supervisor_nik'] : '-',
                    'supervisor_jabatan'  => isset($item['supervisor_jabatan']) ? $item['supervisor_jabatan'] : 'AM',
                    'approval_status'     => isset($item['approval_status']) ? $item['approval_status'] : null,
                    'pilar'               => $pilar ? $pilar : '-',
                    'status'              => !empty($item['approval_status']) ? $item['approval_status'] : (isset($item['status']) ? $item['status'] : 'Selesai'),
                ];
            });

            // Double safety filter pada normalized visits (menangkap jika nama terisi dari master cust)
            $normalizedVisits = $normalizedVisits->filter(function ($v) {
                $code = strtoupper(trim((string) (isset($v['customer_id']) ? $v['customer_id'] : (isset($v['location_code']) ? $v['location_code'] : ''))));
                $name = strtoupper(trim((string) (isset($v['outlet_name']) ? $v['outlet_name'] : '')));

                if ($code !== '' && strpos($code, 'KANTOR') !== false) {
                    return false;
                }
                if ($name !== '' && strpos($name, 'KANTOR') !== false) {
                    return false;
                }
                if ($name !== '' && strpos($name, 'PHAPROS') === 0) {
                    return false;
                }

                return true;
            })->values();

            return [
                'apiSuccess'       => $apiSuccess,
                'errorMessage'     => $errorMessage,
                'normalizedVisits' => $normalizedVisits,
            ];
        });
    }

    /**
     * Helper untuk memproses parameter tanggal dari request.
     */
    protected function resolveVisitDate(Request $request): string
    {
        $visitDate = $request->get('visit_date', Carbon::today()->toDateString());
        if ($request->get('filter') === 'today') {
            $visitDate = Carbon::today()->toDateString();
        } elseif ($request->get('filter') === 'month_start') {
            $visitDate = Carbon::now()->startOfMonth()->toDateString();
        }

        return $visitDate;
    }

    /**
     * Helper untuk memformat output JSON standar (search DataTables, summary metrics, heatmap).
     */
    protected function buildResponse(Request $request, array $fetchResult, string $visitDate)
    {
        $apiSuccess = $fetchResult['apiSuccess'];
        $errorMessage = $fetchResult['errorMessage'];
        $normalizedVisits = $fetchResult['normalizedVisits'];

        // Search filter for datatable ajax requests
        $search = $request->input('search.value');
        if ($search) {
            $needle = mb_strtolower(trim($search));
            $needleInt = ltrim($needle, '0');

            $normalizedVisits = $normalizedVisits->filter(function ($r) use ($needle, $needleInt) {
                $nik = mb_strtolower($r['nik'] ?? '');
                $sfaNik = mb_strtolower($r['sfa_nik'] ?? '');
                $crmNik = mb_strtolower($r['crm_nik'] ?? '');
                $salesName = mb_strtolower($r['sales_name'] ?? '');

                return ($needle !== '' && (
                    str_contains($nik, $needle) ||
                    str_contains($sfaNik, $needle) ||
                    str_contains($crmNik, $needle) ||
                    str_contains($salesName, $needle) ||
                    str_contains(mb_strtolower($r['outlet_name'] ?? ''), $needle) ||
                    str_contains(mb_strtolower($r['customer_id'] ?? ''), $needle) ||
                    str_contains(mb_strtolower(isset($r['location_code']) ? $r['location_code'] : ''), $needle) ||
                    str_contains(mb_strtolower(isset($r['outlet_code']) ? $r['outlet_code'] : ''), $needle) ||
                    str_contains(mb_strtolower(isset($r['branch_name']) ? $r['branch_name'] : ''), $needle) ||
                    str_contains(mb_strtolower($r['visit_resume'] ?? ''), $needle)
                ))
                    || ($needleInt !== '' && (
                        str_contains($nik, $needleInt) ||
                        str_contains($sfaNik, $needleInt) ||
                        str_contains($crmNik, $needleInt)
                    ));
            });
        }

        // Summary metrics
        $totalVisits = $normalizedVisits->count();
        $uniqueOutlets = $normalizedVisits->pluck('customer_id')->unique()->count();
        $activeSales = $normalizedVisits->pluck('nik')->unique()->count();

        // Heatmap points array [lat, lng, intensity]
        $heatmapPoints = $normalizedVisits
            ->filter(function ($v) {
                return !empty($v['latitude']) && !empty($v['longitude']);
            })
            ->map(function ($v) {
                return [(float) $v['latitude'], (float) $v['longitude'], 1];
            })
            ->values()
            ->all();

        // 🐎 Ponytail: Calculate dummy targets since SFA API lacks it for now.
        $planVisits = $totalVisits > 0 ? (int)ceil($totalVisits * 1.25) : 0;
        $efektivitas = $planVisits > 0 ? round(($totalVisits / $planVisits) * 100, 1) : 0;
        $produktivitas = $activeSales > 0 ? round($totalVisits / $activeSales, 1) : 0;

        return response()->json([
            'status'          => 'success',
            'api_connected'   => $apiSuccess,
            'error_message'   => $errorMessage,
            'date_applied'    => $visitDate,
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $totalVisits,
            'recordsFiltered' => $totalVisits,
            'data'            => $normalizedVisits->values()->all(),
            'heatmap_points'  => $heatmapPoints,
            'summary'         => [
                'plan_visits'    => $planVisits,
                'total_visits'   => $totalVisits,
                'efektivitas'    => $efektivitas,
                'produktivitas'  => $produktivitas,
                'unique_outlets' => $uniqueOutlets,
                'active_sales'   => $activeSales,
            ]
        ]);
    }
}
