<?php

namespace App\Console\Commands;

use App\Services\StockGpjService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncStockGpjCommand extends Command
{
    protected $signature = 'qad:sync-stock-gpj';
    protected $description = 'Sync stock PH inventory GPJ from QAD WSA into stock_ph_in_gpj.';

    public function handle(StockGpjService $service): int
    {
        $this->info('Memulai sync stock PH in GPJ dari QAD WSA...');

        try {
            $count = $service->syncInventoryGpj();

            $this->info("Sync stock PH in GPJ selesai. {$count} row diproses.");
            Log::info('SyncStockGpjCommand completed', [
                'rows' => $count,
            ]);

            return 0;
        } catch (\Throwable $e) {
            $this->error('Sync stock PH in GPJ gagal: ' . $e->getMessage());
            Log::error('SyncStockGpjCommand error', [
                'message' => $e->getMessage(),
            ]);

            return 1;
        }
    }
}
