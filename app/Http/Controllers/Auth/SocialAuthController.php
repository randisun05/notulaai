<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Login SSO (Google/Microsoft) khusus untuk akun yang SUDAH terdaftar,
 * dicocokkan lewat email — konsisten dengan keputusan menonaktifkan
 * self-registration: SSO tidak pernah membuat user baru secara diam-diam.
 */
class SocialAuthController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'microsoft'];

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::SUPPORTED_PROVIDERS, true), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::SUPPORTED_PROVIDERS, true), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            Log::warning("Login SSO ({$provider}) gagal: {$e->getMessage()}");

            return redirect()->route('login')->with('error', 'Login SSO gagal, silakan coba lagi.');
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Akun dengan email tersebut belum terdaftar. Hubungi admin untuk didaftarkan terlebih dahulu.');
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard'));
    }
}
