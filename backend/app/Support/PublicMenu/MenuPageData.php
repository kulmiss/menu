<?php

namespace App\Support\PublicMenu;

use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Branch;
use App\Models\BranchItemOverride;
use App\Models\BranchVariantPrice;
use App\Models\Category;
use App\Models\Closure;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuSchedule;
use App\Models\OpeningHour;
use App\Models\Promotion;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\TenantProfile;
use App\Support\Features;
use App\Support\ImageProcessor;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the guest page shows, loaded in a fixed number of queries
 * (PLAN.md §15.4) and turned into plain arrays for the view.
 */
class MenuPageData
{
    public function __construct(private readonly CurrentTenant $current) {}

    /** @return array<string, mixed> */
    public function build(Tenant $tenant, ?TenantAppearance $appearance = null, ?Branch $branch = null): array
    {
        return $this->current->runAs($tenant, function () use ($tenant, $appearance, $branch) {
            $branches = Branch::where('is_active', true)->orderByDesc('is_default')->orderBy('sort')->orderBy('id')->get();
            $branch ??= $branches->firstWhere('is_default', true) ?? $branches->first();
            $branchId = $branch?->id;
            $appearance ??= TenantAppearance::current();
            $theme = PageTheme::from($appearance, Features::styles($tenant));
            $locale = $tenant->default_locale ?: 'en';
            $text = fn (?array $v): string => $v === null || $v === [] ? '' : (string) ($v[$locale] ?? reset($v));

            /** @var Collection<int, Menu> $menus */
            $menus = Menu::where('is_visible', true)
                // Menus limited to some branches (QUESTIONS #59).
                ->where(fn ($q) => $q->whereDoesntHave('branches')->orWhereHas('branches', fn ($b) => $b->where('branches.id', $branchId)))
                ->orderBy('sort')->orderBy('id')->with([
                    'schedules',
                    'categories' => fn ($q) => $q->where('is_visible', true),
                    'categories.media',
                    'categories.items' => fn ($q) => $q->where('is_visible', true),
                    'categories.items.variants',
                    'categories.items.media',
                    'categories.items.photos',
                    'categories.items.tags',
                    'categories.items.allergenRows',
                    'categories.items.addonGroups.options',
                ])->get();

            // This branch's own prices, sold-out and hidden items (null = as the main menu).
            $overrides = $branchId === null ? collect() : BranchItemOverride::where('branch_id', $branchId)->get()->keyBy('item_id');
            $sizePrices = $branchId === null ? collect() : BranchVariantPrice::where('branch_id', $branchId)->pluck('price_minor', 'item_variant_id');
            if ($sizePrices->isNotEmpty()) {
                foreach ($menus as $menu) {
                    foreach ($menu->categories as $category) {
                        foreach ($category->items as $item) {
                            foreach ($item->variants as $variant) {
                                if ($sizePrices->has($variant->id)) {
                                    $variant->price_minor = (int) $sizePrices->get($variant->id);
                                }
                            }
                        }
                    }
                }
            }
            if ($overrides->isNotEmpty()) {
                foreach ($menus as $menu) {
                    foreach ($menu->categories as $category) {
                        $category->setRelation('items', $category->items->reject(function (Item $item) use ($overrides) {
                            $o = $overrides->get($item->id);
                            if ($o === null) {
                                return false;
                            }
                            if ($o->is_sold_out !== null) {
                                $item->is_sold_out = $o->is_sold_out;
                            }
                            if ($o->price_minor !== null && $item->variants->isEmpty()) {
                                $item->price_minor = $o->price_minor;
                            }

                            return (bool) $o->is_hidden;
                        })->values());
                    }
                }
            }

            // Opening hours: the branch's own, else the restaurant's; closures still to come.
            $allHours = OpeningHour::whereNull('branch_id')->orWhere('branch_id', $branchId)->orderBy('weekday')->orderBy('opens_at')->get();
            $own = $allHours->where('branch_id', $branchId);
            $hours = ($branchId !== null && $own->isNotEmpty() ? $own : $allHours->whereNull('branch_id'))
                ->map(fn (OpeningHour $h) => [$h->weekday, substr($h->opens_at, 0, 5), substr($h->closes_at, 0, 5)])->values()->all();
            $today = now($tenant->timezone)->toDateString();
            $closures = Closure::where('ends_on', '>=', $today)->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
                ->orderBy('starts_on')->get();
            $profile = TenantProfile::current();

            $tagsUsed = [];
            $money = fn (?int $minor): string => $theme['show_prices'] ? self::money($minor, $tenant->currency_code) : '';
            $describe = fn (?array $v): string => $theme['show_descriptions'] ? $text($v) : '';
            $photos = $theme['show_photos'];
            $calories = $theme['show_calories'];
            $prices = $theme['show_prices'];

            $menuRows = $menus->map(function (Menu $menu) use ($text, $money, $describe, $photos, $calories, $prices, &$tagsUsed) {
                return [
                    'id' => $menu->id,
                    'name' => $text($menu->name),
                    'scheduled' => $menu->schedule_mode === Menu::SCHEDULED,
                    'schedules' => $menu->schedules->map(fn (MenuSchedule $s) => [$s->weekday, substr($s->starts_at, 0, 5), substr($s->ends_at, 0, 5)])->values()->all(),
                    'categories' => $menu->categories->filter(fn (Category $c) => $c->items->isNotEmpty())->map(fn (Category $c) => [
                        'id' => $c->id,
                        'name' => $text($c->name),
                        'description' => $describe($c->description),
                        'items' => $c->items->map(function (Item $item) use ($text, $money, $describe, $photos, $calories, $prices, &$tagsUsed) {
                            $tags = $item->tags->map(fn (Tag $t) => ['key' => $t->key ?? 'c'.$t->id, 'label' => $t->key ? __('public.tags.'.$t->key) : $text($t->name)])->values()->all();
                            foreach ($tags as $tag) {
                                $tagsUsed[$tag['key']] = $tag['label'];
                            }

                            return [
                                'id' => $item->id,
                                'name' => $text($item->name),
                                'summary' => $describe($item->summary),
                                'price' => $item->variants->isNotEmpty()
                                    ? ($prices ? __('public.from', ['price' => $money($item->fromPriceMinor())]) : '')
                                    : $money($item->price_minor),
                                'sold_out' => $item->is_sold_out,
                                'new' => $item->is_new && ($item->new_until === null || $item->new_until->isFuture() || $item->new_until->isToday()),
                                'popular' => $item->is_popular,
                                'tags' => $tags,
                                // Per dish "Show photo on menu" off: photos stay saved, the menu shows none.
                                'media' => $photos && $item->show_photo && $item->media ? self::image($item->media) : null,
                                // "My order": numbers only (prices in minor units, ids), in the same order as sheet v / a.
                                'cart' => [
                                    $item->variants->isEmpty() ? $item->price_minor : null,
                                    $item->variants->map(fn (ItemVariant $v) => [$v->id, (int) $v->price_minor])->values()->all(),
                                    $item->addonGroups->map(fn (AddonGroup $g) => [
                                        (int) $g->min_choices, (int) $g->max_choices,
                                        $g->options->map(fn (AddonOption $o) => [$o->id, (int) $o->price_minor, $o->is_sold_out ? 1 : 0])->values()->all(),
                                    ])->values()->all(),
                                ],
                                'sheet' => array_filter([
                                    'd' => $describe($item->description),
                                    'v' => $item->variants->map(fn (ItemVariant $v) => [$text($v->name), $money($v->price_minor)])->values()->all(),
                                    'a' => $item->addonGroups->map(fn (AddonGroup $g) => [
                                        $text($g->name), $g->min_choices, $g->max_choices,
                                        $g->options->map(fn (AddonOption $o) => [$text($o->name), $prices && $o->price_minor > 0 ? '+'.$money($o->price_minor) : '', $o->is_sold_out ? 1 : 0])->values()->all(),
                                    ])->values()->all(),
                                    'al' => array_map(fn ($a) => __('public.allergens.'.$a), $item->allergens()),
                                    'k' => $calories ? $item->calories : null,
                                    'p' => $item->prep_minutes,
                                    'i' => $photos && $item->show_photo && $item->media ? ($item->media->urls()['1280'] ?? null) : null,
                                    // More photos to swipe through in the details sheet (photo 2–5).
                                    'g' => $photos && $item->show_photo ? $item->photos->reject(fn (Media $m) => $m->id === $item->media_id)
                                        ->map(fn (Media $m) => $m->urls()['1280'] ?? null)->filter()->values()->all() : [],
                                ], fn ($v) => $v !== null && $v !== '' && $v !== []),
                            ];
                        })->values()->all(),
                    ])->values()->all(),
                ];
            })->filter(fn (array $m) => $m['categories'] !== [])->values()->all();

            // Promotions still running (the page shows each only inside its dates).
            $itemsOnPage = [];
            foreach ($menuRows as $m) {
                foreach ($m['categories'] as $c) {
                    foreach ($c['items'] as $i) {
                        $itemsOnPage[$i['id']] = $i;
                    }
                }
            }
            $localNow = now($tenant->timezone)->format('Y-m-d H:i');
            $features = Features::for($tenant);
            $promotions = ! $features['promotions'] ? [] : Promotion::with('media')->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $localNow))
                ->orderBy('sort')->orderBy('id')->get()
                ->map(function (Promotion $p) use ($text, $itemsOnPage, $localNow, $photos) {
                    $window = [$p->starts_at?->format('Y-m-d H:i'), $p->ends_at?->format('Y-m-d H:i')];
                    $item = $p->item_id !== null ? ($itemsOnPage[$p->item_id] ?? null) : null;

                    return [
                        'type' => $p->type,
                        'title' => $text($p->title),
                        'body' => $text($p->body),
                        'item' => $item ? ['id' => $item['id'], 'price' => $item['price']] : null,
                        'image' => $photos && $p->media ? self::image($p->media) : null,
                        'window' => $window[0] === null && $window[1] === null ? null : $window,
                        // Outside its dates when the page was built (no-JS fallback; the script re-checks).
                        'hidden' => ($window[0] !== null && $localNow < $window[0]) || ($window[1] !== null && $localNow >= $window[1]),
                    ];
                })->values()->all();

            return [
                'tenant' => $tenant,
                'theme' => $theme,
                'promotions' => $promotions,
                'branding' => ! $features['remove_branding'],
                'pdfMenu' => $features['pdf_menu'],
                'orderList' => $features['order_list'] && $theme['show_order_list'] && $prices,
                // QR code → table, so "Show to waiter" can say which table (codes are printed on the tables anyway).
                'tables' => $features['order_list'] ? self::tables($branchId) : [],
                'branch' => $branch && $branches->count() > 1 ? $text($branch->name) : null,
                // One URL per branch, no picker (owner, 2026-11-12): only a footer list of the other branches.
                'otherBranches' => $branches->count() > 1 && $theme['show_other_branches']
                    ? $branches->reject(fn (Branch $b) => $b->id === $branchId)->map(fn (Branch $b) => [
                        'name' => $text($b->name),
                        'address' => $text($b->is_default ? $profile->address : $b->address),
                        'path' => $b->path($tenant),
                    ])->values()->all() : [],
                'canonical' => $branch ? $branch->path($tenant) : '/'.$tenant->slug,
                'branch_id' => $branchId,
                'hours' => $hours,
                'closures' => $closures->map(fn (Closure $c) => [$c->starts_on->toDateString(), $c->ends_on->toDateString()])->values()->all(),
                'week' => self::week($hours),
                'hoursSummary' => self::summary(self::week($hours)),
                'notices' => $closures->take(3)->map(fn (Closure $c) => [
                    'dates' => $c->starts_on->equalTo($c->ends_on) ? $c->starts_on->format('D j M') : $c->starts_on->format('D j M').' – '.$c->ends_on->format('D j M'),
                    'note' => $text($c->note),
                ])->values()->all(),
                'info' => self::info($profile, $branch, $text),
                'locale' => $locale,
                'menus' => $menuRows,
                'tags' => $tagsUsed,
                'timezone' => $tenant->timezone,
                'items_count' => array_sum(array_map(fn ($m) => array_sum(array_map(fn ($c) => count($c['items']), $m['categories'])), $menuRows)),
            ];
        });
    }

    /**
     * Opening hours per day for the info section, Monday first.
     *
     * @param  list<array{0: int, 1: string, 2: string}>  $hours
     * @return list<array{day: string, times: string}>
     */
    private static function week(array $hours): array
    {
        if ($hours === []) {
            return [];
        }
        $rows = [];
        foreach ([1, 2, 3, 4, 5, 6, 0] as $wd) {
            $times = array_map(fn ($h) => $h[1].'–'.$h[2], array_filter($hours, fn ($h) => $h[0] === $wd));
            $rows[] = ['day' => __('public.days.'.$wd), 'times' => $times === [] ? __('public.closed') : implode(', ', $times)];
        }

        return $rows;
    }

    /**
     * The week in a few lines for the footer: days in a row with the same
     * times share a line ("Mon–Fri · 07:00–22:00").
     *
     * @param  list<array{day: string, times: string}>  $week
     * @return list<array{days: string, times: string}>
     */
    private static function summary(array $week): array
    {
        $short = fn (int $i): string => __('public.days_short.'.[1, 2, 3, 4, 5, 6, 0][$i]);
        $out = [];
        $start = 0;
        foreach ($week as $i => $row) {
            $next = $week[$i + 1] ?? null;
            if ($next !== null && $next['times'] === $row['times']) {
                continue;
            }
            $out[] = ['days' => $start === $i ? $short($i) : $short($start).'–'.$short($i), 'times' => $row['times']];
            $start = $i + 1;
        }

        return $out;
    }

    /**
     * Contact and Wi-Fi for the info section; a branch's address and phone
     * win over the restaurant's.
     *
     * @param  callable(array<string, string>|null): string  $text
     * @return array<string, mixed>
     */
    private static function info(TenantProfile $p, ?Branch $branch, callable $text): array
    {
        $address = $branch && ! $branch->is_default && $text($branch->address) !== '' ? $text($branch->address) : $text($p->address);
        $maps = $branch && ! $branch->is_default && $branch->maps_url ? $branch->maps_url
            : ($p->maps_url ?: ($p->lat !== null && $p->lng !== null ? 'https://www.google.com/maps/search/?api=1&query='.$p->lat.','.$p->lng : null));
        $phone = $branch && ! $branch->is_default && $branch->phone ? $branch->phone : $p->phone;
        $digits = fn (?string $v): string => (string) preg_replace('/\D+/', '', (string) $v);

        $links = array_values(array_filter([
            $phone ? ['icon' => 'phone', 'label' => __('public.call'), 'href' => 'tel:'.preg_replace('/[^0-9+]/', '', $phone), 'text' => $phone] : null,
            $p->whatsapp ? ['icon' => 'whatsapp', 'label' => 'WhatsApp', 'href' => 'https://wa.me/'.$digits($p->whatsapp), 'text' => $p->whatsapp] : null,
            $p->telegram ? ['icon' => 'telegram', 'label' => 'Telegram', 'href' => 'https://t.me/'.$p->telegram, 'text' => '@'.$p->telegram] : null,
            $p->email ? ['icon' => 'email', 'label' => __('public.email'), 'href' => 'mailto:'.$p->email, 'text' => $p->email] : null,
        ]));
        $socials = [];
        foreach (TenantProfile::SOCIALS as $key) {
            if (! empty($p->socials[$key])) {
                $socials[] = ['icon' => $key, 'label' => __('public.socials.'.$key), 'href' => $p->socials[$key]];
            }
        }

        return [
            'about' => $text($p->description),
            'address' => $address,
            'maps' => $maps,
            'links' => $links,
            'socials' => $socials,
            'wifi' => $p->show_wifi && $p->wifi_name ? ['name' => $p->wifi_name, 'password' => $p->wifi_password] : null,
        ];
    }

    /** "Br 120" / "Br 120.50" — decimals only when needed (PLAN.md §11). */
    public static function symbol(string $currency): string
    {
        return ['ETB' => 'Br', 'USD' => '$', 'EUR' => '€', 'SOS' => 'Sh', 'KES' => 'KSh'][$currency] ?? $currency;
    }

    public static function money(?int $minor, string $currency): string
    {
        if ($minor === null) {
            return '';
        }
        $symbol = self::symbol($currency);
        $number = $minor % 100 === 0 ? number_format(intdiv($minor, 100)) : number_format($minor / 100, 2);

        return $symbol.' '.$number;
    }

    /** @return array<string, mixed> */
    private static function image(Media $media): array
    {
        $urls = $media->urls();

        return [
            'w' => $media->width,
            'h' => $media->height,
            'webp' => self::srcset($urls),
            'avif' => self::srcset($urls, 'avif') ?: null,
            'jpg' => $urls['jpg'] ?? null,
            'og' => $urls['og'] ?? null,
            'placeholder' => $media->placeholder,
            'color' => $media->dominant_color,
        ];
    }

    /**
     * "url 320w, url 640w, …" for the sizes a photo has (older uploads have
     * no 960).
     *
     * @param  array<int|string, string>  $urls
     */
    public static function srcset(array $urls, string $prefix = ''): string
    {
        $parts = [];
        foreach (ImageProcessor::WIDTHS as $w) {
            if (isset($urls[$prefix.$w])) {
                $parts[] = $urls[$prefix.$w].' '.$w.'w';
            }
        }

        return implode(', ', $parts);
    }

    /** @return array<string, string> QR code => table label, for this branch */
    private static function tables(?int $branchId): array
    {
        return DB::table('qr_codes as q')
            ->join('dining_tables as d', 'd.id', '=', 'q.dining_table_id')
            ->whereNull('q.deleted_at')
            ->where('q.tenant_id', app(CurrentTenant::class)->id())
            ->when($branchId, fn ($query) => $query->where('d.branch_id', $branchId))
            ->pluck('d.label', 'q.code')
            ->map(fn ($label) => (string) $label)
            ->all();
    }
}
