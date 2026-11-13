<?php

namespace App\Support\SignUp;

use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Optional Cloudflare Turnstile check on sign-up (off by default; Settings → Sign-up). */
class Turnstile
{
    public function passes(string $token, ?string $ip): bool
    {
        if (! PlatformSettings::get('turnstile_enabled')) {
            return true;
        }
        if ($token === '') {
            return false;
        }
        try {
            $answer = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => PlatformSettings::turnstileSecret(),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return (bool) $answer->json('success');
        } catch (Throwable) {
            return false;
        }
    }
}
