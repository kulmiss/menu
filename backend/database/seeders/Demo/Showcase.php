<?php

namespace Database\Seeders\Demo;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\DiningTable;
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
 * Three more complete restaurants with real photos, one per menu style, to
 * show restaurants what each style looks like:
 * A editorial (fine dining), B grid (pizza & burgers), C bistro (bakery & juice bar).
 */
class Showcase
{
    public function __construct(
        private readonly CurrentTenant $current,
        private readonly Photos $photos,
        private readonly MenuBuilder $menus,
    ) {}

    /** @param  array<string, Plan>  $plans
     * @return list<Tenant> */
    public function seed(array $plans): array
    {
        return array_map(fn (array $def) => $this->restaurant($def, $plans), self::restaurants());
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, Plan>  $plans
     */
    private function restaurant(array $def, array $plans): Tenant
    {
        $tenant = Tenant::create([
            'slug' => $def['slug'], 'name' => $def['name'], 'plan_id' => $plans[$def['plan']]->id ?? null,
            'paid_until' => now()->addMonths(5), 'timezone' => 'Africa/Addis_Ababa', 'default_locale' => 'en', 'currency_code' => 'ETB',
        ]);
        $tenant->forceFill(['created_at' => now()->subMonths($def['age']), 'qr_downloaded_at' => now()->subMonths($def['age'] - 1)])->save();
        $user = User::firstOrCreate(['email' => $def['email']], ['name' => 'Demo Owner · '.$def['name'], 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now()])->save();
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => Role::Owner]);

        $this->current->runAs($tenant, function () use ($tenant, $def) {
            $items = $this->menus->build($tenant, $def['catalogue']);
            TenantAppearance::create([
                ...TenantAppearance::DEFAULTS,
                'style' => $def['style'], 'primary_color' => $def['color'], 'banner_text' => $def['banner_text'],
                'cover_media_id' => $this->photos->media($tenant, $def['banner'], true)?->id,
                'logo_media_id' => $this->photos->logo($tenant, $def['color'], $def['logo'])?->id,
            ]);
            TenantProfile::create([
                'description' => ['en' => $def['about']], 'address' => ['en' => $def['address']],
                'maps_url' => 'https://maps.google.com/?q='.urlencode($def['address']),
                'phone' => $def['phone'], 'whatsapp' => str_replace('+251 11', '+251 90', $def['phone']),
                'email' => $def['slug'].'@example.com',
                'socials' => ['instagram' => 'https://instagram.com/'.$def['slug'], 'website' => 'https://example.com/'.$def['slug']],
            ]);
            foreach (range(0, 6) as $day) {
                foreach ($def['hours'] as [$open, $close]) {
                    OpeningHour::create(['branch_id' => null, 'weekday' => $day, 'opens_at' => $open, 'closes_at' => $close]);
                }
            }
            $branch = Branch::where('is_default', true)->firstOrFail();
            foreach (range(1, 8) as $n) {
                $table = DiningTable::create(['branch_id' => $branch->id, 'label' => (string) $n, 'sort' => $n]);
                QrCode::create(['code' => QrCode::newCode(), 'branch_id' => $branch->id, 'dining_table_id' => $table->id, 'label' => 'Table '.$n]);
            }
            [$title, $body, $dish, $photo] = $def['special'];
            Promotion::create(['type' => 'special', 'title' => ['en' => $title], 'body' => ['en' => $body], 'item_id' => $items[$dish]->id ?? null, 'media_id' => $photo ? $this->photos->media($tenant, $photo)?->id : null, 'sort' => 0]);
            Promotion::create(['type' => 'announcement', 'title' => ['en' => $def['news'][0]], 'body' => ['en' => $def['news'][1]], 'sort' => 1]);
        });

