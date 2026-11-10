<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemAllergen;
use App\Models\ItemVariant;
use App\Models\Media;
use App\Models\Membership;
use App\Models\Menu;
use App\Models\MenuSchedule;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ImageProcessor;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * "Buna & Bread", the demo café (English): about 60 items, two menus, sizes,
 * add-ons, tags, allergens and a few generated photos. CI measures the guest
 * page's speed on it (PLAN.md §15.5). Sign in: demo@example.com / password.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public const SLUG = 'demo';

    public function run(CurrentTenant $current, ImageProcessor $images): void
    {
        if (Tenant::where('slug', self::SLUG)->exists()) {
            $this->command?->info('The demo restaurant already exists.');

            return;
        }

        $tenant = Tenant::create([
            'slug' => self::SLUG,
            'name' => 'Buna & Bread',
            'paid_until' => now()->addYears(10),
            'timezone' => 'Africa/Addis_Ababa',
            'default_locale' => 'en',
            'currency_code' => 'ETB',
        ]);
        $user = User::firstOrCreate(['email' => 'demo@example.com'], [
            'name' => 'Demo Owner',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => Role::Owner]);

        $current->runAs($tenant, fn () => $this->menu($tenant, $images));
    }

    private function menu(Tenant $tenant, ImageProcessor $images): void
    {
        $tag = Tag::all()->keyBy('key');
        $milk = AddonGroup::create(['name' => ['en' => 'Milk'], 'min_choices' => 0, 'max_choices' => 1]);
        foreach ([['Oat milk', 1500], ['Almond milk', 1500], ['Lactose-free', 1000]] as $i => [$name, $price]) {
            AddonOption::create(['addon_group_id' => $milk->id, 'name' => ['en' => $name], 'price_minor' => $price, 'sort' => $i]);
        }
        $extras = AddonGroup::create(['name' => ['en' => 'Extras'], 'min_choices' => 0, 'max_choices' => 3]);
        foreach ([['Avocado', 4000], ['Fried egg', 2500], ['Cheese', 3000], ['Chili sauce', 0]] as $i => [$name, $price]) {
            AddonOption::create(['addon_group_id' => $extras->id, 'name' => ['en' => $name], 'price_minor' => $price, 'sort' => $i]);
        }

        $allDay = Menu::create(['name' => ['en' => 'All day'], 'slug' => 'all-day', 'sort' => 0]);
        $breakfast = Menu::create(['name' => ['en' => 'Breakfast'], 'slug' => 'breakfast', 'schedule_mode' => Menu::SCHEDULED, 'sort' => 1]);
        foreach (range(0, 6) as $day) {
            MenuSchedule::create(['menu_id' => $breakfast->id, 'weekday' => $day, 'starts_at' => '06:30', 'ends_at' => '11:30']);
        }

        $photos = 0;
        $data = $this->catalogue($allDay, $breakfast);

        foreach ($data as $ci => [$menu, $categoryName, $categoryText, $items]) {
            $category = Category::create([
                'menu_id' => $menu->id, 'name' => ['en' => $categoryName],
                'description' => $categoryText ? ['en' => $categoryText] : null, 'sort' => $ci,
            ]);
            foreach ($items as $ii => [$name, $summary, $price, $tags, $opt]) {
                $media = ($opt['photo'] ?? false) ? $this->photo($tenant, $images, $photos++) : null;
                $sizes = $opt['sizes'] ?? null;
                $item = Item::create([
                    'category_id' => $category->id,
                    'name' => ['en' => $name],
                    'summary' => $summary ? ['en' => $summary] : null,
                    'description' => $summary ? ['en' => $summary.'. Made to order in our kitchen.'] : null,
                    'price_minor' => $sizes ? null : $price,
                    'media_id' => $media?->id,
                    'calories' => $opt['k'] ?? null,
                    'prep_minutes' => $opt['p'] ?? null,
                    'is_sold_out' => $opt['sold_out'] ?? false,
                    'is_new' => in_array('new', $tags, true),
                    'is_popular' => in_array('popular', $tags, true),
                    'sort' => $ii,
                ]);
                foreach ($sizes ?? [] as $si => [$size, $sizePrice]) {
                    ItemVariant::create(['item_id' => $item->id, 'name' => ['en' => $size], 'price_minor' => $sizePrice, 'is_default' => $si === 0, 'sort' => $si]);
                }
                $tagIds = collect($tags)->map(fn ($k) => $tag[$k]->id ?? null)->filter();
                $item->tags()->attach($tagIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenant->id]])->all());
                foreach ($opt['al'] ?? [] as $allergen) {
                    ItemAllergen::create(['item_id' => $item->id, 'allergen' => $allergen]);
                }
                $groups = array_filter([($opt['milk'] ?? false) ? $milk->id : null, ($opt['extras'] ?? false) ? $extras->id : null]);
                $item->addonGroups()->attach(collect($groups)->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenant->id, 'sort' => 0]])->all());
            }
        }
    }

    /**
     * The demo menu: [menu, category, category text, items]; each item is
     * [name, summary, price, tags, options].
     *
     * @return list<array{0: Menu, 1: string, 2: string|null, 3: list<array{0: string, 1: string|null, 2: int, 3: list<string>, 4: array{sizes?: list<array{0: string, 1: int}>, al?: list<string>, photo?: bool, extras?: bool, milk?: bool, k?: int, p?: int, sold_out?: bool}}>}>
     */
    private function catalogue(Menu $allDay, Menu $breakfast): array
    {
        return [
            [$allDay, 'Coffee', 'Roasted here every Monday.', [
                ['Buna (Ethiopian coffee)', 'Traditional jebena coffee with frankincense', 6000, ['popular'], ['sizes' => [['Cup', 6000], ['Pot for 4', 20000]]]],
                ['Macchiato', 'Espresso with a spoon of foam', 7000, [], ['milk' => true, 'al' => ['milk']]],
                ['Cappuccino', 'Double shot, silky milk', 9000, [], ['milk' => true, 'al' => ['milk'], 'sizes' => [['Regular', 9000], ['Large', 11000]]]],
                ['Latte', 'Smooth and milky', 9500, [], ['milk' => true, 'al' => ['milk']]],
                ['Flat white', 'Two ristretto shots, thin milk', 10000, [], ['milk' => true, 'al' => ['milk']]],
                ['Americano', 'Espresso and hot water', 7500, ['vegan'], []],
                ['Espresso', 'Single origin, Yirgacheffe', 5500, ['vegan'], ['sizes' => [['Single', 5500], ['Double', 7500]]]],
                ['Mocha', 'Espresso, chocolate and milk', 11000, [], ['milk' => true, 'al' => ['milk']]],
                ['Iced coffee', 'Cold brew over ice', 10000, ['vegan', 'new'], []],
                ['Spris', 'Layered coffee and tea', 6500, ['vegan'], []],
            ]],
            [$allDay, 'Tea & more', null, [
                ['Shai (spiced tea)', 'Black tea with cinnamon and cloves', 4500, ['vegan'], []],
                ['Green tea', null, 4500, ['vegan'], []],
                ['Ginger lemon honey', 'Fresh ginger, lemon, local honey', 6000, ['vegetarian'], []],
                ['Hot chocolate', 'Rich cocoa, whipped cream', 9000, ['vegetarian'], ['al' => ['milk']]],
                ['Fresh juice', 'Mango, avocado or papaya', 9000, ['vegan'], ['sizes' => [['Glass', 9000], ['Jug', 26000]]]],
                ['Spris juice', 'Layered avocado, mango and papaya', 11000, ['vegan', 'popular'], []],
                ['Sparkling water', null, 3500, ['vegan'], []],
                ['Still water', null, 2500, ['vegan'], []],
            ]],
            [$allDay, 'Bakery', 'Baked fresh every morning.', [
                ['Butter croissant', 'Flaky, golden, French butter', 6500, ['vegetarian', 'popular'], ['al' => ['gluten', 'milk', 'eggs'], 'photo' => true]],
                ['Pain au chocolat', null, 7500, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs']]],
                ['Almond croissant', 'Filled with almond cream', 8500, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs', 'nuts']]],
                ['Cinnamon roll', 'Cream cheese glaze', 8000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'photo' => true]],
                ['Banana bread', 'With walnuts', 6000, ['vegetarian'], ['al' => ['gluten', 'eggs', 'nuts']]],
                ['Dabo (honey bread)', 'Traditional spiced loaf', 5000, ['vegetarian'], ['al' => ['gluten']]],
                ['Sourdough loaf', 'To take home', 18000, ['vegan'], ['al' => ['gluten']]],
                ['Blueberry muffin', null, 6500, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs']]],
                ['Chocolate cookie', null, 4500, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs']]],
            ]],
            [$allDay, 'Sandwiches', 'Served on our sourdough.', [
                ['Chicken avocado', 'Grilled chicken, avocado, tomato, aioli', 22000, ['popular'], ['al' => ['gluten', 'eggs'], 'extras' => true, 'photo' => true, 'k' => 640, 'p' => 10]],
                ['Tuna melt', 'Tuna, cheddar, red onion', 21000, [], ['al' => ['gluten', 'fish', 'milk'], 'extras' => true]],
                ['Halloumi & veggies', 'Grilled halloumi, peppers, pesto', 20000, ['vegetarian'], ['al' => ['gluten', 'milk', 'nuts'], 'extras' => true]],
                ['Beef pastrami', 'Pickles, mustard, Swiss cheese', 25000, [], ['al' => ['gluten', 'milk', 'mustard']]],
                ['Falafel wrap', 'Hummus, salad, tahini', 18000, ['vegan'], ['al' => ['gluten', 'sesame'], 'extras' => true]],
                ['Club sandwich', 'Chicken, egg, bacon, lettuce', 24000, [], ['al' => ['gluten', 'eggs']]],
                ['Grilled cheese', 'Three cheeses', 16000, ['vegetarian'], ['al' => ['gluten', 'milk']]],
            ]],
            [$allDay, 'Ethiopian classics', null, [
                ['Shiro', 'Chickpea stew with injera', 18000, ['vegan', 'popular'], ['photo' => true, 'p' => 15]],
                ['Beyaynetu', 'Fasting platter: lentils, greens, shiro', 24000, ['vegan'], ['p' => 15]],
                ['Doro wat', 'Spicy chicken stew with egg', 32000, ['spicy'], ['al' => ['eggs'], 'p' => 20]],
                ['Tibs', 'Sautéed beef with rosemary', 30000, ['spicy'], ['sizes' => [['Half', 30000], ['Full', 52000]], 'p' => 15]],
                ['Kitfo', 'Minced beef, mitmita, ayib', 34000, ['spicy'], ['al' => ['milk']]],
                ['Misir wat', 'Red lentil stew', 16000, ['vegan', 'spicy'], []],
                ['Gomen', 'Collard greens', 14000, ['vegan'], []],
                ['Extra injera', null, 2500, ['vegan'], []],
            ]],
            [$allDay, 'Salads & bowls', null, [
                ['Garden salad', 'Seasonal greens, vinaigrette', 15000, ['vegan', 'gluten_free'], ['al' => ['mustard']]],
                ['Chicken Caesar', 'Romaine, parmesan, croutons', 22000, [], ['al' => ['gluten', 'milk', 'eggs', 'fish']]],
                ['Quinoa bowl', 'Roasted vegetables, tahini', 20000, ['vegan', 'gluten_free', 'new'], ['al' => ['sesame'], 'photo' => true]],
                ['Greek salad', 'Feta, olives, cucumber', 18000, ['vegetarian', 'gluten_free'], ['al' => ['milk']]],
            ]],
            [$allDay, 'Desserts', null, [
                ['Chocolate cake', 'Dark chocolate ganache', 11000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'photo' => true]],
                ['Cheesecake', 'Passion fruit topping', 12000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs']]],
                ['Fruit salad', 'Seasonal fruit', 8000, ['vegan', 'gluten_free'], []],
                ['Ice cream', 'Vanilla, chocolate or mango', 7000, ['vegetarian', 'gluten_free'], ['al' => ['milk'], 'sold_out' => true]],
            ]],
            [$breakfast, 'Breakfast', 'Until 11:30.', [
                ['Firfir', 'Shredded injera in spiced sauce', 15000, ['vegan', 'spicy'], []],
                ['Chechebsa', 'Flatbread with niter kibbeh and honey', 16000, ['vegetarian', 'popular'], ['al' => ['gluten', 'milk'], 'photo' => true]],
                ['Ful', 'Fava beans, egg, bread', 17000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'extras' => true]],
                ['Scrambled eggs', 'On toast with tomato', 16000, ['vegetarian'], ['al' => ['gluten', 'eggs', 'milk'], 'extras' => true]],
                ['Avocado toast', 'Chili flakes, lime', 18000, ['vegan', 'new'], ['al' => ['gluten'], 'extras' => true]],
                ['Pancakes', 'Honey and fresh fruit', 17000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs']]],
                ['Granola bowl', 'Yogurt, honey, fruit', 15000, ['vegetarian'], ['al' => ['milk', 'nuts']]],
                ['Full breakfast', 'Eggs, sausage, beans, toast', 26000, [], ['al' => ['gluten', 'eggs']]],
            ]],
        ];
    }

    /** A simple generated "food" picture, run through the normal photo pipeline. */
    private function photo(Tenant $tenant, ImageProcessor $images, int $n): ?Media
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }
        $palette = [[196, 120, 60], [120, 70, 40], [90, 140, 70], [210, 170, 90], [160, 60, 50], [230, 190, 140], [70, 50, 40], [200, 100, 80]];
        [$r, $g, $b] = $palette[$n % count($palette)];
        $img = imagecreatetruecolor(1200, 800);
        for ($y = 0; $y < 800; $y += 4) {
            $k = $y / 800;
            imagefilledrectangle($img, 0, $y, 1199, $y + 3, (int) imagecolorallocate($img, (int) (245 - 30 * $k), (int) (238 - 30 * $k), (int) (228 - 30 * $k)));
        }
        imagefilledellipse($img, 600, 420, 760, 560, (int) imagecolorallocate($img, 250, 250, 248));
        imagefilledellipse($img, 600, 420, 600, 430, (int) imagecolorallocate($img, $r, $g, $b));
        imagefilledellipse($img, 540, 380, 200, 140, (int) imagecolorallocate($img, min(255, $r + 40), min(255, $g + 40), min(255, $b + 30)));
        $path = tempnam(sys_get_temp_dir(), 'demo').'.jpg';
        imagejpeg($img, $path, 90);

        $folder = $tenant->id.'/'.Str::lower((string) Str::ulid());
        $result = $images->process($path, 'image/jpeg', 'public', $folder);
        @unlink($path);

        return Media::create([...$result, 'disk' => 'public', 'base_path' => $folder, 'mime' => 'image/jpeg', 'bytes' => 0]);
    }
}
