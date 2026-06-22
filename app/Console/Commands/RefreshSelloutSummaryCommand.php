<?php

namespace App\Console\Commands;

use App\Services\SelloutSummaryRefreshService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshSelloutSummaryCommand extends Command
{
    protected $signature = 'sellout:refresh-summary {from?} {to?}';
    protected $description = 'Refresh crm_sellout_summary table for a date range.';

    public function handle(SelloutSummaryRefreshService $service)
    {
        $from = $this->argument('from')
            ? Carbon::parse($this->argument('from'))->toDateString()
            : Carbon::yesterday()->toDateString();

        $to = $this->argument('to')
            ? Carbon::parse($this->argument('to'))->toDateString()
            : Carbon::today()->toDateString();

        $this->info("Memulai refresh crm_sellout_summary dari {$from} sampai {$to}...");

        try {
            $service->refreshSummaryRange($from, $to, function (string $chunkFrom, string $chunkTo) {
                $this->line("Memproses {$chunkFrom} sampai {$chunkTo}...");
            });
            $this->info('Refresh crm_sellout_summary selesai.');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Refresh crm_sellout_summary gagal: ' . $e->getMessage());
            Log::error('RefreshSelloutSummaryCommand error', [
                'message' => $e->getMessage(),
                'from' => $from,
                'to' => $to,
            ]);
            return 1;
        }
    }

    // nohup php artisan sellout:refresh-summary 2023-01-01 2026-05-19 > sellout_refresh.log &
    # Cicil tahun 2023 saja dulu
    // php artisan sellout:refresh-summary 2023-01-01 2023-12-31

    # Kemudian tahun 2024
    // php artisan sellout:refresh-summary 2024-01-01 2024-12-31
}
