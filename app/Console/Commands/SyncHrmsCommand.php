<?php

namespace App\Console\Commands;

use App\Models\Mapping;
use App\Models\User;
use App\Services\CoverageReplacementAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncHrmsCommand extends Command
{
    protected $signature   = 'hrms:sync';
    protected $description = 'Sync data anggota dari HRMS API dan cek perubahan is_active';

    public function handle()
    {
        $this->info('Memulai sync HRMS...');

        try {
            // 1. Hit API HRMS
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.hrms.token'),
            ])->get(config('services.hrms.url') . '/api/anggota');

            if (!$response->successful()) {
                $this->error('Gagal hit API HRMS: ' . $response->status());
                Log::error('HRMS Sync failed', ['status' => $response->status()]);
                return 1;
            }

            $anggotaList = $response->json('data');
            $this->info('Total anggota dari HRMS: ' . count($anggotaList));

            $deactivatedNiks = [];
            $activatedNiks = [];
            $inactiveNiks = [];
            $today = now()->toDateString();

            // 2. Loop dan update tabel users
            foreach ($anggotaList as $anggota) {
                $nik       = $anggota['nik'];
                $isActive  = $this->hrmsIsActive($anggota);

                if ((int) $isActive === 0) {
                    $inactiveNiks[] = $nik;
                }

                // Cek apakah user sudah ada di local
                $localUser = User::where('nik', $nik)->first();

                if ($localUser) {
                    $wasActive = $localUser->is_active ?? 1;

                    // Update data user
                    $localUser->update([
                        'name'      => $anggota['nama'],
                        'email'     => $anggota['email'] ?? $localUser->email,
                        'jabatan'   => $anggota['ref_nama_jabatan'] ?? $localUser->jabatan,
                        'is_active' => $isActive,
                        'tanggal_masuk' => $this->hrmsDate($anggota, ['tanggal_masuk', 'tgl_masuk', 'date_in', 'join_date']),
                        'tanggal_keluar' => $this->hrmsDate($anggota, ['tanggal_keluar', 'tgl_keluar', 'date_out', 'resign_date']),
                    ]);

                    // 3. Deteksi perubahan is_active menjadi 0
                    if ($wasActive == 1 && $isActive == 0) {
                        $deactivatedNiks[] = $nik;
                        $this->warn("NIK {$nik} ({$anggota['nama']}) terdeteksi resign/nonaktif.");
                    }

                    if ($wasActive == 0 && $isActive == 1) {
                        $activatedNiks[] = $nik;
                        $this->info("NIK {$nik} ({$anggota['nama']}) aktif kembali.");
                    }
                }
                // Jika belum pernah login, skip (user dibuat saat login)
            }

            // 4. Proses coverage yang perlu di-flag
            $niksToProcess = array_values(array_unique(array_merge($deactivatedNiks, $inactiveNiks)));

            if (!empty($niksToProcess)) {
                $this->info('Memproses ' . count($niksToProcess) . ' NIK nonaktif...');
                $alertService = app(CoverageReplacementAlertService::class);

                foreach ($niksToProcess as $nik) {
                    $closedCoverages = $this->closeActiveCoveragesForNik($nik, $today);

                    foreach ($closedCoverages as $item) {
                        $coverage = $item['coverage'];
                        $originalState = $item['original_state'];

                        if ($alertService->shouldNotify($originalState, $coverage)) {
                            $alertService->notify($coverage);
                        }

                        $outletCount = $coverage->outlets()->count();
                        $productCount = $coverage->products()->count();
                        $this->info("Coverage {$coverage->id_coverage} ditutup otomatis. {$outletCount} outlet dan {$productCount} produk butuh pengganti.");
                        Log::info("HRMS Sync: Coverage {$coverage->id_coverage} closed for NIK {$nik}");
                    }
                }
            }

            $niksToActivate = array_values(array_unique($activatedNiks));

            if (!empty($niksToActivate)) {
                $this->info('Memproses ' . count($niksToActivate) . ' NIK aktif...');
                $alertService = app(CoverageReplacementAlertService::class);

                foreach ($niksToActivate as $nik) {
                    $openedCoverages = $this->openCoveragesForNik($nik);

                    foreach ($openedCoverages as $coverage) {
                        $alertService->syncReplacementFlag($coverage);
                        $this->info("Coverage {$coverage->id_coverage} disesuaikan aktif untuk NIK {$nik}.");
                    }
                }
            }

            $this->info('Sync HRMS selesai.');
            Log::info('HRMS Sync completed', [
                'total'       => count($anggotaList),
                'deactivated' => count($deactivatedNiks),
                'activated'   => count($activatedNiks),
                'inactive'    => count($inactiveNiks),
            ]);
            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('HRMS Sync error', ['message' => $e->getMessage()]);
            return 1;
        }
    }

    /**
     * Tutup semua coverage aktif milik NIK tertentu dan kembalikan daftar coverage yang diubah.
     */
    protected function closeActiveCoveragesForNik(string $nik, string $today): Collection
    {
        $activeCoverages = Mapping::where('cov_nik', $nik)
            ->where('is_active', true)
            ->get();

        $activeCoverages->transform(function ($coverage) use ($today) {
            $originalState = $coverage->only(['is_active', 'valid_to']);

            $coverage->update([
                'valid_to'  => $today,
                'is_active' => false,
            ]);

            return [
                'coverage' => $coverage,
                'original_state' => $originalState,
            ];
        });

        return $activeCoverages;
    }

    protected function openCoveragesForNik(string $nik): Collection
    {
        $coverages = Mapping::where('cov_nik', $nik)
            ->where('is_active', false)
            ->get();

        $coverages->each(function ($coverage) {
            $coverage->update([
                'valid_to'  => null,
                'is_active' => true,
            ]);
        });

        return $coverages;
    }

    protected function hrmsIsActive(array $anggota): int
    {
        if (array_key_exists('is_active', $anggota)) {
            return (int) $anggota['is_active'] === 1 ? 1 : 0;
        }

        if (array_key_exists('status_hapus', $anggota)) {
            return (int) $anggota['status_hapus'] === 1 ? 1 : 0;
        }

        return 1;
    }

    protected function hrmsDate(array $anggota, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!empty($anggota[$key])) {
                return date('Y-m-d', strtotime($anggota[$key]));
            }
        }

        return null;
    }
}
