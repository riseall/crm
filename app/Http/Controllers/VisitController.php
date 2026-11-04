<?php

namespace App\Http\Controllers;

use App\Models\CrmMstCust;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VisitController extends Controller
{
    /**
     * Halaman Utama: Monitoring Kunjungan VS Realisasi
     */
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

        // Tentukan default periode MTD (Month to Date)
        $defaultStartDate = Carbon::now()->startOfMonth()->toDateString();
        $defaultEndDate = Carbon::today()->toDateString();

        // Cek jika bulan ini belum ada data sama sekali, fallback ke bulan data terakhir yang tersedia
        try {
            $latestVisitDate = Cache::remember('dwh_latest_visit_date', 3600, function () use ($defaultStartDate, $defaultEndDate) {
                $hasData = DB::connection('db_dwh')
                    ->table('sfa_call_plan_dashboards')
                    ->whereBetween('visit_date', [$defaultStartDate, $defaultEndDate])
                    ->exists();

                if (!$hasData) {
                    return DB::connection('db_dwh')
                        ->table('sfa_call_plan_dashboards')
                        ->max('visit_date');
                }
                return $defaultEndDate;
            });

            if ($latestVisitDate && $latestVisitDate !== $defaultEndDate) {
                $latest = Carbon::parse($latestVisitDate);
                $defaultStartDate = $latest->copy()->startOfMonth()->toDateString();
                $defaultEndDate = $latest->toDateString();
            }
        } catch (\Exception $e) {
            Log::warning('Gagal cek max visit_date: ' . $e->getMessage());
        }

        // Daftar Divisi & Level sesuai spesifikasi
        $divisis = ['Ethical', 'OTC', 'OGB', 'Reguler'];
        $levels = ['SAM', 'AM', 'MR Ethical', 'MR OGB', 'MR Combo', 'SR OTC'];

        // Daftar Employee (Sales Force) dari users & mappings (Cached 1 jam)
        $employees = Cache::remember('crm_visit_filter_employees', 3600, function () {
            try {
                return DB::table('users')
                    ->where('is_active', 1)
                    ->select('nik', 'name')
                    ->orderBy('name')
                    ->get();
            } catch (\Exception $e) {
                return collect();
            }
        });

        // Daftar Outlet populer / terdata (Cached 1 jam)
        $outlets = Cache::remember('crm_visit_filter_outlets', 3600, function () {
            try {
                return DB::connection('db_dwh')
                    ->table('crm_mst_cust')
                    ->whereNotNull('nama_outlet')
                    ->where('nama_outlet', '!=', '')
                    ->select('id_konversi', 'nama_outlet')
                    ->orderBy('nama_outlet')
                    ->limit(500)
                    ->get();
            } catch (\Exception $e) {
                return collect();
            }
        });

        return view('page.visit.index', [
            'pilars'           => ['ETHIPROM', 'ETHICOM', 'OGB', 'OTC'],
            'divisis'          => $divisis,
            'levels'           => $levels,
            'employees'        => $employees,
            'outlets'          => $outlets,
            'defaultStartDate' => $defaultStartDate,
            'defaultEndDate'   => $defaultEndDate,
            'anggota'          => $anggota,
            'initialNik'       => $request->nik
        ]);
    }

    /**
     * Endpoint Data: Kunjungan VS Realisasi Selling Out
     */
    public function getVisitData(Request $request)
    {
        return $this->getVisitRealizationData($request);
    }

    /**
     * Endpoint Data Spesifik Sales
     */
    public function getVisitDataBySales(Request $request)
    {
        return $this->getVisitRealizationData($request);
    }

    /**
     * Endpoint Data Rincian Dokter/User yang dikunjungi di suatu Outlet (Lazy-Loaded via AJAX)
     */
    public function getDoctorVisitDetails(Request $request)
    {
        $locationCode = $request->input('location_code');
        if (empty($locationCode)) {
            return response()->json(['status' => 'error', 'message' => 'location_code is required', 'data' => []]);
        }

        [$startDate, $endDate] = $this->resolveDateRange($request);
        $employee = $request->input('employee') ?: $request->input('nik');

        $cacheKey = 'doc_details_' . md5($locationCode . '|' . $startDate . '|' . $endDate . '|' . ($employee ?? ''));

        try {
            $doctors = Cache::remember($cacheKey, 600, function () use ($locationCode, $startDate, $endDate, $employee) {
                $query = DB::connection('db_dwh')
                    ->table('sfa_call_plan_dashboards')
                    ->where('location_code', $locationCode)
                    ->whereBetween('visit_date', [$startDate, $endDate])
                    ->where('actual_approval_status', 'APPROVED')
                    ->where('visit_status', 'VISITED');

                if (!empty($employee) && $employee !== 'All') {
                    $cleanNik = ltrim($employee, '0');
                    if (is_numeric($cleanNik) || preg_match('/^[0-9A-Za-z]+$/', $employee)) {
                        $query->whereIn('pic_nik', array_unique([$employee, $cleanNik]));
                    }
                }

                $raw = $query->select([
                    'outlet_name',
                    'customer_channel_name',
                    'is_doctor',
                    'visit_date'
                ])->get();

                $docs = [];
                foreach ($raw as $row) {
                    $name = trim((string)($row->outlet_name ?? ''));
                    $isDoctor = (bool)($row->is_doctor ?? false);
                    $hasDoctorTitle = (strpos(strtolower($name), 'dr.') !== false || strpos(strtolower($name), 'sp.') !== false);

                    if ($name !== '' && ($isDoctor || $hasDoctorTitle)) {
                        if (!isset($docs[$name])) {
                            $docs[$name] = [
                                'user_name'       => $name,
                                'specialist'      => !empty($row->customer_channel_name) ? trim($row->customer_channel_name) : '-',
                                'visit_count'     => 0,
                                'last_visit_date' => $row->visit_date ?? '-',
                            ];
                        }
                        $docs[$name]['visit_count'] += 1;
                        if (!empty($row->visit_date) && $row->visit_date > $docs[$name]['last_visit_date']) {
                            $docs[$name]['last_visit_date'] = $row->visit_date;
                        }
                    }
                }

                return array_values($docs);
            });

            return response()->json([
                'status' => 'success',
                'data'   => $doctors,
            ]);
        } catch (\Exception $e) {
            Log::warning('Error in getDoctorVisitDetails: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Query & normalisasi data Kunjungan VS Realisasi Selling Out
     * Berbasis tabel lokal DWH sfa_call_plan_dashboards & crm_sellout_summary
     */
    public function getVisitRealizationData(Request $request)
    {
        // Amankan alokasi memori PHP untuk query rentang tanggal panjang (misal 1 tahun)
        @ini_set('memory_limit', '512M');

        // 1. Resolusi rentang tanggal
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $divisi = $request->get('divisi');
        $level = $request->get('level');
        $employee = $request->get('employee') ?: $request->get('nik');
        $outlet = $request->get('outlet');

        // Cache key unik per kombinasi filter
        $cacheParams = md5(implode('|', [
            $startDate,
            $endDate,
            $divisi ?? '',
            $level ?? '',
            $employee ?? '',
            $outlet ?? ''
        ]));
        $cacheKey = 'visit_realization_v1_' . $cacheParams;
        $ttl = 600; // 10 menit

        $result = Cache::remember($cacheKey, $ttl, function () use ($startDate, $endDate, $divisi, $level, $employee, $outlet) {
            try {
                // 2. Query kunjungan dari sfa_call_plan_dashboards
                $query = DB::connection('db_dwh')
                    ->table('sfa_call_plan_dashboards')
                    ->whereBetween('visit_date', [$startDate, $endDate])
                    ->where('actual_approval_status', 'APPROVED')
                    ->where('visit_status', 'VISITED');

                // Pengecualian lokasi absensi kantor internal
                $query->where(function ($q) {
                    $q->whereNull('location_name')->orWhere('location_name', 'not like', '%KANTOR%');
                })->where(function ($q) {
                    $q->whereNull('outlet_name')->orWhere('outlet_name', 'not like', '%KANTOR%');
                })->where(function ($q) {
                    $q->whereNull('location_name')->orWhere('location_name', 'not like', 'PHAPROS%');
                })->where(function ($q) {
                    $q->whereNull('outlet_name')->orWhere('outlet_name', 'not like', 'PHAPROS%');
                });

                // Filter Level
                if (!empty($level) && $level !== 'All') {
                    $this->applyLevelFilter($query, $level);
                }

                // Filter Divisi
                if (!empty($divisi) && $divisi !== 'All') {
                    $query->where('pilar_name', 'like', '%' . $divisi . '%');
                }

                // Filter Employee (NIK atau Nama)
                if (!empty($employee) && $employee !== 'All') {
                    $cleanNik = ltrim($employee, '0');
                    if (is_numeric($cleanNik) || preg_match('/^[0-9A-Za-z]+$/', $employee)) {
                        $query->whereIn('pic_nik', array_unique([$employee, $cleanNik]));
                    } else {
                        $query->where('pic_nama', 'like', '%' . $employee . '%');
                    }
                }

                // Filter Outlet (Kode atau Nama)
                if (!empty($outlet) && $outlet !== 'All') {
                    $query->where('location_code', $outlet);
                }

                $columns = [
                    'location_code',
                    'location_name',
                    'outlet_name',
                    'is_doctor',
                    'customer_channel_name',
                    'visit_date',
                    'visit_latitude',
                    'visit_longitude',
                    'pic_nik',
                    'pic_nama',
                    'pic_jabatan',
                    'pilar_name',
                ];

                $rawVisits = $query->select($columns)->get();

                // 3. Ambil data Selling Out dari crm_sellout_summary
                $locationCodes = $rawVisits->pluck('location_code')->filter()->unique()->values()->all();
                $selloutMap = $this->fetchSelloutSummaryMap($locationCodes, $startDate, $endDate);

                // 4. Agregasi untuk Tabel, Map, dan KPI Cards
                return $this->aggregateVisitRealization($rawVisits, $selloutMap, $startDate, $endDate);
            } catch (\Exception $e) {
                Log::error('VisitController::getVisitRealizationData error: ' . $e->getMessage());
                return [
                    'summary'     => [
                        'jumlah_user_terkunjungi' => 0,
                        'total_realisasi'         => 0,
                        'produktivitas_per_level' => 0,
                        'total_produktivitas_ff'  => 0,
                        'jumlah_ff'               => 0,
                    ],
                    'map_markers' => [],
                    'table_data'  => [],
                    'error'       => $e->getMessage()
                ];
            }
        });

        // 5. Search filter opsional untuk DataTables AJAX request
        $search = $request->input('search.value');
        $tableData = $result['table_data'];
        if (!empty($search)) {
            $needle = mb_strtolower(trim($search));
            $tableData = array_values(array_filter($tableData, function ($r) use ($needle) {
                return strpos(mb_strtolower($r['employee'] ?? ''), $needle) !== false
                    || strpos(mb_strtolower($r['outlet'] ?? ''), $needle) !== false
                    || strpos(mb_strtolower($r['nik'] ?? ''), $needle) !== false;
            }));
        }

        return response()->json([
            'status'          => 'success',
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => count($result['table_data']),
            'recordsFiltered' => count($tableData),
            'summary'         => $result['summary'],
            'map_markers'     => $result['map_markers'],
            'table_data'      => $tableData,
            'filters'         => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ]
        ]);
    }

    /**
     * Resolusi tanggal dari request: prioritas date range start_date & end_date,
     * fallback visit_date tunggal, atau default MTD.
     */
    protected function resolveDateRange(Request $request): array
    {
        if ($request->filled('start_date') && $request->filled('end_date')) {
            return [
                Carbon::parse($request->input('start_date'))->toDateString(),
                Carbon::parse($request->input('end_date'))->toDateString(),
            ];
        }

        if ($request->filled('visit_date')) {
            $date = Carbon::parse($request->input('visit_date'))->toDateString();
            return [$date, $date];
        }

        // Default MTD
        $startDate = Carbon::now()->startOfMonth()->toDateString();
        $endDate = Carbon::today()->toDateString();

        try {
            $latestVisitDate = Cache::remember('dwh_latest_visit_date', 3600, function () use ($startDate, $endDate) {
                $hasData = DB::connection('db_dwh')
                    ->table('sfa_call_plan_dashboards')
                    ->whereBetween('visit_date', [$startDate, $endDate])
                    ->exists();

                if (!$hasData) {
                    return DB::connection('db_dwh')
                        ->table('sfa_call_plan_dashboards')
                        ->max('visit_date');
                }
                return $endDate;
            });

            if ($latestVisitDate && $latestVisitDate !== $endDate) {
                $latest = Carbon::parse($latestVisitDate);
                $startDate = $latest->copy()->startOfMonth()->toDateString();
                $endDate = $latest->toDateString();
            }
        } catch (\Exception $e) {
            // Silence exception, keep default
        }

        return [$startDate, $endDate];
    }

    /**
     * Terapkan filter Level ke query builder
     */
    protected function applyLevelFilter($query, string $level): void
    {
        switch ($level) {
            case 'SAM':
                $query->where(function ($q) {
                    $q->where('pic_jabatan', 'SAM')->orWhere('pic_jabatan', 'like', '%SENIOR AREA MANAGER%');
                });
                break;
            case 'AM':
                $query->where(function ($q) {
                    $q->where('pic_jabatan', 'AM')->orWhere('pic_jabatan', 'like', '%AREA MANAGER%');
                });
                break;
            case 'MR Ethical':
                $query->where(function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('pic_jabatan', 'MR')->orWhere('pic_jabatan', 'like', '%MEDICAL%');
                    })->where('pilar_name', 'like', '%ETHIC%');
                });
                break;
            case 'MR OGB':
                $query->where(function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('pic_jabatan', 'MR')->orWhere('pic_jabatan', 'like', '%MEDICAL%');
                    })->where('pilar_name', 'like', '%OGB%');
                });
                break;
            case 'MR Combo':
                $query->where(function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('pic_jabatan', 'MR')->orWhere('pic_jabatan', 'like', '%MEDICAL%');
                    })->where(function ($sq) {
                        $sq->where('pilar_name', 'like', '%COMBO%')
                            ->orWhere('pilar_name', 'like', '%ETHIC%')
                            ->orWhere('pilar_name', 'like', '%OGB%');
                    });
                });
                break;
            case 'SR OTC':
                $query->where(function ($q) {
                    $q->where('pic_jabatan', 'SR')
                        ->orWhere('pic_jabatan', 'like', '%SALES REP%')
                        ->orWhere('pilar_name', 'like', '%OTC%');
                });
                break;
        }
    }

    /**
     * Query batch data Selling Out dari crm_sellout_summary
     */
    protected function fetchSelloutSummaryMap(array $locationCodes, string $startDate, string $endDate): array
    {
        if (empty($locationCodes)) {
            return [];
        }

        $selloutMap = [];
        try {
            $selloutQuery = DB::connection('db_dwh')
                ->table('crm_sellout_summary')
                ->whereBetween('tgl_faktur', [$startDate, $endDate]);

            // Jika lokasi sedikit (misal filter cabang/sales tertentu), query whereIn langsung di SQL.
            // Jika lokasi banyak/semua, biarkan tgl_faktur (yang berindeks) yang scan di SQL,
            // lalu filter di PHP dengan O(1) hash lookup untuk menghindari ribuan parameter in-clause.
            $filterInSql = count($locationCodes) <= 300;
            if ($filterInSql) {
                $selloutQuery->whereIn('id_konversi', $locationCodes);
            }

            $selloutRows = $selloutQuery
                ->select('id_konversi', 'pilar_sub', DB::raw('SUM(netto) as total_netto'))
                ->groupBy('id_konversi', 'pilar_sub')
                ->get();

            $locLookup = $filterInSql ? null : array_flip($locationCodes);

            foreach ($selloutRows as $sr) {
                $loc = (string) $sr->id_konversi;
                if ($locLookup !== null && !isset($locLookup[$loc])) {
                    continue;
                }

                $pilar = trim((string) $sr->pilar_sub);
                $netto = (float) $sr->total_netto;

                if (!isset($selloutMap[$loc])) {
                    $selloutMap[$loc] = [
                        'total_netto' => 0.0,
                        'breakdown'   => [
                            'Ethi-KOM'  => 0.0,
                            'Ethi-PROM' => 0.0,
                            'OGB'       => 0.0,
                            'OTC'       => 0.0,
                        ]
                    ];
                }

                $selloutMap[$loc]['total_netto'] += $netto;
                if (isset($selloutMap[$loc]['breakdown'][$pilar])) {
                    $selloutMap[$loc]['breakdown'][$pilar] += $netto;
                } else {
                    $selloutMap[$loc]['breakdown'][$pilar] = $netto;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error querying crm_sellout_summary: ' . $e->getMessage());
        }

        return $selloutMap;
    }

    /**
     * Agregasi data kunjungan dan selling out ke format UI
     */
    protected function aggregateVisitRealization($rawVisits, array $selloutMap, string $startDate, string $endDate): array
    {
        $tableGroups = [];
        $allVisitedDoctors = [];
        $allFfNiks = [];

        foreach ($rawVisits as $row) {
            $emp = !empty($row->pic_nama) ? trim($row->pic_nama) : ($row->pic_nik ?? 'Sales Force');
            $outlet = !empty($row->location_name) ? trim($row->location_name) : ($row->outlet_name ?? 'Outlet');
            $locCode = $row->location_code ? trim((string)$row->location_code) : null;
            $key = $emp . '||' . $outlet;

            if (!empty($row->pic_nik)) {
                $allFfNiks[$row->pic_nik] = true;
            }

            if (!isset($tableGroups[$key])) {
                $sellingOutData = ($locCode && isset($selloutMap[$locCode])) ? $selloutMap[$locCode] : null;
                $realisasiNetto = $sellingOutData ? $sellingOutData['total_netto'] : 0.0;
                $breakdown = $sellingOutData ? $sellingOutData['breakdown'] : [];

                $tableGroups[$key] = [
                    'employee'              => $emp,
                    'nik'                   => $row->pic_nik ?? '-',
                    'outlet'                => $outlet,
                    'location_code'         => $locCode,
                    'latitude'              => (float) ($row->visit_latitude ?? 0),
                    'longitude'             => (float) ($row->visit_longitude ?? 0),
                    'total_kunjungan'       => 0,
                    'realisasi_selling_out' => $realisasiNetto,
                    'selling_breakdown'     => $breakdown,
                    'doctor_count'          => 0,
                    'users_visited'         => [],
                    '_doctor_names'         => [],
                    '_map_user_labels'      => [],
                ];
            }

            $tableGroups[$key]['total_kunjungan'] += 1;

            // Jika kunjungan dokter/user (is_doctor = 1 atau nama mengandung indikasi dokter)
            $isDoctor = (bool) ($row->is_doctor ?? false);
            $doctorName = trim((string) ($row->outlet_name ?? ''));

            $hasDoctorTitle = (strpos(strtolower($doctorName), 'dr.') !== false || strpos(strtolower($doctorName), 'sp.') !== false);
            if ($doctorName !== '' && ($isDoctor || $hasDoctorTitle)) {
                $allVisitedDoctors[$doctorName] = true;
                $tableGroups[$key]['_doctor_names'][$doctorName] = true;

                $uLabel = $doctorName . (!empty($row->customer_channel_name) && trim($row->customer_channel_name) !== '-' ? ', ' . trim($row->customer_channel_name) : '');
                $tableGroups[$key]['_map_user_labels'][$uLabel] = true;
            }
        }

        // Agregasi titik peta unik
        $mapMarkers = [];
        foreach ($tableGroups as $tg) {
            $loc = $tg['outlet'];
            $lat = $tg['latitude'];
            $lng = $tg['longitude'];
            if (empty($lat) || empty($lng) || ($lat == 0.0 && $lng == 0.0)) {
                continue;
            }

            $mapKey = !empty($tg['location_code']) ? $tg['location_code'] : ($lat . '_' . $lng);
            if (!isset($mapMarkers[$mapKey])) {
                $mapMarkers[$mapKey] = [
                    'outlet_name'       => $loc,
                    'latitude'          => $lat,
                    'longitude'         => $lng,
                    'total_kunjungan'   => 0,
                    'selling_out_total' => $tg['realisasi_selling_out'],
                    'selling_breakdown' => $tg['selling_breakdown'],
                    'users'             => [],
                ];
            }

            $mapMarkers[$mapKey]['total_kunjungan'] += $tg['total_kunjungan'];
            if (!empty($tg['_map_user_labels'])) {
                foreach ($tg['_map_user_labels'] as $uLabel => $v) {
                    $mapMarkers[$mapKey]['users'][$uLabel] = true;
                }
            }
        }

        foreach ($mapMarkers as &$mm) {
            $mm['users'] = array_keys($mm['users']);
        }
        unset($mm);

        // Summary KPI Cards
        $jumlahUserTerkunjungi = count($allVisitedDoctors);

        $visitedUniqueOutlets = collect($tableGroups)->pluck('location_code')->filter()->unique()->all();
        $totalRealisasi = 0.0;
        foreach ($visitedUniqueOutlets as $locCode) {
            if (isset($selloutMap[$locCode])) {
                $totalRealisasi += (float) $selloutMap[$locCode]['total_netto'];
            }
        }

        $jumlahFfFiltered = count($allFfNiks);
        $produktivitasPerLevel = $jumlahFfFiltered > 0 ? ($totalRealisasi / $jumlahFfFiltered) : 0.0;

        // Subtitle: Total produktivitas FF (SAM, AM, dan MR)
        $totalFfCompany = Cache::remember('crm_total_ff_company', 86400, function () use ($jumlahFfFiltered) {
            try {
                $cnt = DB::table('users')->where('is_active', 1)->where(function ($q) {
                    $q->where('jabatan', 'like', '%SAM%')
                        ->orWhere('jabatan', 'like', '%AM%')
                        ->orWhere('jabatan', 'like', '%MR%')
                        ->orWhere('jabatan', 'like', '%SR%')
                        ->orWhere('jabatan', 'like', '%MANAGER%')
                        ->orWhere('jabatan', 'like', '%REPRESENTATIVE%');
                })->count();
                return $cnt > 0 ? $cnt : max($jumlahFfFiltered, 1);
            } catch (\Exception $e) {
                return max($jumlahFfFiltered, 1);
            }
        });
        $totalProduktivitasFf = $totalRealisasi / max($totalFfCompany, 1);

        $tableDataList = [];
        foreach ($tableGroups as $tg) {
            $tg['doctor_count'] = count($tg['_doctor_names']);
            unset($tg['_doctor_names'], $tg['_map_user_labels']);
            $tableDataList[] = $tg;
        }

        return [
            'summary'     => [
                'jumlah_user_terkunjungi' => $jumlahUserTerkunjungi,
                'total_realisasi'         => $totalRealisasi,
                'produktivitas_per_level' => $produktivitasPerLevel,
                'total_produktivitas_ff'  => $totalProduktivitasFf,
                'jumlah_ff'               => $jumlahFfFiltered,
            ],
            'map_markers' => array_values($mapMarkers),
            'table_data'  => $tableDataList,
        ];
    }
}
