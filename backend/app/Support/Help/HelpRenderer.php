<?php

namespace App\Support\Help;

use App\Http\Controllers\Api\Menu\ItemController;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Features;
use App\Support\PlatformSettings;
use App\Support\PublicMenu\MenuPageData;
use App\Support\SignUp\SignUp;
use App\Support\UpgradeContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns an article's Markdown into what a person reads (QUESTIONS #194):
 *
 * - `{{name}}` placeholders → live values (plans, prices, limits, contact), never typed by hand;
 * - `<!-- if sold_out -->…<!-- endif -->` blocks → kept only when the feature is on
 *   (`!key` = only when off, `role:owner,manager` = only for those roles);
 * - `![Alt](shot:dashboard)` → the phone and desktop screenshots in public/help/shots/;
 * - `[text](help:slug)` links stay as they are: each app opens its own Help page.
 */
class HelpRenderer
{
    /** @var array<string, string>|null */
    private ?array $values = null;

    public function __construct(private readonly ?Tenant $tenant = null, private readonly ?string $role = null) {}

    public function markdown(string $body): string
    {
        // Code is shown as written: no placeholders or conditions inside it.
        $code = [];
        $body = preg_replace_callback('/```.*?```|`[^`\n]+`/s', function ($m) use (&$code) {
            $code[] = $m[0];

            return "\u{E000}".(count($code) - 1)."\u{E001}";
        }, $body) ?? $body;
        $body = $this->conditions($body);
        $body = preg_replace_callback('/\{\{\s*([a-z0-9_.\-]+)\s*\}\}/', fn ($m) => $this->value($m[1]) ?? $m[0], $body) ?? $body;
        $body = preg_replace_callback('/!\[([^\]]*)\]\(shot:([a-z0-9\-]+)\)/', fn ($m) => self::shot($m[2], $m[1]), $body) ?? $body;
        $body = preg_replace('/!\[([^\]]*)\]\(appshot:([a-z0-9\-]+)\)/', '![$1](/help/shots/$2.webp "app")', $body) ?? $body;

        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;

        return preg_replace_callback('/\x{E000}(\d+)\x{E001}/u', fn ($m) => $code[(int) $m[1]], $body) ?? $body;
    }

    public function html(string $markdown): string
    {
        $html = Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
        // Help links and screenshots: the web admin turns `help:` into its own address.
        $html = str_replace(['<img src="/help/shots/', '<a href="help:'], ['<img loading="lazy" src="/help/shots/', '<a data-help href="help:'], $html);

        return $html;
    }

    /**
     * Placeholders that don't resolve (tests use this to find typos).
     *
     * @return list<string>
     */
    public function unknown(string $body): array
    {
        $body = preg_replace('/```.*?```|`[^`\n]+`/s', '', $body) ?? $body;
        preg_match_all('/\{\{\s*([a-z0-9_.\-]+)\s*\}\}/', $body, $m);

        return array_values(array_unique(array_filter($m[1], fn ($k) => $this->value($k) === null)));
    }

    /** `![Alt](shot:menu)` → phone and desktop pictures (the files are checked by a test). */
    public static function shot(string $name, string $alt): string
    {
        return "![{$alt}](/help/shots/{$name}-phone.webp \"phone\") ![{$alt}](/help/shots/{$name}-desktop.webp \"desktop\")";
    }

    /** @return list<string> picture files a body uses, e.g. `dashboard-phone.webp` */
    public static function shots(string $body): array
    {
        preg_match_all('/\((app)?shot:([a-z0-9\-]+)\)/', $body, $m, PREG_SET_ORDER);
        $files = [];
        foreach ($m as $x) {
            array_push($files, ...($x[1] === 'app' ? [$x[2].'.webp'] : [$x[2].'-phone.webp', $x[2].'-desktop.webp']));
        }

        return array_values(array_unique($files));
    }

    private function conditions(string $body): string
    {
        // Innermost first, so blocks may be nested.
        // A tag on its own line takes its line break with it, so lists and tables stay whole.
        $re = '/<!--\s*if\s+([^>]*?)\s*-->\n?((?:(?!<!--\s*if\s).)*?)<!--\s*endif\s*-->\n?/s';
        while (preg_match($re, $body)) {
            $body = preg_replace_callback($re, fn ($m) => $this->holds($m[1]) ? $m[2] : '', $body) ?? $body;
        }

        return $body;
    }

    private function holds(string $condition): bool
    {
        foreach (preg_split('/\s+/', trim($condition)) ?: [] as $token) {
            if (str_starts_with($token, 'role:')) {
                if ($this->role !== null && ! in_array($this->role, explode(',', substr($token, 5)), true)) {
                    return false;
                }
            } elseif (! HelpLibrary::featuresOn([$token], $this->tenant)) {
                return false;
            }
        }

        return true;
    }

    private function value(string $key): ?string
    {
        $this->values ??= $this->values();

        return $this->values[$key] ?? null;
    }

