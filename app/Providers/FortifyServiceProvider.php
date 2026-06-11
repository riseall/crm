<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Illuminate\Validation\ValidationException;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        //login
        Fortify::loginView(function () {
            return view('auth.login');
        });

        Fortify::authenticateUsing(function (Request $request) {
            $turnstileResponse = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => env('TURNSTILE_SECRET_KEY'),
                'response' => $request->input('cf-turnstile-response'),
                'remoteip' => $request->ip(),
            ]);

            if (!$turnstileResponse->json('success')) {
                throw ValidationException::withMessages([
                    'cf-turnstile-response' => ['Validasi keamanan gagal. Silakan centang kotak verifikasi.'],
                ]);
            }

            $spkUser = DB::connection('db_master')
                ->table('mst_anggota')
                ->where('nik', $request->input('nik'))
                ->first();

            if ($spkUser && Hash::check($request->input('password'), $spkUser->password_hash)) {

                $kode_direktorat = $spkUser->kode_direktorat ?? '';
                $kode_kelompok_jabatan = $spkUser->kode_kelompok_jabatan ?? '';
                $kode_divisi = $spkUser->kode_divisi ?? '';
                $kode_departemen = $spkUser->kode_departemen ?? '';

                // Tentukan Role ID-nya dulu di variabel (Default null)
                $targetRoleId = null;

                if ($kode_divisi === 'DV00004') {
                    $targetRoleId = 2;
                } elseif ($kode_direktorat === 'DR00003') {
                    if ($kode_divisi === 'DV00022') {
                        $targetRoleId = 3;
                    } elseif ($kode_divisi === 'DV00021') {
                        if ($kode_kelompok_jabatan === 'KJ00007') {
                            $targetRoleId = 4;
                        } elseif ($kode_kelompok_jabatan === 'KJ00004') {
                            $targetRoleId = 6;
                        }
                    } else {
                        if ($kode_kelompok_jabatan === 'KJ00007') {
                            $targetRoleId = 4;
                        } elseif ($kode_kelompok_jabatan === 'KJ00006') {
                            $targetRoleId = 5;
                        } elseif ($kode_kelompok_jabatan === 'KJ00005') {
                            $targetRoleId = 7;
                        } elseif ($kode_kelompok_jabatan === 'KJ00003') {
                            $targetRoleId = 8;
                        } elseif ($kode_kelompok_jabatan === 'KJ00002') {
                            $targetRoleId = ($kode_departemen === 'DP00059') ? 10 : 9;
                        }
                    }
                }

                // Jika tidak ada role yang cocok, JANGAN simpan ke database
                if (is_null($targetRoleId)) {
                    throw ValidationException::withMessages([
                        'nik' => [__('auth.no_role_access')],
                    ]);
                }

                // Jika lolos (punya role), baru simpan/update user
                $localUser = User::updateOrCreate(
                    ['nik' => $spkUser->nik],
                    [
                        'name' => $spkUser->nama,
                        'email' => $spkUser->email,
                        'jabatan' => $spkUser->ref_nama_jabatan,
                        'is_active' => $this->hrmsIsActive((array) $spkUser),
                        'tanggal_masuk' => $this->hrmsDate((array) $spkUser, ['tanggal_masuk', 'tgl_masuk', 'date_in', 'join_date']),
                        'tanggal_keluar' => $this->hrmsDate((array) $spkUser, ['tanggal_keluar', 'tgl_keluar', 'date_out', 'resign_date']),
                    ]
                );

                // Sinkronisasi Role (Hanya assign jika belum punya)
                if ($localUser->roles()->count() === 0) {
                    $localUser->assignRole($targetRoleId);
                }

                session([
                    'spk_name' => $spkUser->nama,
                    'spk_jabatan' => $spkUser->ref_nama_jabatan,
                    'spk_email' => $spkUser->email,
                ]);

                return $localUser;
            }

            return null;
        });
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
