<?php

namespace Database\Seeders\Demo;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\BranchItemOverride;
use App\Models\BranchVariantPrice;
use App\Models\Closure;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Membership;
use App\Models\OpeningHour;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\TenantProfile;
use App\Models\User;
use App\Tenancy\CurrentTenant;

/**
 * "Buna & Bread", the main sales demo (style from DEMO_MENU_STYLE so CI can
 * measure all three): 3 menus, 10 categories, 67 dishes with real photos
 * (a few on purpose without), 2 branches with their own prices, team,
 * tables 1–12 + 1–6, promotions, hours with a break and a holiday.
 * All people, e-mails and phone numbers are fictional (example.com,
 * unassigned +251 90 / 11 000 numbers).
 */
class Cafe
{
    public const SLUG = 'demo';

    public function __construct(
        private readonly CurrentTenant $current,
        private readonly Photos $photos,
        private readonly MenuBuilder $menus,
    ) {}

    public function seed(?Plan $plan): Tenant
    {
        $tenant = Tenant::create([
            'slug' => self::SLUG, 'name' => 'Buna & Bread', 'plan_id' => $plan?->id,
            'paid_until' => now()->addYear(), 'timezone' => 'Africa/Addis_Ababa',
            'default_locale' => 'en', 'currency_code' => 'ETB',
        ]);
        $tenant->forceFill(['created_at' => now()->subMonths(11), 'qr_downloaded_at' => now()->subMonths(10)])->save();
        foreach ([
            [Role::Owner, 'demo@example.com', 'Demo Owner'],
            [Role::Manager, 'manager@example.com', 'Demo Manager'],
            [Role::Staff, 'staff@example.com', 'Demo Staff'],
        ] as [$role, $email, $name]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password']);
            $user->forceFill(['email_verified_at' => now()])->save();
            Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => $role]);
        }

        $this->current->runAs($tenant, function () use ($tenant) {
            $items = $this->menus->build($tenant, self::catalogue());
            $this->look($tenant);
            $this->info();
            $this->branches($items);
            $this->promotions($tenant, $items);
        });

        return $tenant;
    }

    private function look(Tenant $tenant): void
    {
        TenantAppearance::create([
            ...TenantAppearance::DEFAULTS,
            'style' => TenantAppearance::effectiveStyle(config('menu.demo_style'), TenantAppearance::STYLES),
            'primary_color' => '#9a3b1f',
            'banner_text' => 'under',
            'cover_media_id' => $this->photos->media($tenant, 'cafe-rustic', true)?->id,
            'logo_media_id' => $this->photos->logo($tenant, '#9a3b1f', 'bean')?->id,
        ]);
    }

    private function info(): void
    {
        TenantProfile::create([
            'description' => ['en' => "Ethiopian coffee roasted here every Monday, bread from our oven every morning.\nCome for a quick macchiato or stay for the Sunday buna ceremony."],
            'address' => ['en' => 'Bole Road, near Edna Mall, Addis Ababa'],
            'maps_url' => 'https://maps.google.com/?q=Bole+Road+Addis+Ababa',
            'phone' => '+251 11 000 0142',
            'whatsapp' => '+251 90 000 0142',
            'telegram' => 'bunaandbread_demo',
            'email' => 'hello@example.com',
            'socials' => [
                'instagram' => 'https://instagram.com/bunaandbread.demo',
                'facebook' => 'https://facebook.com/bunaandbread.demo',
                'tiktok' => 'https://tiktok.com/@bunaandbread.demo',
            ],
            'wifi_name' => 'BunaGuest',
            'wifi_password' => 'coffee2026',
            'show_wifi' => true,
        ]);
        // 0 = Sunday. Weekdays close for a short break in the afternoon.
        foreach (range(0, 6) as $day) {
            $rows = match ($day) {
                0 => [['08:00', '21:00']],
                6 => [['07:00', '23:00']],
                default => [['06:00', '14:30'], ['16:00', '22:00']],
            };
            foreach ($rows as [$open, $close]) {
                OpeningHour::create(['branch_id' => null, 'weekday' => $day, 'opens_at' => $open, 'closes_at' => $close]);
            }
        }
        $genna = now()->month <= 1 && now()->day < 7 ? now()->setDate(now()->year, 1, 7) : now()->setDate(now()->year + 1, 1, 7);
        Closure::create(['branch_id' => null, 'starts_on' => $genna->toDateString(), 'ends_on' => $genna->toDateString(), 'note' => ['en' => 'Closed for Ethiopian Christmas (Genna).']]);
    }

    /** @param  array<string, Item>  $items */
    private function branches(array $items): void
    {
        $bole = Branch::where('is_default', true)->firstOrFail();
        $bole->update(['slug' => 'bole', 'name' => ['en' => 'Bole'], 'address' => ['en' => 'Bole Road, Addis Ababa'], 'phone' => '+251 11 000 0142']);
        $piassa = Branch::create(['slug' => 'piassa', 'name' => ['en' => 'Piassa'], 'address' => ['en' => 'Churchill Avenue, Piassa, Addis Ababa'], 'phone' => '+251 11 000 0143', 'sort' => 1]);
        foreach ([[$bole, 12], [$piassa, 6]] as [$branch, $count]) {
            foreach (range(1, $count) as $n) {
                $table = DiningTable::create(['branch_id' => $branch->id, 'label' => (string) $n, 'sort' => $n]);
                QrCode::create(['code' => QrCode::newCode(), 'branch_id' => $branch->id, 'dining_table_id' => $table->id, 'label' => 'Table '.$n]);
            }
        }

        // Piassa is a little cheaper on food and has run out of kitfo today.
        foreach (['Shiro' => 21000, 'Beyaynetu' => 26000, 'Buna classic burger' => 36000, 'Spaghetti bolognese' => 31000, 'Club sandwich' => 30000, 'Macchiato' => 6500] as $name => $price) {
            BranchItemOverride::create(['branch_id' => $piassa->id, 'item_id' => $items[$name]->id, 'price_minor' => $price]);
        }
        BranchItemOverride::create(['branch_id' => $piassa->id, 'item_id' => $items['Kitfo']->id, 'is_sold_out' => true]);
        foreach ($items['Cappuccino']->variants as $variant) {
            BranchVariantPrice::create(['branch_id' => $piassa->id, 'item_variant_id' => $variant->id, 'price_minor' => $variant->price_minor - 1000]);
        }
    }

    /** @param  array<string, Item>  $items */
    private function promotions(Tenant $tenant, array $items): void
    {
        Promotion::create([
            'type' => 'special', 'title' => ['en' => 'Sunday buna ceremony'],
            'body' => ['en' => 'A full jebena for the table, roasted in front of you, with popcorn. Sundays from 15:00.'],
            'item_id' => $items['Buna (Ethiopian coffee)']->id, 'media_id' => $this->photos->media($tenant, 'ceremony')?->id, 'sort' => 0,
        ]);
        Promotion::create([
            'type' => 'special', 'title' => ['en' => 'Weekend brunch for two'],
            'body' => ['en' => 'Two breakfast plates, two coffees and a fresh juice to share — Br 780 on Saturdays and Sundays.'],
            'media_id' => $this->photos->media($tenant, 'brunch')?->id, 'sort' => 1,
        ]);
        Promotion::create(['type' => 'announcement', 'title' => ['en' => 'New:'], 'body' => ['en' => 'Avocado spris and mango juice pressed every morning.'], 'sort' => 2]);
        Promotion::create(['type' => 'announcement', 'title' => ['en' => 'Holiday:'], 'body' => ['en' => 'Closed for Genna (Ethiopian Christmas) on 7 January.'], 'sort' => 3]);
    }

    /**
     * Prices in santim (Br 1 = 100). Photo keys refer to demo-photos/photos.json.
     *
     * @return array<string, mixed>
     */
    public static function catalogue(): array
    {
        return [
            'groups' => [
                'milk' => ['Milk', 0, 1, [['Oat milk', 1500], ['Almond milk', 1500], ['Lactose-free milk', 1000]]],
                'shot' => ['Extra shot or syrup', 0, 2, [['Extra espresso shot', 2000], ['Vanilla syrup', 1500], ['Caramel syrup', 1500, true]]],
                'eggs' => ['How would you like your eggs?', 1, 1, [['Fried', 0], ['Scrambled', 0], ['Poached', 0]]],
                'breakfast' => ['Add to your breakfast', 0, 3, [['Avocado', 4000], ['Extra egg', 2500], ['Grilled tomato', 1500], ['Beef sausage', 5000]]],
                'burger' => ['Burger extras', 0, 4, [['Cheddar', 3000], ['Bacon', 5000], ['Fried egg', 2500], ['Jalapeños', 1500], ['Extra patty', 9000]]],
                'side' => ['Choose a side', 1, 1, [['Herb fries', 0], ['Side salad', 0], ['Sweet potato fries', 2500]]],
                'spice' => ['Spice level', 1, 1, [['Mild', 0], ['Medium', 0], ['Hot (with mitmita)', 0]]],
                'pasta' => ['Pasta extras', 0, 2, [['Parmesan', 2500], ['Grilled chicken', 7000], ['Garlic bread', 3500]]],
                'sweet' => ['Toppings', 0, 2, [['Honey', 1000], ['Fresh berries', 3500], ['Chocolate sauce', 2000], ['Whipped cream', 1500]]],
            ],
            'menus' => [
                ['name' => 'Breakfast', 'slug' => 'breakfast', 'schedule' => ['06:00', '11:00'], 'categories' => [
                    ['Breakfast', 'Served 06:00–11:00, every day.', [
                        ['Full breakfast', 'Eggs, beef sausage, beans, grilled tomato, toast', 32000, ['popular'], ['d' => 'Our big plate: two eggs your way, beef sausage, baked beans, grilled tomato and sourdough toast.', 'al' => ['gluten', 'eggs'], 'k' => 890, 'pm' => 15, 'ph' => ['full-breakfast'], 'g' => ['eggs', 'breakfast']]],
                        ['Eggs on toast', 'Two poached eggs on sourdough', 19000, ['vegetarian'], ['d' => 'Soft poached eggs on toasted sourdough with herbs and a little chili oil.', 'al' => ['gluten', 'eggs'], 'k' => 420, 'pm' => 10, 'ph' => ['poached-eggs'], 'g' => ['eggs', 'breakfast']]],
                        ['Scrambled eggs', 'Creamy eggs, toast, sweet tea', 17000, ['vegetarian'], ['al' => ['gluten', 'eggs', 'milk'], 'k' => 460, 'pm' => 10, 'ph' => ['eggs-tea'], 'g' => ['breakfast']]],
                        ['Avocado toast', 'Smashed avocado, lime, chili flakes, egg', 22000, ['vegetarian', 'new'], ['d' => 'Ethiopian avocado smashed with lime on sourdough, topped with a soft egg and chili flakes.', 'al' => ['gluten', 'eggs'], 'k' => 510, 'pm' => 8, 'ph' => ['avocado-toast'], 'g' => ['breakfast']]],
                        ['Pancakes', 'Three fluffy pancakes, honey, fresh fruit', 21000, ['vegetarian', 'popular'], ['d' => 'A stack of three buttermilk pancakes with local honey and seasonal fruit.', 'al' => ['gluten', 'milk', 'eggs'], 'k' => 640, 'pm' => 12, 'ph' => ['pancakes', 'pancakes-berries', 'pancakes-strawberry'], 'g' => ['sweet']]],
                        ['Waffles', 'Golden waffles, honey and cream', 20000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 580, 'pm' => 12, 'ph' => ['waffle'], 'g' => ['sweet']]],
                        ['Granola bowl', 'Yogurt, house granola, berries, honey', 18000, ['vegetarian'], ['d' => 'Thick yogurt with our oat and teff granola, berries and a drizzle of honey.', 'al' => ['milk', 'nuts', 'gluten'], 'k' => 430, 'pm' => 5, 'ph' => ['granola', 'yogurt-bowls']]],
                        ['Chechebsa', 'Flatbread with niter kibbeh, berbere and honey', 16000, ['vegetarian', 'spicy'], ['d' => 'Torn flatbread tossed in spiced butter and berbere, served with honey and yogurt.', 'al' => ['gluten', 'milk'], 'k' => 610, 'pm' => 12]],
                        ['Ful', 'Fava beans, egg, tomato, warm bread', 17000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'k' => 520, 'pm' => 10, 'g' => ['spice']]],
                    ]],
                ]],
                ['name' => 'All day', 'slug' => 'all-day', 'categories' => [
                    ['Ethiopian classics', 'Served with fresh injera.', [
                        ['Beyaynetu', 'Fasting platter: shiro, misir, gomen, atkilt', 28000, ['vegan', 'popular'], ['d' => 'A colourful platter of our vegan stews on injera — perfect to share.', 'k' => 780, 'pm' => 15, 'ph' => ['injera-platter', 'injera-dishes'], 'g' => ['spice']]],
                        ['Shiro', 'Silky chickpea stew', 22000, ['vegan'], ['k' => 540, 'pm' => 15, 'g' => ['spice']]],
                        ['Tibs', 'Sautéed beef, rosemary, onion, jalapeño', 36000, ['spicy', 'popular'], ['d' => 'Tender beef cubes sautéed with rosemary, onion and green chili, served sizzling.', 'k' => 720, 'pm' => 18, 'ph' => ['tibs'], 'sz' => [['Half', 36000], ['Full', 62000]], 'g' => ['spice']]],
                        ['Doro wat', 'Slow-cooked chicken stew with egg', 42000, ['spicy'], ['d' => 'Chicken simmered for hours in berbere and onions, with a boiled egg and ayib.', 'al' => ['eggs', 'milk'], 'k' => 820, 'pm' => 20]],
                        ['Kitfo', 'Minced beef, mitmita, ayib, gomen', 44000, ['spicy'], ['al' => ['milk'], 'k' => 760, 'pm' => 12, 'g' => ['spice']]],
                        ['Misir wat', 'Red lentils in berbere sauce', 19000, ['vegan', 'spicy'], ['k' => 480, 'pm' => 12]],
                        ['Gomen', 'Collard greens with garlic and ginger', 17000, ['vegan', 'gluten_free'], ['k' => 260, 'pm' => 10]],
                        ['Extra injera', null, 3000, ['vegan'], []],
                    ]],
                    ['Burgers & sandwiches', 'Burgers come with a side of your choice.', [
                        ['Buna classic burger', 'Beef patty, cheddar, tomato, house sauce', 38000, ['popular'], ['d' => '180 g beef patty on a brioche bun with cheddar, tomato, pickles and our house sauce.', 'al' => ['gluten', 'milk', 'eggs', 'mustard'], 'k' => 980, 'pm' => 15, 'ph' => ['burger-half', 'burger-board'], 'g' => ['side', 'burger']]],
                        ['Cheeseburger', 'Double cheese, pickles, onion', 36000, [], ['al' => ['gluten', 'milk', 'eggs', 'mustard'], 'k' => 920, 'pm' => 15, 'ph' => ['cheeseburger'], 'g' => ['side', 'burger']]],
                        ['Smoky bacon burger', 'Bacon, cheddar, caramelised onion', 42000, ['new'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 1080, 'pm' => 15, 'ph' => ['burger-bacon'], 'g' => ['side', 'burger']]],
                        ['Club sandwich', 'Chicken, egg, bacon, lettuce, tomato', 32000, [], ['d' => 'Three layers of toasted bread with grilled chicken, egg, bacon, lettuce and tomato.', 'al' => ['gluten', 'eggs'], 'k' => 760, 'pm' => 12, 'ph' => ['club-sandwich'], 'g' => ['side']]],
                        ['BLT', 'Bacon, lettuce, tomato, mayo', 26000, [], ['al' => ['gluten', 'eggs'], 'k' => 610, 'pm' => 10, 'ph' => ['blt']]],
                        ['Grilled cheese', 'Three cheeses on sourdough', 21000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 640, 'pm' => 10, 'ph' => ['grilled-cheese']]],
                        ['Toastie & fries', 'Ham and cheese toastie with fries', 27000, [], ['al' => ['gluten', 'milk'], 'k' => 830, 'pm' => 12, 'ph' => ['toastie-fries']]],
                        ['Herb fries', 'Crispy fries, rosemary salt', 12000, ['vegan', 'gluten_free'], ['k' => 420, 'pm' => 8, 'ph' => ['fries-bowl']]],
                    ]],
                    ['Salads & bowls', null, [
                        ['Garden salad', 'Seasonal greens, peppers, vinaigrette', 18000, ['vegan', 'gluten_free'], ['al' => ['mustard'], 'k' => 240, 'pm' => 8, 'ph' => ['garden-salad']]],
                        ['Grilled chicken bowl', 'Chicken, greens, grains, yogurt dressing', 29000, ['popular'], ['d' => 'Grilled chicken thigh on mixed greens and grains with a lemon yogurt dressing.', 'al' => ['milk'], 'k' => 560, 'pm' => 12, 'ph' => ['chicken-bowl']]],
                        ['Beetroot & feta', 'Roasted beetroot, greens, lemon', 22000, ['vegetarian', 'gluten_free'], ['al' => ['milk'], 'k' => 310, 'pm' => 8, 'ph' => ['beet-salad']]],
                        ['Rainbow bowl', 'Quinoa, roast vegetables, hummus, seeds', 24000, ['vegan', 'new'], ['al' => ['sesame'], 'k' => 520, 'pm' => 10, 'ph' => ['salad-bowls']]],
                    ]],
                    ['Pasta', 'Fresh pasta, made to order.', [
                        ['Spaghetti bolognese', 'Slow-cooked beef ragù, parmesan', 33000, ['popular'], ['al' => ['gluten', 'milk', 'celery'], 'k' => 820, 'pm' => 15, 'ph' => ['bolognese'], 'g' => ['pasta']]],
                        ['Spaghetti pomodoro', 'Tomato, garlic, basil', 26000, ['vegan'], ['al' => ['gluten'], 'k' => 610, 'pm' => 12, 'ph' => ['pomodoro'], 'g' => ['pasta']]],
                        ['Carbonara', 'Egg, pecorino, crispy beef bacon, pepper', 34000, [], ['al' => ['gluten', 'eggs', 'milk'], 'k' => 870, 'pm' => 12, 'ph' => ['carbonara'], 'g' => ['pasta']]],
                        ['Creamy mushroom pasta', 'Mushrooms, spinach, cream', 31000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 790, 'pm' => 14, 'ph' => ['pasta-mushroom'], 'g' => ['pasta']]],
                        ['Penne alfredo', 'Parmesan cream sauce, herbs', 29000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 840, 'pm' => 12, 'ph' => ['pasta-cream'], 'g' => ['pasta']]],
                    ]],
                    ['Desserts & pastries', 'Baked here every morning.', [
                        ['Chocolate cake', 'Dark chocolate layers', 14000, ['vegetarian', 'popular'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 480, 'ph' => ['chocolate-cake']]],
                        ['Berry cheesecake', 'Baked cheesecake, fresh berries', 15000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 430, 'ph' => ['cheesecake']]],
                        ['Ice cream', 'Three scoops: vanilla, chocolate, mango', 11000, ['vegetarian', 'gluten_free'], ['al' => ['milk'], 'k' => 360, 'ph' => ['ice-cream'], 'so' => true]],
                        ['Fruit salad', 'Seasonal fruit, lime, mint', 12000, ['vegan', 'gluten_free'], ['k' => 180, 'ph' => ['fruit-salad']]],
                        ['Butter croissant', 'Flaky and golden', 8500, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 310, 'ph' => ['croissants']]],
                        ['Pain au chocolat', 'Croissant dough, dark chocolate', 9500, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 340, 'ph' => ['pain-choc']]],
                        ['Sweet rolls', 'Soft rolls with powdered sugar', 8000, ['vegetarian', 'new'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 290, 'ph' => ['sweet-rolls']]],
                        ['Chocolate cupcake', 'Whipped cream and a cherry', 9000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 330, 'ph' => ['cupcake']]],
                    ]],
                ]],
                ['name' => 'Drinks', 'slug' => 'drinks', 'categories' => [
                    ['Coffee', 'Single-origin beans, roasted here every Monday.', [
                        ['Buna (Ethiopian coffee)', 'Jebena coffee with frankincense', 7000, ['popular'], ['d' => 'Brewed in a clay jebena and served in small cups, the traditional way.', 'k' => 5, 'ph' => ['jebena', 'ceremony', 'ceremony-2'], 'sz' => [['Cup', 7000], ['Jebena for 4', 24000]]]],
                        ['Espresso', 'Yirgacheffe, bright and fruity', 6500, ['vegan'], ['k' => 5, 'ph' => ['espresso'], 'sz' => [['Single', 6500], ['Double', 9000]]]],
                        ['Macchiato', 'Espresso with a cloud of foam', 7500, ['popular'], ['al' => ['milk'], 'k' => 40, 'ph' => ['macchiato'], 'g' => ['milk', 'shot']]],
                        ['Cappuccino', 'Double shot, silky milk', 10000, [], ['al' => ['milk'], 'k' => 120, 'ph' => ['cappuccino', 'cappuccino-2'], 'sz' => [['Regular', 10000], ['Large', 12500]], 'g' => ['milk', 'shot']]],
                        ['Latte', 'Smooth and milky', 10500, [], ['al' => ['milk'], 'k' => 160, 'ph' => ['latte', 'latte-biscotti'], 'sz' => [['Regular', 10500], ['Large', 13000]], 'g' => ['milk', 'shot']]],
                        ['Americano', 'Espresso and hot water', 8000, ['vegan'], ['k' => 10, 'g' => ['shot']]],
                        ['Mocha', 'Espresso, chocolate, milk', 12000, [], ['al' => ['milk'], 'k' => 260, 'ph' => ['mocha'], 'g' => ['milk', 'shot']]],
                        ['Iced coffee', 'Espresso, milk and ice', 11500, ['new'], ['al' => ['milk'], 'k' => 140, 'ph' => ['iced-coffee'], 'g' => ['milk', 'shot']]],
                        ['Cold brew', 'Steeped for 18 hours', 12000, ['vegan'], ['k' => 10, 'ph' => ['cold-brew'], 'so' => true]],
                    ]],
                    ['Tea', null, [
                        ['Shai', 'Black tea with cinnamon and cloves', 5000, ['vegan', 'popular'], ['k' => 30, 'ph' => ['spiced-tea'], 'sz' => [['Cup', 5000], ['Pot for 2', 9000]]]],
                        ['Chai latte', 'Spiced tea with steamed milk', 9500, ['vegetarian'], ['al' => ['milk'], 'k' => 150, 'ph' => ['chai-latte'], 'g' => ['milk']]],
                        ['Green tea', 'Japanese sencha', 6000, ['vegan'], ['k' => 0, 'ph' => ['green-tea']]],
                        ['Ginger honey lemon', 'Fresh ginger, lemon, local honey', 7000, ['vegetarian'], ['k' => 70, 'ph' => ['ginger-tea']]],
                        ['Hot chocolate', 'Rich cocoa, steamed milk', 11000, ['vegetarian'], ['al' => ['milk'], 'k' => 290, 'ph' => ['hot-chocolate'], 'g' => ['milk']]],
                    ]],
                    ['Fresh juices', 'Pressed to order — no sugar added.', [
                        ['Avocado spris', 'Layered avocado, mango and papaya', 13000, ['vegan', 'popular'], ['d' => 'Our favourite: thick layers of avocado, mango and papaya with a squeeze of lime.', 'k' => 320, 'ph' => ['avocado-smoothie'], 'sz' => [['Glass', 13000], ['Jug', 36000]]]],
                        ['Mango juice', 'Ripe Ethiopian mango', 11000, ['vegan'], ['k' => 180, 'ph' => ['mango-juice'], 'sz' => [['Glass', 11000], ['Jug', 30000]]]],
                        ['Orange juice', 'Freshly squeezed', 10000, ['vegan', 'gluten_free'], ['k' => 140, 'ph' => ['orange-juice']]],
                        ['Strawberry smoothie', 'Strawberries, banana, yogurt', 12500, ['vegetarian'], ['al' => ['milk'], 'k' => 240, 'ph' => ['strawberry-smoothie']]],
                        ['Kiwi & apple', 'Kiwi, green apple, mint', 12000, ['vegan', 'new'], ['k' => 160, 'ph' => ['kiwi-juice']]],
                        ['Tropical trio', 'Banana, kiwi and orange smoothies', 14000, ['vegan'], ['k' => 280, 'ph' => ['smoothies']]],
                    ]],
                    ['Soft drinks', null, [
                        ['Iced lemon tea', 'House-made, lightly sweet', 8000, ['vegan'], ['k' => 90, 'ph' => ['iced-lemon-tea']]],
                        ['Mint lemonade', 'Lemon, fresh mint, soda', 8500, ['vegan'], ['k' => 110, 'ph' => ['iced-mint-tea']]],
                        ['Cola', 'Chilled, with ice and lemon', 6000, ['vegan'], ['k' => 140, 'ph' => ['soda-glass'], 'sz' => [['Small', 6000], ['Large', 9000]]]],
                        ['Sparkling water', null, 5000, ['vegan'], ['ph' => ['sparkling-water']]],
                        ['Still water', null, 3500, ['vegan'], []],
                    ]],
                ]],
            ],
        ];
    }
}
