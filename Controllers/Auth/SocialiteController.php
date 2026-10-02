<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialiteController extends Controller
{
    private const PROVIDERS = ['google', 'facebook'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->providerColumn($provider);

        if (! config("services.{$provider}.client_id") || ! config("services.{$provider}.client_secret")) {
            return $this->loginFailure("Login {$provider} belum dikonfigurasi di server.");
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $providerColumn = $this->providerColumn($provider);
        $socialUser = $this->fetchSocialUser($provider);
        $email = $socialUser?->getEmail();
        $providerId = $socialUser?->getId();
        $failureMessage = null;

        if (! $socialUser) {
            $failureMessage = 'Login sosial gagal. Silakan coba lagi.';
        } elseif (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $providerId) {
            $failureMessage = 'Provider tidak memberikan email atau identitas akun yang valid.';
        } elseif ($provider === 'google' && ! ($socialUser->user['email_verified'] ?? false)) {
            $failureMessage = 'Email Google belum terverifikasi.';
        }

        if ($failureMessage) {
            return $this->loginFailure($failureMessage);
        }

        $user = $this->resolveUser($providerColumn, $providerId, $email);

        if (! $user) {
            return $this->loginFailure('Akun ini belum memiliki akses admin atau tidak cocok dengan akun sosial.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function providerColumn(string $provider): string
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        return $provider.'_id';
    }

    private function fetchSocialUser(string $provider): mixed
    {
        try {
            return Socialite::driver($provider)->user();
        } catch (Throwable $exception) {
            Log::warning('Social login failed.', [
                'provider' => $provider,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function resolveUser(string $providerColumn, string $providerId, string $email): ?User
    {
        $user = User::query()->where($providerColumn, $providerId)->first();

        if (! $user) {
            $user = User::query()->where('email', $email)->first();
        }

        if (! $user || ! $user->is_admin) {
            return null;
        }

        if ($user->{$providerColumn} && $user->{$providerColumn} !== $providerId) {
            return null;
        }

        if (! $user->{$providerColumn}) {
            $user->forceFill([
                $providerColumn => $providerId,
            ])->save();
        }

        return $user;
    }

    private function loginFailure(string $message): RedirectResponse
    {
        return to_route('login')->withErrors(['social' => $message]);
    }
}
