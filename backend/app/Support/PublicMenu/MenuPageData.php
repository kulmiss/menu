<?php

namespace App\Support\PublicMenu;

use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuSchedule;
use App\Models\Tag;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything the guest page shows, loaded in a fixed number of queries
 * (PLAN.md §15.4) and turned into plain arrays for the view.
 */
class MenuPageData
{
    public function __construct(private readonly CurrentTenant $current) {}

    /** @return array<string, mixed> */
    public function build(Tenant $tenant): array
    {
        return $this->current->runAs($tenant, function () use ($tenant) {
            $locale = $tenant->default_locale ?: 'en';
            $text = fn (?array $v): string => $v === null || $v === [] ? '' : (string) ($v[$locale] ?? reset($v));

            /** @var Collection<int, Menu> $menus */
            $menus = Menu::where('is_visible', true)->orderBy('sort')->orderBy('id')->with([
                'schedules',
                'categories' => fn ($q) => $q->where('is_visible', true),
                'categories.media',
                'categories.items' => fn ($q) => $q->where('is_visible', true),
                'categories.items.variants',
                'categories.items.media',
                'categories.items.tags',
                'categories.items.allergenRows',
                'categories.items.addonGroups.options',
            ])->get();

            $tagsUsed = [];
            $money = fn (?int $minor): string => self::money($minor, $tenant->currency_code);

            $menuRows = $menus->map(function (Menu $menu) use ($text, $money, &$tagsUsed) {
                return [
                    'id' => $menu->id,
                    'name' => $text($menu->name),
                    'scheduled' => $menu->schedule_mode === Menu::SCHEDULED,
                    'schedules' => $menu->schedules->map(fn (MenuSchedule $s) => [$s->weekday, substr($s->starts_at, 0, 5), substr($s->ends_at, 0, 5)])->values()->all(),
                    'categories' => $menu->categories->filter(fn (Category $c) => $c->items->isNotEmpty())->map(fn (Category $c) => [
                        'id' => $c->id,
                        'name' => $text($c->name),
                        'description' => $text($c->description),
                        'items' => $c->items->map(function (Item $item) use ($text, $money, &$tagsUsed) {
                            $tags = $item->tags->map(fn (Tag $t) => ['key' => $t->key ?? 'c'.$t->id, 'label' => $t->key ? __('public.tags.'.$t->key) : $text($t->name)])->values()->all();
                            foreach ($tags as $tag) {
                                $tagsUsed[$tag['key']] = $tag['label'];
                            }

                            return [
                                'id' => $item->id,
                                'name' => $text($item->name),
                                'summary' => $text($item->summary),
                                'price' => $item->variants->isNotEmpty()
                                    ? __('public.from', ['price' => $money($item->fromPriceMinor())])
                                    : ($item->price_minor === null ? '' : $money($item->price_minor)),
                                'sold_out' => $item->is_sold_out,
                                'new' => $item->is_new && ($item->new_until === null || $item->new_until->isFuture() || $item->new_until->isToday()),
                                'popular' => $item->is_popular,
                                'tags' => $tags,
                                'media' => $item->media ? self::image($item->media) : null,
                                'sheet' => array_filter([
                                    'd' => $text($item->description),
                                    'v' => $item->variants->map(fn (ItemVariant $v) => [$text($v->name), $money($v->price_minor)])->values()->all(),
                                    'a' => $item->addonGroups->map(fn (AddonGroup $g) => [
                                        $text($g->name), $g->min_choices, $g->max_choices,
                                        $g->options->map(fn (AddonOption $o) => [$text($o->name), $o->price_minor > 0 ? '+'.$money($o->price_minor) : '', $o->is_sold_out ? 1 : 0])->values()->all(),
                                    ])->values()->all(),
                                    'al' => array_map(fn ($a) => __('public.allergens.'.$a), $item->allergens()),
                                    'k' => $item->calories,
                                    'p' => $item->prep_minutes,
                                    'i' => $item->media ? ($item->media->urls()['1280'] ?? null) : null,
                                ], fn ($v) => $v !== null && $v !== '' && $v !== []),
                            ];
                        })->values()->all(),
                    ])->values()->all(),
                ];
            })->filter(fn (array $m) => $m['categories'] !== [])->values()->all();

            return [
                'tenant' => $tenant,
                'locale' => $locale,
                'menus' => $menuRows,
                'tags' => $tagsUsed,
                'timezone' => $tenant->timezone,
                'items_count' => array_sum(array_map(fn ($m) => array_sum(array_map(fn ($c) => count($c['items']), $m['categories'])), $menuRows)),
            ];
        });
    }

    /** "Br 120" / "Br 120.50" — decimals only when needed (PLAN.md §11). */
    public static function money(?int $minor, string $currency): string
    {
        if ($minor === null) {
            return '';
        }
        $symbol = ['ETB' => 'Br', 'USD' => '$', 'EUR' => '€', 'SOS' => 'Sh', 'KES' => 'KSh'][$currency] ?? $currency;
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
            'webp' => implode(', ', array_filter([
                isset($urls['320']) ? $urls['320'].' 320w' : null,
                isset($urls['640']) ? $urls['640'].' 640w' : null,
            ])),
            'avif' => isset($urls['avif320']) ? $urls['avif320'].' 320w, '.($urls['avif640'] ?? '').' 640w' : null,
            'jpg' => $urls['jpg'] ?? null,
            'og' => $urls['og'] ?? null,
            'placeholder' => $media->placeholder,
            'color' => $media->dominant_color,
        ];
    }
}
