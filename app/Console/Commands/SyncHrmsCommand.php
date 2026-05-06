<?php

namespace App\Console\Commands;

use App\Models\Mapping;
use App\Models\Outlet;
use App\Models\User;
use App\Notifications\CoverageNeedsReplacementNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

            // 2. Loop dan update tabel users
            foreach ($anggotaList as $anggota) {
                $nik       = $anggota['nik'];
                $isActive  = $anggota['is_active'] ?? 1;

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
                    ]);

                    // 3. Deteksi perubahan is_active menjadi 0
                    if ($wasActive == 1 && $isActive == 0) {
                        $deactivatedNiks[] = $nik;
                        $this->warn("NIK {$nik} ({$anggota['nama']}) terdeteksi resign/nonaktif.");
                    }
                }
                // Jika belum pernah login, skip (user dibuat saat login)
            }

            // 4. Proses coverage yang perlu di-flag
            if (!empty($deactivatedNiks)) {
                $this->info('Memproses ' . count($deactivatedNiks) . ' NIK nonaktif...');
                $admins = User::role('admin')->get();

                foreach ($deactivatedNiks as $nik) {
                    // Cari coverage aktif dengan NIK ini
                    $activeCoverages = Mapping::where('cov_nik', $nik)
                        ->where('is_active', true)
                        ->get();

                    foreach ($activeCoverages as $coverage) {
                        // Flag outlet terkait
                        $outletCount = Outlet::where('id_coverage', $coverage->id_coverage)
                            ->update(['needs_replacement' => true]);

                        // Kirim notifikasi ke semua admin
                        Notification::send($admins, new CoverageNeedsReplacementNotification(
                            $coverage,
                            Outlet::where('id_coverage', $coverage->id_coverage)->count()
                        ));

                        $this->info("Coverage {$coverage->id_coverage} di-flag. {$outletCount} outlet butuh pengganti.");
                        Log::info("HRMS Sync: Coverage {$coverage->id_coverage} flagged for NIK {$nik}");
                    }
                }
            }

            $this->info('Sync HRMS selesai.');
            Log::info('HRMS Sync completed', ['total' => count($anggotaList), 'deactivated' => count($deactivatedNiks)]);
            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('HRMS Sync error', ['message' => $e->getMessage()]);
            return 1;
        }
    }
}
