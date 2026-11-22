<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide settings (PLAN.md §7.3 screen 8), cached; defaults below.
 */
class PlatformSettings
{
    public const DEFAULTS = [
        'name' => 'Menu',
        'support_email' => null,
        'domain' => null,
        'default_locale' => 'en',
        'default_currency' => 'ETB',
        'terms' => '',
        'privacy' => '',
        // Site pages linked from the footer (owner, 2026-11-18): empty = the default text (resources/legal).
        'about' => '',
        'contact' => '',
        'cookies' => '',
        // Super Admin → Footer: the unpublished draft (null = none; the live footer is the newest FooterVersion).
        'footer_draft' => null,
        // Default link preview when a restaurant has no banner (path on the public disk; null = built-in).
        'share_image' => null,
        // E-mail header logo (path on the public disk; null = the platform name in text).
        'logo' => null,
        // Two-factor sign-in for restaurant Owners: encouraged; "required" is the Super Admin's switch (owner, 2026-11-14).
        'owner_two_factor_required' => false,
        // Self sign-up (owner, 2026-11-13).
        'signup_open' => true,
        'signup_plan_id' => null, // null = the "free" plan
        'signup_approval' => false,
        'signup_notify' => true,
        'turnstile_enabled' => false,
        'turnstile_site_key' => '',
        'turnstile_secret' => '', // encrypted (Crypt), never sent to the browser
        'disposable_domains' => [
            '10minutemail.com', 'dispostable.com', 'getnada.com', 'guerrillamail.com', 'mailinator.com', 'maildrop.cc',
            'sharklasers.com', 'temp-mail.org', 'tempmail.com', 'throwawaymail.com', 'trashmail.com', 'yopmail.com',
        ],
        // "Upgrade" hint when a plan limit is reached (payments are manual).
        'upgrade_whatsapp' => '',
        // Menu Admin app (QUESTIONS #190): older versions must update; flags switch app features remotely.
        'app_min_version' => '1.0.0',
        'app_latest_version' => '1.0.0',
        'app_flags' => [],
        'mobile' => [], // Super Admin → Mobile app, see App\Support\MobileApp (QUESTIONS #193)
        'features_global' => [], // feature key => false to switch it off for everyone (Settings → Features)
        // Setup wizard (QUESTIONS #195): done_at = finished; card_hidden = the dashboard checklist closed.
        'setup' => ['done_at' => null, 'card_hidden' => false],
        // "Continue with Google" (owner, 2026-11-17): needs GOOGLE_CLIENT_ID/SECRET in .env too.
        'google_login' => false,
        // Public landing page (owner, 2026-11-13). Empty texts use the translated defaults.
        'brand_color' => '#c2410c',
        // The home page: Landing 1 (the first design) or Landing 2 (owner, 2026-11-22).
        'home_design' => 'landing1',
        'landing_headline' => '',
        'landing_subline' => '',
        // Sections between the hero and the footer (owner, 2026-11-17). "Customers say" starts hidden.
        'landing_sections' => ['problem' => true, 'demo' => true, 'how' => true, 'features' => true, 'compare' => true, 'pricing' => true, 'testimonials' => false, 'faq' => true, 'cta' => true],
        'landing_order' => ['problem', 'demo', 'how', 'features', 'compare', 'pricing', 'testimonials', 'faq', 'cta'],
        // Real quotes only, added by the Super Admin: [{quote, name, role, real}]. Only ticked "real" ones show.
        'landing_testimonials' => [],
        // Super Admin → Landing page (owner, 2026-11-17): changed texts (key => text; others use
        // lang/en/landing.php) and uploaded pictures (see App\Support\Landing\LandingImages).
        'landing_texts' => [],
        'landing_images' => [],
        // Landing 2 (owner, 2026-11-22), see App\Support\Landing\Landing2 and Landing2Texts.
        'landing2_texts' => ['en' => [], 'am' => []],
        'landing2_sections' => [],
        'landing2_payments' => null, // null = the built-in list (Landing2::PAYMENTS)
        'landing2_numbers' => [],
        'landing2_numbers_counted' => null, // {at, restaurants, dishes, views}: landing2:numbers, daily
        'landing2_video' => null, // {folder} on the public disk (video.mp4, poster.webp, en.vtt, am.vtt); null = the built-in video, if any
        'landing2_footer_am' => [], // English footer text (Content → Footer) => Amharic
        'landing2_lines' => [], // Amharic plan lines and footer texts from the review CSV: "landing.pricing.free" / "footer.trust" => text
        // Footer links: network => URL (empty = hidden).
        // Billing (owner, 2026-11-13): shown on every invoice, copied into it when issued.
        'billing_company' => '',
        'billing_address' => '',
        'billing_tax_id' => '',
        'invoice_prefix' => 'INV',
        'invoice_footer' => '',
        'invoice_tax_rate' => 0, // VAT %, included in the amount paid
        'invoice_email' => true, // e-mail each new invoice to the owner
        // Messages (bulk e-mail): e-mails sent per minute by the cron queue worker.
        'campaign_rate' => 30,
        // Backups (owner, 2026-11-13): daily at 02:30, thinned to the newest per day / week / month.
        'backup_enabled' => true,
        'backup_keep_daily' => 7,
        'backup_keep_weekly' => 4,
        'backup_keep_monthly' => 3,
        'backup_s3_enabled' => false,
        'backup_s3_endpoint' => '',
        'backup_s3_region' => 'auto',
        'backup_s3_bucket' => '',
        'backup_s3_prefix' => 'menu-backups',
        'backup_s3_key' => '',
        'backup_s3_secret' => '', // encrypted (Crypt), never sent to the browser
        // First-customer experience (owner, 2026-11-19, QUESTIONS #235): "Add my menu for me"
        // requests, and the welcome e-mails on day 1, 3 and 7 after sign-up.
        'menu_help_enabled' => true,
        'welcome_series' => false,
        // Alerts (owner, 2026-11-19, QUESTIONS #233): server errors and failed go-live checks
        // e-mailed to every Super Admin and, optionally, sent to a Telegram chat.
        'alerts_email' => true,
        'alerts_telegram_enabled' => false,
        'alerts_telegram_chat' => '',
        'alerts_telegram_token' => '', // encrypted (Crypt), never sent to the browser
        'socials' => ['telegram' => '', 'facebook' => '', 'instagram' => '', 'tiktok' => '', 'x' => '', 'youtube' => '', 'linkedin' => ''],
    ];

