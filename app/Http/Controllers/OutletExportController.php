<?php

namespace App\Http\Controllers;

use App\Services\SimpleXlsxExporter;
use App\Services\UnmappedSelloutService;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OutletExportController extends Controller
{
    private UnmappedSelloutService $unmappedService;

    public function __construct(UnmappedSelloutService $unmappedService)
    {
        $this->unmappedService = $unmappedService;
    }

    /**
     * Export unmapped master sellout outlets to XLSX.
     */
    public function exportUnmappedMaster(): BinaryFileResponse
    {
        $outlets = $this->unmappedService->getUnmappedMasterQuery()->get();

        $headers = [
            'Distributor',
            'Kode Cabang',
            'Kode Outlet',
            'Nama Outlet',
            'Segment',
        ];

        $rows = [];
        foreach ($outlets as $outlet) {
            $rows[] = [
                $outlet->dist ?? '',
                $outlet->id_cbg ?? '',
                $outlet->id_outlet ?? '',
                $outlet->nama_outlet ?? '',
                $outlet->segment_dist ?? '',
            ];
        }

        $tempPath = SimpleXlsxExporter::create($headers, $rows, 'Unmapped Outlets');
        $filename = 'Outlet_Sellout_Belum_Termapping_' . Carbon::now()->format('Ymd_His') . '.xlsx';

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