    /** @return array<string, string> */
    private function values(): array
    {
        $s = PlatformSettings::all();
        $contact = UpgradeContact::payload();
        $signup = SignUp::plan();
        $v = [
            'site_name' => (string) $s['name'],
            'site_url' => rtrim((string) config('app.url'), '/'),
            'admin_url' => rtrim((string) config('app.url'), '/').'/admin',
            'support_whatsapp' => $contact['whatsapp'] ?? '—',
            'support_email' => $contact['email'] ?? '—',
            'support' => $this->support($contact),
            'trial_days' => (string) ($signup?->trial_days ?: config('menu.trial_days')),
            'grace_days' => (string) config('menu.grace_days'),
            'read_only_days' => (string) config('menu.read_only_days'),
            'invitation_days' => (string) config('menu.invitation_days'),
            'photos_per_dish' => (string) ItemController::MAX_PHOTOS,
            'photo_max_mb' => '8',
            'import_max_mb' => '5',
            'signup_plan' => $signup ? $this->name($signup) : '—',
            'plans' => $this->plans(),
        ];
        foreach (Plan::query()->get() as $plan) {
            $p = 'plan.'.$plan->slug;
            $v["$p.name"] = $this->name($plan);
            $v["$p.price"] = $plan->price_minor === 0 ? __('help.free') : MenuPageData::money($plan->price_minor, $plan->currency).' '.__('help.per.'.($plan->interval === 'year' ? 'year' : 'month'));
            foreach (config('menu.limit_keys') as $limit) {
                $n = ((array) ($plan->limits ?? []))[$limit] ?? null;
                $v["$p.$limit"] = $n === null ? __('help.unlimited') : number_format((int) $n);
            }
        }
        if ($signup) {
            foreach (config('menu.limit_keys') as $limit) {
                $v["signup_plan.$limit"] = $v["plan.{$signup->slug}.$limit"];
            }
        }
        if ($this->tenant !== null) {
            $v['restaurant_name'] = (string) $this->tenant->name;
            $v['menu_url'] = rtrim((string) config('app.url'), '/').'/'.$this->tenant->slug;
        } else {
            $v['restaurant_name'] = __('help.your_restaurant');
            $v['menu_url'] = rtrim((string) config('app.url'), '/').'/your-restaurant';
        }

        return $v;
    }

    /** @param  array{whatsapp: string|null, email: string|null}  $contact */
    private function support(array $contact): string
    {
        $lines = [];
        if ($contact['whatsapp']) {
            $lines[] = '- '.__('help.contact.whatsapp').': ['.preg_replace('#^https://wa\.me/#', '+', $contact['whatsapp']).']('.$contact['whatsapp'].')';
        }
        if ($contact['email']) {
            $lines[] = '- '.__('help.contact.email').': ['.$contact['email'].'](mailto:'.$contact['email'].')';
        }

        return $lines === [] ? __('help.contact.none') : implode("\n", $lines);
    }

    private function name(Plan $plan): string
    {
        return (string) ($plan->name[app()->getLocale()] ?? $plan->name['en'] ?? $plan->slug);
    }

    /** A short list of the public plans: price, limits and what each adds, live from the database. */
    private function plans(): string
    {
        $signup = SignUp::plan();
        $plans = Plan::where('is_active', true)->where(fn ($q) => $q->where('is_public', true)->orWhere('id', $signup?->id))
            ->orderBy('sort')->orderBy('price_minor')->get();
        if ($plans->isEmpty()) {
            return __('help.no_plans');
        }
        $own = DB::table('plan_features')->whereIn('plan_id', $plans->pluck('id'))->get()->groupBy('plan_id');
        $global = Features::global();
        $keys = ['branches', 'promotions', 'price_periods', 'insights', 'import_export', 'pdf_menu', 'order_list', 'remove_branding'];
        $lines = [];
        $before = [];
        foreach ($plans as $p) {
            $on = ($own[$p->id] ?? collect())->pluck('enabled', 'feature_key')->map(fn ($x) => (bool) $x)->all();
            $limits = (array) ($p->limits ?? []);
            $has = [];
            foreach ($keys as $k) {
                if (($on[$k] ?? Features::DEFAULTS[$k]) && ($global[$k] ?? true)) {
                    $has[$k] = __('help.plan_features.'.$k);
                }
            }
            $styles = count(array_filter(['editorial', 'grid', 'bistro', 'minimal'], fn ($st) => ($on['style_'.$st] ?? true) && ($global['style_'.$st] ?? true)));
            $limitText = [];
            foreach (['items', 'photos', 'branches', 'members'] as $k) {
                $n = $limits[$k] ?? null;
                $limitText[] = $n === null ? __('help.limits.unlimited_'.$k) : trans_choice('help.limits.'.$k, (int) $n, ['n' => number_format((int) $n)]);
            }
            $price = $p->price_minor === 0 ? __('help.free') : MenuPageData::money($p->price_minor, $p->currency).' '.__('help.per.'.($p->interval === 'year' ? 'year' : 'month'));
            $adds = array_diff_key($has, $before);
            $line = '- **'.$this->name($p).'** — '.$price.'. '.ucfirst(implode(' · ', $limitText)).' · '.trans_choice('help.limits.styles', $styles, ['n' => $styles]).'.';
            if ($adds !== []) {
                $line .= ' '.($before === [] ? __('help.includes') : __('help.adds')).' '.implode(', ', $adds).'.';
            }
            $lines[] = $line;
            $before = $has;
        }

        return implode("\n", $lines);
    }
}