        return $tenant;
    }

    /** @return list<array<string, mixed>> */
    public static function restaurants(): array
    {
        return [
            [
                'slug' => 'demo-fine', 'name' => 'Lumière Table', 'email' => 'fine@example.com', 'plan' => 'business', 'age' => 9,
                'style' => 'editorial', 'color' => '#3d3a6b', 'logo' => 'ring', 'banner' => 'fine-interior', 'banner_text' => 'over',
                'about' => "A small dining room for slow evenings: seasonal plates, a short wine list and quiet music.\nBooking recommended on Fridays and Saturdays.",
                'address' => 'Kazanchis, Addis Ababa', 'phone' => '+251 11 000 0211', 'hours' => [['18:00', '23:30']],
                'special' => ['Chef\'s tasting evening', 'Five courses chosen by the chef, every Thursday — Br 3,900 per guest.', 'Beef tenderloin', 'fine-plated'],
                'news' => ['Tonight:', 'Live piano from 20:00.'],
                'catalogue' => [
                    'groups' => [
                        'doneness' => ['How would you like it cooked?', 1, 1, [['Rare', 0], ['Medium rare', 0], ['Medium', 0], ['Well done', 0]]],
                        'sides' => ['Sides', 0, 2, [['Truffle mash', 9000], ['Grilled asparagus', 8000], ['Green salad', 6000]]],
                    ],
                    'menus' => [
                        ['name' => 'Dinner', 'slug' => 'dinner', 'schedule' => ['18:00', '23:30'], 'categories' => [
                            ['Starters', null, [
                                ['Garden plate', 'Heritage vegetables, herb oil, seeds', 42000, ['vegan', 'gluten_free'], ['k' => 220, 'ph' => ['fine-salad']]],
                                ['Crispy croquette', 'Smoked cheese, microgreens, aioli', 46000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 340, 'ph' => ['fine-crispy']]],
                                ['Chef\'s amuse', 'Today\'s bite from the kitchen', 38000, ['new'], ['ph' => ['fine-starter']]],
                                ['Lentil velouté', 'Red lentils, cumin, crème fraîche', 34000, ['vegetarian', 'gluten_free'], ['al' => ['milk'], 'k' => 260]],
                            ]],
                            ['Mains', 'Served with seasonal vegetables.', [
                                ['Beef tenderloin', '200 g, red wine jus, microgreens', 135000, ['popular'], ['d' => 'Grass-fed tenderloin, seared and rested, with a red wine jus.', 'k' => 690, 'pm' => 25, 'ph' => ['steak-micro', 'steak-veg'], 'g' => ['doneness', 'sides']]],
                                ['Ribeye & pepper sauce', '300 g, asparagus, tomato', 148000, [], ['k' => 880, 'pm' => 25, 'ph' => ['steak-sauce'], 'g' => ['doneness', 'sides']]],
                                ['Seafood linguine', 'Prawns, mushrooms, cream', 98000, [], ['al' => ['gluten', 'crustaceans', 'milk'], 'k' => 760, 'pm' => 18, 'ph' => ['seafood-pasta']]],
                                ['Prawn tagliatelle', 'Garlic butter, lemon, chili', 104000, ['spicy'], ['al' => ['gluten', 'crustaceans', 'milk'], 'k' => 720, 'pm' => 18, 'ph' => ['shrimp-pasta']]],
                                ['Wild mushroom risotto', 'Parmesan, thyme', 78000, ['vegetarian', 'gluten_free'], ['al' => ['milk'], 'k' => 640, 'pm' => 22]],
                                ['Nile perch', 'Beurre blanc, crushed potatoes', 112000, ['gluten_free'], ['al' => ['fish', 'milk'], 'k' => 590, 'pm' => 20, 'so' => true]],
                            ]],
                            ['Desserts', null, [
                                ['Orange panna cotta', 'Walnut, pomegranate', 36000, ['vegetarian', 'gluten_free'], ['al' => ['milk', 'nuts'], 'k' => 320, 'ph' => ['panna-cotta']]],
                                ['Dessert trio', 'Chocolate mousse, cheesecake, berries', 42000, ['vegetarian', 'popular'], ['al' => ['milk', 'eggs', 'gluten'], 'k' => 520, 'ph' => ['dessert-plate']]],
                                ['Cheese selection', 'Three cheeses, honey, crackers', 48000, ['vegetarian'], ['al' => ['milk', 'gluten']]],
                            ]],
                        ]],
                        ['name' => 'Wine & drinks', 'slug' => 'drinks', 'categories' => [
                            ['By the glass', null, [
                                ['House red', 'Ethiopian Rift Valley red', 28000, ['vegan'], ['sz' => [['Glass', 28000], ['Bottle', 140000]]]],
                                ['House white', 'Crisp and dry', 28000, ['vegan'], ['sz' => [['Glass', 28000], ['Bottle', 140000]]]],
                                ['Sparkling', 'Brut, chilled', 36000, ['vegan'], []],
                                ['Espresso', null, 9000, ['vegan'], []],
                                ['Fresh mint tea', null, 9000, ['vegan'], []],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-pizza', 'name' => 'Stack & Slice', 'email' => 'pizza@example.com', 'plan' => 'pro', 'age' => 7,
                'style' => 'grid', 'color' => '#b3261e', 'logo' => 'slice', 'banner' => 'pizzeria-interior', 'banner_text' => 'under',
                'about' => "Wood-fired pizza, smashed burgers and crispy chicken.\nOrder at the table with your waiter — slices to go at the counter.",
                'address' => 'Sarbet, Addis Ababa', 'phone' => '+251 11 000 0322', 'hours' => [['11:00', '23:00']],
                'special' => ['Two pizzas, one price', 'Any two large pizzas for Br 1,200 every Tuesday.', 'Pepperoni', 'pizzaiolo'],
                'news' => ['Now open:', 'Late kitchen on Fridays until 01:00.'],
                'catalogue' => [
                    'groups' => [
                        'crust' => ['Crust', 1, 1, [['Classic', 0], ['Thin', 0], ['Stuffed crust', 6000]]],
                        'toppings' => ['Extra toppings', 0, 4, [['Mozzarella', 4000], ['Mushrooms', 3000], ['Jalapeños', 2500], ['Olives', 2500], ['Beef pepperoni', 5000]]],
                        'dip' => ['Dip', 0, 2, [['Garlic mayo', 1500], ['BBQ', 1500], ['Hot sauce', 1500]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Pizza', 'Wood-fired, 30 cm or 40 cm.', [
                                ['Margherita', 'Tomato, mozzarella, basil', 42000, ['vegetarian', 'popular'], ['al' => ['gluten', 'milk'], 'k' => 820, 'pm' => 12, 'ph' => ['pizzas-plates'], 'sz' => [['Medium', 42000], ['Large', 58000]], 'g' => ['crust', 'toppings']]],
                                ['Pepperoni', 'Beef pepperoni, mozzarella, oregano', 52000, ['popular'], ['al' => ['gluten', 'milk'], 'k' => 980, 'pm' => 12, 'ph' => ['pepperoni-slice', 'pizza-checkered'], 'sz' => [['Medium', 52000], ['Large', 69000]], 'g' => ['crust', 'toppings']]],
                                ['Ham & mushroom', 'Ham, mushrooms, mozzarella', 54000, [], ['al' => ['gluten', 'milk'], 'k' => 940, 'pm' => 12, 'ph' => ['pizza-ham'], 'sz' => [['Medium', 54000], ['Large', 71000]], 'g' => ['crust', 'toppings']]],
                                ['Veggie garden', 'Peppers, olives, onion, tomato', 46000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 780, 'pm' => 12, 'ph' => ['pizzas-wood'], 'sz' => [['Medium', 46000], ['Large', 62000]], 'g' => ['crust', 'toppings']]],
                                ['Four cheese', 'Mozzarella, gorgonzola, parmesan, ayib', 56000, ['vegetarian', 'new'], ['al' => ['gluten', 'milk'], 'k' => 1020, 'pm' => 12, 'ph' => ['pizzas-wine'], 'sz' => [['Medium', 56000], ['Large', 74000]], 'g' => ['crust']]],
                                ['Berbere chicken', 'Spiced chicken, onion, green chili', 55000, ['spicy'], ['al' => ['gluten', 'milk'], 'k' => 910, 'pm' => 12, 'sz' => [['Medium', 55000], ['Large', 72000]], 'g' => ['crust', 'toppings']]],
                            ]],
                            ['Burgers', 'Smashed patties, potato bun.', [
                                ['Stack burger', 'Double smashed beef, cheese, pickles', 44000, ['popular'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 1050, 'pm' => 10, 'ph' => ['burger-tray']]],
                                ['Bacon double', 'Bacon, cheddar, BBQ sauce', 48000, [], ['al' => ['gluten', 'milk'], 'k' => 1180, 'pm' => 10, 'ph' => ['burger-boards']]],
                                ['Crispy chicken burger', 'Fried chicken, slaw, hot mayo', 40000, ['spicy'], ['al' => ['gluten', 'eggs'], 'k' => 940, 'pm' => 10]],
                            ]],
                            ['Chicken', null, [
                                ['Chicken wings', 'Eight crispy wings', 36000, ['spicy'], ['al' => ['gluten'], 'k' => 780, 'pm' => 14, 'ph' => ['wings'], 'g' => ['dip'], 'sz' => [['8 pieces', 36000], ['16 pieces', 64000]]]],
                                ['Chicken & fries', 'Three pieces, fries, dip', 38000, [], ['al' => ['gluten'], 'k' => 990, 'pm' => 14, 'ph' => ['chicken-fries'], 'g' => ['dip']]],
                                ['Chicken plate', 'Crispy chicken, fries, salad', 42000, ['new'], ['al' => ['gluten'], 'k' => 1010, 'pm' => 14, 'ph' => ['chicken-plate'], 'g' => ['dip']]],
                            ]],
                            ['Sides & drinks', null, [
                                ['Fries', 'With ketchup', 14000, ['vegan'], ['k' => 420, 'ph' => ['fries-ketchup'], 'g' => ['dip']]],
                                ['Onion rings', null, 16000, ['vegetarian'], ['al' => ['gluten'], 'k' => 460]],
                                ['Cola', null, 6000, ['vegan'], ['sz' => [['Small', 6000], ['Large', 9000]]]],
                                ['Vanilla milkshake', null, 15000, ['vegetarian'], ['al' => ['milk'], 'k' => 520, 'so' => true]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-bakery', 'name' => 'Crumb & Juice', 'email' => 'bakery@example.com', 'plan' => 'starter', 'age' => 5,
                'style' => 'bistro', 'color' => '#2e6b47', 'logo' => 'wheat', 'banner' => 'bakery-interior', 'banner_text' => 'under',
                'about' => "Sourdough, pastries and cold-pressed juice from 06:30.\nEverything is baked on site — come early for warm croissants.",
                'address' => 'Old Airport, Addis Ababa', 'phone' => '+251 11 000 0433', 'hours' => [['06:30', '20:00']],
                'special' => ['Morning deal', 'Any pastry and a fresh juice for Br 190 before 10:00.', 'Croissant basket', 'juices-table'],
                'news' => ['Fresh today:', 'Apple galette is back.'],
                'catalogue' => [
                    'menus' => [
                        ['name' => 'Bakery', 'slug' => 'bakery', 'categories' => [
                            ['Bread & pastries', 'Baked every morning.', [
                                ['Croissant basket', 'Three butter croissants', 22000, ['vegetarian', 'popular'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 930, 'ph' => ['flaky-pastries', 'pastry-platter']]],
                                ['Daily pastries', 'Ask for today\'s selection', 9000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs', 'nuts'], 'ph' => ['bakery-display']]],
                                ['Apple galette', 'Rustic, cinnamon', 12000, ['vegetarian', 'new'], ['al' => ['gluten', 'milk'], 'k' => 380, 'ph' => ['apple-galette']]],
                                ['Glazed donuts', 'Five colourful donuts', 16000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 1100, 'ph' => ['donuts']]],
                                ['Sourdough loaf', 'To take home', 18000, ['vegan'], ['al' => ['gluten']]],
                                ['Teff bread', 'Gluten-free loaf', 20000, ['vegan', 'gluten_free'], []],
                            ]],
                            ['Cakes', null, [
                                ['Éclairs', 'Chocolate, coffee, pistachio', 11000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs', 'nuts'], 'k' => 290, 'ph' => ['eclairs']]],
                                ['Berry roulade', 'Meringue, cream, berries', 13000, ['vegetarian', 'gluten_free'], ['al' => ['milk', 'eggs'], 'k' => 340, 'ph' => ['roulade']]],
                                ['Berry cups', 'Cream, berries, crumble', 12000, ['vegetarian'], ['al' => ['milk', 'gluten'], 'k' => 310, 'ph' => ['berry-desserts']]],
                                ['Carrot cake', 'Cream cheese frosting', 12500, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs', 'nuts'], 'so' => true]],
                            ]],
                        ]],
                        ['name' => 'Juice bar', 'slug' => 'juice-bar', 'categories' => [
                            ['Fresh juices', 'Cold-pressed to order.', [
                                ['Juice of the day', 'Ask what\'s fresh', 10000, ['vegan'], ['ph' => ['juice-jars'], 'sz' => [['Small', 10000], ['Large', 14000]]]],
                                ['Berry mint cooler', 'Berries, lime, mint', 12000, ['vegan', 'popular'], ['k' => 120, 'ph' => ['berry-mint']]],
                                ['Mixed fruit trio', 'Three small juices to taste', 18000, ['vegan'], ['ph' => ['juices-table']]],
                                ['Green detox', 'Spinach, apple, ginger, lemon', 13000, ['vegan', 'new'], ['k' => 90]],
                            ]],
                            ['Coffee & matcha', null, [
                                ['Matcha latte', 'Ceremonial matcha, oat milk', 13000, ['vegan'], ['al' => ['gluten'], 'k' => 140, 'ph' => ['matcha']]],
                                ['Flat white', null, 10000, [], ['al' => ['milk']]],
                                ['Macchiato', null, 7500, [], ['al' => ['milk']]],
                            ]],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
