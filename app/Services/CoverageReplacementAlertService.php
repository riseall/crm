<?php

namespace App\Services;

use App\Models\Mapping;
use App\Models\Product;
use App\Models\Outlet;
use App\Models\User;
use App\Notifications\CoverageNeedsReplacementNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CoverageReplacementAlertService
{
    protected const ALERT_ROLES = ['Admin IT', 'Marketing Insight'];

    public function shouldNotify(array $originalState, Mapping $coverage): bool
    {
        $originalIsActive = array_key_exists('is_active', $originalState)
            ? (bool) $originalState['is_active']
            : true;
        $originalValidTo = $originalState['valid_to'] ?? null;

        $wasAlreadyInAlertState = !$originalIsActive || !empty($originalValidTo);
        $isNowInAlertState = !$coverage->is_active || !empty($coverage->valid_to);

        return !$wasAlreadyInAlertState && $isNowInAlertState;
    }

    public function notify(Mapping $coverage): void
    {
        $outletCount = Outlet::where('id_coverage', $coverage->id_coverage)->count();
        $productCount = Product::where('id_coverage', $coverage->id_coverage)->count();

        if ($outletCount > 0) {
            Outlet::where('id_coverage', $coverage->id_coverage)
                ->update(['needs_replacement' => true]);
        }

        if ($productCount > 0) {
            Product::where('id_coverage', $coverage->id_coverage)
                ->update(['needs_replacement' => true]);
        }

        if ($outletCount === 0 && $productCount === 0) {
            return;
        }

        $recipients = $this->recipients();

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send(
                $recipients,
                new CoverageNeedsReplacementNotification($coverage, $outletCount, $productCount)
            );
        } catch (\Throwable $notificationException) {
            report($notificationException);
            Log::warning('Failed to send coverage replacement notification', [
                'id_coverage' => $coverage->id_coverage,
                'message'     => $notificationException->getMessage(),
            ]);
        }
    }

    public function recipients(): Collection
    {
        return User::role(self::ALERT_ROLES)->get();
    }

    public function pendingAlerts(): Collection
    {
        $latestRows = DB::table('mst_mappings')
            ->selectRaw('MAX(id) as id')
            ->groupBy('id_coverage');

        return Mapping::query()
            ->joinSub($latestRows, 'latest_mappings', function ($join) {
                $join->on('mst_mappings.id', '=', 'latest_mappings.id');
            })
            ->where(function ($query) {
                $query->where('is_active', false)
                    ->orWhereNotNull('valid_to');
            })
            ->withCount(['outlets', 'products'])
            ->orderByDesc('updated_at')
            ->get()
            ->filter(function (Mapping $coverage) {
                return $coverage->outlets_count > 0 || $coverage->products_count > 0;
            })
            ->values();
    }

    public function pendingAlertPayload(): Collection
    {
        return $this->pendingAlerts()->map(function (Mapping $coverage) {
            return [
                'id_coverage'   => $coverage->id_coverage,
                'cov_desc'      => $coverage->cov_desc,
                'cov_nik'       => $coverage->cov_nik,
                'outlet_count'  => $coverage->outlets_count,
                'product_count' => $coverage->products_count,
                'message'       => "Coverage {$coverage->id_coverage} ({$coverage->cov_desc}) perlu diganti. {$coverage->outlets_count} outlet atau {$coverage->products_count} produk butuh coverage baru.",
            ];
        })->values();
    }

    public function alertSignature(): string
    {
        $payload = $this->pendingAlertPayload();

        return sha1($payload->pluck('id_coverage')->implode('|') . '|' . $payload->count());
    }
}