    private const CACHE_KEY = 'platform_settings.stored';

    /** Landing page sections the Super Admin can switch off. */
    public const LANDING_SECTIONS = ['how', 'features', 'styles', 'speed', 'pricing', 'testimonials', 'faq', 'cta'];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        // Only the stored values are cached; defaults are merged on every read, so
        // a release that adds a setting never meets a cache without that key.
        $stored = Cache::remember(self::CACHE_KEY, 3600, fn () => DB::table('platform_settings')->pluck('value', 'key')
            ->map(fn ($v) => json_decode((string) $v, true))->all());

        return [...self::DEFAULTS, ...array_intersect_key((array) $stored, self::DEFAULTS)];
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Settings for the Super Admin screen: secrets replaced by "is set".
     *
     * @return array<string, mixed>
     */
    public static function forScreen(): array
    {
        $all = self::all();
        $all['turnstile_secret_set'] = ($all['turnstile_secret'] ?? '') !== '';
        $all['backup_s3_secret_set'] = ($all['backup_s3_secret'] ?? '') !== '';
        $all['alerts_telegram_token_set'] = ($all['alerts_telegram_token'] ?? '') !== '';
        unset($all['turnstile_secret'], $all['backup_s3_secret'], $all['alerts_telegram_token']);

        return $all;
    }

    /** The Turnstile secret key, decrypted (empty when not set). */
    public static function turnstileSecret(): string
    {
        $stored = (string) self::get('turnstile_secret');
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return '';
        }
    }

    /** @param array<string, mixed> $values */
    public static function save(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
