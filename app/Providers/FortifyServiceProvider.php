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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Http;
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
            // $turnstileResponse = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            //     'secret' => env('TURNSTILE_SECRET_KEY'),
            //     'response' => $request->input('cf-turnstile-response'),
            //     'remoteip' => $request->ip(),
            // ]);

            // if (!$turnstileResponse->json('success')) {
            //     throw ValidationException::withMessages([
            //         'cf-turnstile-response' => ['Validasi keamanan gagal. Silakan centang kotak verifikasi.'],
            //     ]);
            // }

            $spkUser = DB::connection('db_master')
                ->table('mst_anggota')
                ->where('nik', $request->nik)
                ->first();

            if ($spkUser && Hash::check($request->password, $spkUser->password_hash)) {

                $kode_direktorat = $spkUser->kode_direktorat ?? '';

                //insert new user spk
                $localUser = User::firstOrCreate(['nik' => $spkUser->nik]);

                if ($kode_direktorat === 'DR00003' && $localUser->roles()->count() === 0) {
                    // $localUser->assignRole('mkt');
                    return $localUser;
                } elseif ($localUser->roles()->count() === 0) {
                    return false;
                }

                session([
                    'spk_name' => $spkUser->nama,
                    'spk_jabatan' => $spkUser->ref_nama_jabatan,
                    'spk_email' => $spkUser->email,
                ]);

                return $localUser;
            }
        });
    }
}
