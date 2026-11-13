<?php

namespace Database\Seeders\Demo;

use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemAllergen;
use App\Models\ItemVariant;
use App\Models\Menu;
use App\Models\MenuSchedule;
use App\Models\Tag;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Builds a restaurant's menus from a plain catalogue array (run inside the
 * tenant). Item shape: [name, summary, price (minor units), tags, options]
 * where options may hold: d (description), sz ([size, price] list),
 * al (allergens), k (kcal), pm (prep minutes), ph (photo keys, first = main),
 * g (add-on group keys), so (sold out), new_days (the "New" badge ends).
 *
 * @phpstan-type ItemRow array{0: string, 1: string|null, 2: int, 3: list<string>, 4?: array<string, mixed>}
 * @phpstan-type Catalogue array{groups?: array<string, array{0: string, 1: int, 2: int, 3: list<array{0: string, 1: int, 2?: bool}>}>, menus: list<array{name: string, slug: string, schedule?: array{0: string, 1: string}, categories: list<array{0: string, 1: string|null, 2: list<ItemRow>}>}>}
 */
class MenuBuilder
{
    public function __construct(private readonly Photos $photos) {}

    /**
     * @param  Catalogue  $catalogue
     * @return array<string, Item> items by English name
     */
    public function build(Tenant $tenant, array $catalogue): array
    {
        $tags = Tag::all()->keyBy('key');
        $groups = [];
        foreach ($catalogue['groups'] ?? [] as $key => [$name, $min, $max, $options]) {
            $group = AddonGroup::create(['name' => ['en' => $name], 'min_choices' => $min, 'max_choices' => $max]);
            foreach ($options as $i => $option) {
                AddonOption::create(['addon_group_id' => $group->id, 'name' => ['en' => $option[0]], 'price_minor' => $option[1], 'is_sold_out' => $option[2] ?? false, 'sort' => $i]);
            }
            $groups[$key] = $group->id;
        }

        $items = [];
        $sort = 0;
        foreach ($catalogue['menus'] as $mi => $menuRow) {
            $menu = Menu::create([
                'name' => ['en' => $menuRow['name']], 'slug' => $menuRow['slug'], 'sort' => $mi,
                'schedule_mode' => isset($menuRow['schedule']) ? Menu::SCHEDULED : Menu::ALWAYS,
            ]);
            if (isset($menuRow['schedule'])) {
                foreach (range(0, 6) as $day) {
                    MenuSchedule::create(['menu_id' => $menu->id, 'weekday' => $day, 'starts_at' => $menuRow['schedule'][0], 'ends_at' => $menuRow['schedule'][1]]);
                }
            }
            foreach ($menuRow['categories'] as [$categoryName, $categoryText, $rows]) {
                $category = Category::create([
                    'menu_id' => $menu->id, 'name' => ['en' => $categoryName],
                    'description' => $categoryText ? ['en' => $categoryText] : null, 'sort' => $sort++,
                ]);
                foreach ($rows as $ii => $row) {
                    $items[$row[0]] = $this->item($tenant, $category, $ii, $row, $tags, $groups);
                }
            }
        }

        return $items;
    }

    /**
     * @param  ItemRow  $row
     * @param  Collection<string, Tag>  $tags
     * @param  array<string, int>  $groups
     */
    private function item(Tenant $tenant, Category $category, int $sort, array $row, $tags, array $groups): Item
    {
        [$name, $summary, $price, $tagKeys] = $row;
        $opt = $row[4] ?? [];
        $sizes = $opt['sz'] ?? null;
        $photoIds = [];
        foreach ($opt['ph'] ?? [] as $key) {
            $media = $this->photos->media($tenant, $key);
            if ($media) {
                $photoIds[] = $media->id;
            }
        }
        $new = in_array('new', $tagKeys, true);

        $item = Item::create([
            'category_id' => $category->id,
            'name' => ['en' => $name],
            'summary' => $summary ? ['en' => $summary] : null,
            'description' => isset($opt['d']) ? ['en' => $opt['d']] : null,
            'price_minor' => $sizes ? null : $price,
            'media_id' => $photoIds[0] ?? null,
            'calories' => $opt['k'] ?? null,
            'prep_minutes' => $opt['pm'] ?? null,
            'is_sold_out' => $opt['so'] ?? false,
            'is_new' => $new,
            'new_until' => $new ? now()->addDays($opt['new_days'] ?? 30) : null,
            'is_popular' => in_array('popular', $tagKeys, true),
            'sort' => $sort,
        ]);
        $item->photos()->sync(collect($photoIds)->mapWithKeys(fn ($id, $i) => [$id => ['tenant_id' => $tenant->id, 'sort' => $i]])->all());
        foreach ($sizes ?? [] as $si => [$size, $sizePrice]) {
            ItemVariant::create(['item_id' => $item->id, 'name' => ['en' => $size], 'price_minor' => $sizePrice, 'is_default' => $si === 0, 'sort' => $si]);
        }
        $tagIds = collect($tagKeys)->map(fn ($k) => $tags[$k]->id ?? null)->filter();
        $item->tags()->attach($tagIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenant->id]])->all());
        foreach ($opt['al'] ?? [] as $allergen) {
            ItemAllergen::create(['item_id' => $item->id, 'allergen' => $allergen]);
        }
        $attach = [];
        foreach ($opt['g'] ?? [] as $gi => $key) {
            $attach[$groups[$key]] = ['tenant_id' => $tenant->id, 'sort' => $gi];
        }
        $item->addonGroups()->attach($attach);

        return $item;
    }
}
