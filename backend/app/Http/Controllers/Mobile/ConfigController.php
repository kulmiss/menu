<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;

/**
 * Public start-up settings for the Menu Admin app (QUESTIONS #190): which
 * versions must update, remote feature switches and the links it shows.
 */
class ConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $s = PlatformSettings::all();
        $whatsapp = preg_replace('/\D+/', '', (string) $s['upgrade_whatsapp']);

        return response()->json(['data' => [
            'name' => $s['name'],
            'min_version' => $s['app_min_version'],
            'latest_version' => $s['app_latest_version'],
            'flags' => (object) (is_array($s['app_flags']) ? $s['app_flags'] : []),
            'links' => [
                'privacy' => url('/privacy'),
                'terms' => url('/terms'),
                'forgot_password' => url('/auth/forgot-password'),
                'signup' => url('/auth/sign-up'),
                'web_admin' => url('/admin'),
                'support_whatsapp' => $whatsapp !== '' ? 'https://wa.me/'.$whatsapp : null,
                'support_email' => $s['support_email'] ?: null,
            ],
        ]])->header('Cache-Control', 'public, max-age=300');
    }
}
