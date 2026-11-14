<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Support\Features;
use App\Support\MobileApp;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;

/**
 * Public start-up settings for the Menu Admin app (QUESTIONS #190, #193):
 * who may sign in, maintenance, versions, the banner, the platform's feature
 * switches, app-only switches and links. The app caches the answer and reads
 * it at start and when it comes back to the foreground; it never waits for it.
 */
class ConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $s = PlatformSettings::all();
        $m = MobileApp::settings();
        $whatsapp = preg_replace('/\D+/', '', (string) ($m['links']['support_whatsapp'] ?: $s['upgrade_whatsapp']));
        $legacy = is_array($s['app_flags']) ? $s['app_flags'] : [];
        $web = url('/admin');

        return response()->json(['data' => [
            'name' => $s['name'],
            // Kept for app versions before #193.
            'min_version' => $m['min_version'],
            'latest_version' => $m['latest_version'],
            'flags' => (object) [...$legacy, ...$m['app_features']],
            'versions' => [
                'latest' => $m['latest_version'],
                'min' => $m['min_version'],
                'recommended' => $m['recommended_version'] ?: null,
                'notes' => $m['release_notes'] ?: null,
            ],
            'sign_in' => [
                'restaurant' => (bool) $m['restaurant_sign_in'],
                'super_admin' => (bool) $m['super_admin_sign_in'],
                'message' => $m['sign_in_message'] ?: null,
            ],
            'maintenance' => ['on' => (bool) $m['maintenance'], 'message' => $m['maintenance_message'] ?: null],
            'banner' => MobileApp::activeBanner(),
            'features' => (object) Features::global(),
            'app_features' => (object) $m['app_features'],
            'links' => [
                'privacy' => $m['links']['privacy'] ?: url('/privacy'),
                'terms' => $m['links']['terms'] ?: url('/terms'),
                'forgot_password' => url('/auth/forgot-password'),
                'signup' => url('/auth/sign-up'),
                'web_admin' => $web,
                'play_store' => $m['links']['play_store'] ?: null,
                'app_store' => $m['links']['app_store'] ?: null,
                'support_whatsapp' => $whatsapp !== '' ? 'https://wa.me/'.$whatsapp : null,
                'support_email' => $s['support_email'] ?: null,
            ],
        ]])->header('Cache-Control', 'public, max-age=60'); // maintenance and forced updates reach phones within a minute
    }
}
