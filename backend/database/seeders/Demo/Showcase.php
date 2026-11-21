<?php

namespace Database\Seeders\Demo;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Membership;
use App\Models\OpeningHour;
use App\Models\Plan;
use App\Models\PricePeriod;
use App\Models\Promotion;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\TenantProfile;
use App\Models\User;
use App\Tenancy\CurrentTenant;

/**
 * More complete restaurants with real photos, one per menu style, to show
 * restaurants what each style looks like: A editorial (fine dining), B grid
 * (pizza & burgers), C bistro (bakery & juice bar), E mesob (Ethiopian and
 * Somali), F lounge (cocktail bar, happy hour), G board (street food), H arabic
 * (Yemeni mandi, menu in Arabic: right-to-left), I islamic (halal family
 * restaurant), J somali (Somali café), K mono (speciality coffee),
 * L colour (juice bar and ice cream), M coffee (coffee house), N grill (tibs
 * and grill), O garden (juice and fasting food), P coastal (seafood by the sea,
 * in US dollars) and Q luxe (fine dining).
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
    public function restaurant(array $def, array $plans): Tenant
    {
        $tenant = Tenant::create([
            'slug' => $def['slug'], 'name' => $def['name'], 'plan_id' => $plans[$def['plan']]->id ?? null,
            'paid_until' => now()->addMonths(5), 'timezone' => $def['timezone'] ?? 'Africa/Addis_Ababa', 'default_locale' => 'en', 'currency_code' => $def['currency'] ?? 'ETB',
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
                // Style options (H: direction, I: Halal and Ramadan mode, K: greyscale and accent).
                ...($def['options'] ?? []),
                // No banner (M–Q demos): the style's own header picture shows.
                'cover_media_id' => isset($def['banner']) ? $this->photos->media($tenant, $def['banner'], true)?->id : null,
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
            // Happy hour: [weekdays, from, to, [[dish, percent], …]] (style F's demo).
            if (isset($def['happy'])) {
                [$days, $from, $to, $deals] = $def['happy'];
                $period = PricePeriod::create(['name' => ['en' => 'Happy hour'], 'weekdays' => $days, 'starts_at' => $from, 'ends_at' => $to]);
                foreach ($deals as [$dish, $percent]) {
                    $period->items()->create(['item_id' => $items[$dish]->id, 'mode' => 'percent', 'value' => $percent]);
                }
            }
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
                'slug' => 'demo-bakery', 'name' => 'Crumb & Juice', 'email' => 'bakery@example.com', 'plan' => 'plus', 'age' => 5,
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
            [
                'slug' => 'demo-mesob', 'name' => 'Lalibela House', 'email' => 'mesob@example.com', 'plan' => 'plus', 'age' => 6,
                'style' => 'mesob', 'color' => '#a3361f', 'logo' => 'ring', 'banner' => 'injera-dishes', 'banner_text' => 'under',
                'about' => "Ethiopian and Somali home cooking, served on one big injera to share.\nCoffee ceremony every afternoon from 15:00.",
                'address' => 'Bole Medhanialem, Addis Ababa', 'phone' => '+251 11 000 0544', 'hours' => [['07:00', '22:30']],
                'special' => ['Beyaynetu for two', 'Six fasting stews on one injera — Br 520 every Wednesday and Friday.', 'Beyaynetu · በያይነቱ', 'injera-platter'],
                'news' => ['Every afternoon:', 'Coffee ceremony with fresh popcorn from 15:00.'],
                'catalogue' => [
                    'groups' => [
                        'spice' => ['How spicy?', 1, 1, [['Mild', 0], ['Medium', 0], ['Hot (with mitmita)', 0]]],
                        'side' => ['Add to your plate', 0, 3, [['Extra injera', 2500], ['Ayib (fresh cheese)', 3500], ['Gomen (greens)', 4000], ['Boiled egg', 2000]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Wot · ወጥ', 'Stews, served on injera.', [
                                ['Doro wot · ዶሮ ወጥ', 'Chicken, berbere, niter kibbeh, boiled egg', 42000, ['spicy', 'popular'], ['d' => 'Slow-cooked chicken in a deep red berbere sauce with spiced butter and a boiled egg, on injera.', 'al' => ['eggs', 'milk'], 'k' => 780, 'pm' => 25, 'ph' => ['injera-dishes'], 'g' => ['spice', 'side']]],
                                ['Beyaynetu · በያይነቱ', 'Six fasting stews on one injera', 28000, ['vegan', 'popular'], ['k' => 640, 'pm' => 15, 'ph' => ['injera-platter'], 'g' => ['side'], 'sz' => [['For one', 28000], ['For two', 52000]]]],
                                ['Shiro tegabino', 'Chickpea stew, bubbling in a clay pot', 22000, ['vegan'], ['k' => 520, 'pm' => 15, 'g' => ['spice', 'side']]],
                                ['Misir wot', 'Red lentils, berbere, garlic', 20000, ['vegan', 'spicy'], ['k' => 480, 'pm' => 15, 'g' => ['spice']]],
                                ['Kitfo', 'Minced beef, mitmita, ayib, gomen', 46000, [], ['al' => ['milk'], 'k' => 820, 'pm' => 15, 'g' => ['spice'], 'so' => true]],
                            ]],
                            ['Tibs · ጥብስ', null, [
                                ['Shekla tibs', 'Sizzling beef on a clay dish, rosemary, onion', 48000, ['popular'], ['k' => 860, 'pm' => 20, 'ph' => ['tibs'], 'g' => ['spice', 'side']]],
                                ['Suqaar', 'Somali beef cubes, peppers, cumin, with canjeero', 40000, ['new'], ['al' => ['gluten'], 'k' => 740, 'pm' => 20]],
                                ['Bariis iskukaris', 'Somali spiced rice, goat, banana on the side', 44000, [], ['k' => 900, 'pm' => 25]],
                            ]],
                            ['Coffee & tea', 'Our coffee is roasted every morning.', [
                                ['Coffee ceremony · ቡና', 'Three rounds from the jebena, with popcorn', 18000, ['vegan', 'popular'], ['ph' => ['ceremony-2', 'jebena'], 'sz' => [['For one', 18000], ['For the table', 45000]]]],
                                ['Spiced tea', 'Black tea, cinnamon, clove, cardamom', 6000, ['vegan'], ['ph' => ['spiced-tea']]],
                                ['Macchiato', null, 7000, ['vegetarian'], ['al' => ['milk']]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-lounge', 'name' => 'Amber Room', 'email' => 'lounge@example.com', 'plan' => 'pro', 'age' => 4,
                'style' => 'lounge', 'color' => '#c58bff', 'logo' => 'drop', 'banner' => 'cafe-tables', 'banner_text' => 'over',
                'about' => "Cocktails, mocktails and small plates under low light.\nLive DJ on Fridays and Saturdays from 21:00.",
                'address' => 'Kazanchis, Addis Ababa', 'phone' => '+251 11 000 0655', 'hours' => [['17:00', '02:00']],
                'special' => ['Sunset set', 'Two signature drinks and a sharing plate for Br 1,150 before 20:00.', 'Sunset spritz', 'berry-mint'],
                'news' => ['Tonight:', 'DJ from 21:00 · no entry fee.'],
                // Happy hour every day, 18:00–20:00 (owner, 2026-11-18: before / during / after must work).
                'happy' => [[0, 1, 2, 3, 4, 5, 6], '18:00', '20:00', [['Sunset spritz', 20], ['Blue Nile', 20], ['Mint & lime cooler', 20]]],
                'catalogue' => [
                    'groups' => [
                        'ice' => ['Ice', 1, 1, [['Normal ice', 0], ['Little ice', 0], ['No ice', 0]]],
                        'dip' => ['Dip', 0, 2, [['Garlic mayo', 1500], ['Mitmita mayo', 1500], ['Sweet chili', 1500]]],
                    ],
                    'menus' => [
                        ['name' => 'Drinks & bites', 'slug' => 'menu', 'categories' => [
                            ['Signature drinks', 'Made at the bar, alcohol-free on request.', [
                                ['Sunset spritz', 'Orange, berries, soda, fresh mint', 45000, ['popular'], ['k' => 160, 'ph' => ['berry-mint'], 'g' => ['ice']]],
                                ['Blue Nile', 'Butterfly pea, lime, tonic', 48000, ['new'], ['k' => 140, 'ph' => ['iced-lemon-tea'], 'g' => ['ice']]],
                                ['Mint & lime cooler', 'Lime, mint, cane sugar, sparkling water', 32000, ['vegan'], ['k' => 110, 'ph' => ['iced-mint-tea'], 'g' => ['ice']]],
                                ['Espresso tonic', 'Cold espresso over tonic and orange', 34000, ['vegan'], ['k' => 60, 'ph' => ['cold-brew'], 'g' => ['ice']]],
                                ['Kiwi smash', 'Kiwi, lime, ginger, soda', 36000, ['vegan'], ['k' => 130, 'ph' => ['kiwi-juice'], 'g' => ['ice'], 'so' => true]],
                            ]],
                            ['Sharing plates', null, [
                                ['Spiced wings', 'Eight wings, mitmita glaze', 39000, ['spicy', 'popular'], ['al' => ['gluten'], 'k' => 780, 'pm' => 15, 'ph' => ['wings'], 'g' => ['dip'], 'sz' => [['8 pieces', 39000], ['16 pieces', 69000]]]],
                                ['Loaded fries', 'Cheese, jalapeños, spring onion', 26000, ['vegetarian'], ['al' => ['milk'], 'k' => 690, 'pm' => 10, 'ph' => ['fries-bowl'], 'g' => ['dip']]],
                                ['Slider trio', 'Three mini burgers, cheddar, pickles', 52000, [], ['al' => ['gluten', 'milk'], 'k' => 940, 'pm' => 15, 'ph' => ['burger-half']]],
                                ['Hummus & bread', 'Olive oil, paprika, warm flatbread', 22000, ['vegan'], ['al' => ['gluten', 'sesame'], 'k' => 520]],
                            ]],
                            ['Soft drinks', null, [
                                ['Sparkling water', null, 9000, ['vegan'], []],
                                ['Cola', null, 9000, ['vegan'], ['sz' => [['Glass', 9000], ['Bottle', 14000]]]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-street', 'name' => 'Piassa Street Kitchen', 'email' => 'street@example.com', 'plan' => 'plus', 'age' => 3,
                'style' => 'board', 'color' => '#ffd23f', 'logo' => 'slice', 'banner' => 'pizzas-plates', 'banner_text' => 'under',
                'about' => "Burgers, sambusa and fresh juice, made fast.\nOrder at the counter — we call your number.",
                'address' => 'Piassa, Addis Ababa', 'phone' => '+251 11 000 0766', 'hours' => [['10:00', '23:00']],
                'special' => ['Combo of the day', 'Any burger, fries and a juice for Br 450.', 'Classic smash', 'burger-tray'],
                'news' => ['New:', 'Avocado spris is back.'],
                'catalogue' => [
                    'groups' => [
                        'extras' => ['Extras', 0, 3, [['Cheddar', 3000], ['Bacon', 5000], ['Fried egg', 2500], ['Jalapeños', 1500]]],
                        'sauce' => ['Sauce', 0, 2, [['Ketchup', 0], ['Mitmita mayo', 1000], ['BBQ', 1000]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Burgers', 'Smashed to order, potato bun.', [
                                ['Classic smash', 'Beef, cheddar, pickles, house sauce', 32000, ['popular'], ['al' => ['gluten', 'milk'], 'k' => 820, 'pm' => 8, 'ph' => ['burger-tray'], 'g' => ['extras', 'sauce']]],
                                ['Berbere chicken', 'Crispy chicken, spicy mayo, slaw', 29000, ['spicy'], ['al' => ['gluten', 'eggs'], 'k' => 760, 'pm' => 8, 'ph' => ['burger-bacon'], 'g' => ['extras', 'sauce']]],
                                ['Double trouble', 'Two patties, two cheeses, bacon', 41000, [], ['al' => ['gluten', 'milk'], 'k' => 1150, 'pm' => 10, 'ph' => ['cheeseburger'], 'g' => ['extras', 'sauce'], 'so' => true]],
                                ['Veggie stack', 'Lentil patty, avocado, tomato', 27000, ['vegetarian', 'new'], ['al' => ['gluten'], 'k' => 640, 'pm' => 8]],
                            ]],
                            ['Sides & snacks', null, [
                                ['Fries', 'Salted, with ketchup', 12000, ['vegan'], ['k' => 420, 'ph' => ['fries-ketchup'], 'g' => ['sauce'], 'sz' => [['Regular', 12000], ['Large', 16000]]]],
                                ['Sambusa', 'Three crispy lentil sambusa', 9000, ['vegan', 'popular'], ['al' => ['gluten'], 'k' => 360]],
                                ['Grilled cheese', 'Toasted, with tomato soup', 18000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 560, 'ph' => ['grilled-cheese']]],
                                ['Wings', 'Six wings, hot sauce', 24000, ['spicy'], ['k' => 610, 'ph' => ['chicken-fries'], 'g' => ['sauce']]],
                            ]],
                            ['Juice', 'Pressed fresh, no sugar added.', [
                                ['Avocado spris', 'Avocado, mango, papaya', 12000, ['vegan', 'popular'], ['k' => 280, 'ph' => ['avocado-smoothie']]],
                                ['Mango juice', null, 10000, ['vegan'], ['k' => 190, 'ph' => ['mango-juice']]],
                                ['Orange juice', null, 9500, ['vegan'], ['k' => 150]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-mandi', 'name' => 'دار المندي', 'email' => 'mandi@example.com', 'plan' => 'plus', 'age' => 3,
                'style' => 'arabic', 'color' => '#0f5c4a', 'logo' => 'ring', 'banner' => 'shawarma-wraps', 'banner_text' => 'under',
                // The dish and category names are in Arabic: "automatic" shows the menu right-to-left.
                'options' => ['menu_direction' => 'auto'],
                'about' => "Yemeni mandi and grills, slow-cooked in our tandoor oven.\nFamily seating upstairs.",
                'address' => 'Bole Atlas, Addis Ababa', 'phone' => '+251 11 000 0877', 'hours' => [['11:00', '23:30']],
                'special' => ['Mandi feast for four', 'Lamb mandi, chicken mandi, salads and Arabic coffee — Br 2,600.', 'لحم مندي', 'kabsa-platter'],
                'news' => ['After 20:00:', 'Arabic coffee and dates on the house.'],
                'catalogue' => [
                    'groups' => [
                        'rice' => ['Rice', 1, 1, [['Mandi rice (smoked)', 0], ['Kabsa rice (tomato)', 0]]],
                        'sauce' => ['Sauce', 0, 2, [['Daqqus (tomato and chili)', 0], ['Toum (garlic)', 1500], ['Yogurt', 1000]]],
                    ],
                    'menus' => [
                        ['name' => 'المنيو', 'slug' => 'menu', 'categories' => [
                            ['المندي · Mandi', 'Slow-cooked in the tandoor, on smoked rice.', [
                                ['مندي دجاج', 'Chicken mandi: half a chicken on smoked rice, with daqqus', 52000, ['halal', 'popular'], ['k' => 950, 'pm' => 20, 'ph' => ['mandi-chicken'], 'g' => ['rice', 'sauce'], 'sz' => [['Half chicken', 52000], ['Whole chicken', 95000]]]],
                                ['لحم مندي', 'Lamb mandi: lamb shoulder on smoked rice', 78000, ['halal', 'popular'], ['k' => 1200, 'pm' => 25, 'ph' => ['kabsa-platter'], 'g' => ['rice', 'sauce']]],
                                ['حنيذ', 'Haneeth: slow-roasted lamb, the Yemeni way', 82000, ['halal'], ['k' => 1150, 'pm' => 30, 'g' => ['rice'], 'so' => true]],
                                ['كبسة دجاج', 'Chicken kabsa: spiced tomato rice, raisins and almonds', 48000, ['halal', 'new'], ['al' => ['nuts'], 'k' => 980, 'pm' => 20, 'g' => ['sauce']]],
                            ]],
                            ['شاورما · Shawarma', null, [
                                ['صحن شاورما دجاج', 'Chicken shawarma plate with fries and toum', 34000, ['halal', 'popular'], ['al' => ['gluten'], 'k' => 880, 'pm' => 10, 'ph' => ['shawarma-fries'], 'g' => ['sauce']]],
                                ['شاورما لحم', 'Beef shawarma wrap, pickles, tahini', 26000, ['halal'], ['al' => ['gluten', 'sesame'], 'k' => 640, 'pm' => 8, 'ph' => ['shawarma-wraps']]],
                                ['فلافل', 'Falafel: six pieces with tahini and salad', 16000, ['vegan'], ['al' => ['sesame'], 'k' => 420, 'pm' => 8]],
                            ]],
                            ['مقبلات · Mezze', 'To share, with warm bread.', [
                                ['حمص', 'Hummus with olive oil and warm bread', 14000, ['vegan'], ['al' => ['sesame', 'gluten'], 'k' => 380, 'ph' => ['hummus-pita']]],
                                ['حمص باللحمة', 'Hummus topped with spiced minced lamb', 22000, ['halal'], ['al' => ['sesame'], 'k' => 560, 'ph' => ['hummus-board']]],
                                ['فتوش', 'Fattoush: crisp bread, sumac and garden salad', 15000, ['vegan'], ['al' => ['gluten'], 'k' => 260]],
                            ]],
                            ['قهوة وحلويات · Coffee & sweets', null, [
                                ['قهوة عربية', 'Arabic coffee with cardamom, a pot for the table', 12000, ['vegan', 'popular'], ['ph' => ['arabic-coffee'], 'sz' => [['Small pot', 12000], ['Large pot', 20000]]]],
                                ['كنافة', 'Kunafa: warm cheese pastry, syrup and pistachio', 18000, ['vegetarian'], ['al' => ['milk', 'gluten', 'nuts'], 'k' => 520]],
                                ['شاي عدني', 'Adeni tea: milk tea with cardamom', 6000, ['vegetarian'], ['al' => ['milk']]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-halal', 'name' => 'Al-Noor Family Kitchen', 'email' => 'halal@example.com', 'plan' => 'plus', 'age' => 3,
                'style' => 'islamic', 'color' => '#0d6b5f', 'logo' => 'drop', 'banner' => 'biryani-dark', 'banner_text' => 'under',
                'options' => ['show_halal' => true, 'ramadan_mode' => false],
                'about' => "Halal grills, biryani and family platters.\nFamily room and a kids' corner upstairs.",
                'address' => 'Mexico Square, Addis Ababa', 'phone' => '+251 11 000 0988', 'hours' => [['10:00', '23:00']],
                'special' => ['Iftar set for two', 'Dates, lentil soup, chicken biryani and two drinks — Br 1,450, at sunset.', 'Chicken biryani', 'biryani-plates'],
                'news' => ['Family room:', 'Separate family seating and a kids\' corner upstairs.'],
                'catalogue' => [
                    'groups' => [
                        'side' => ['Choose your side', 1, 1, [['Saffron rice', 0], ['Bread', 0], ['Fries', 0]]],
                        'heat' => ['How spicy?', 1, 1, [['Mild', 0], ['Medium', 0], ['Hot', 0]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Grills', 'From the charcoal grill, with rice or bread.', [
                                ['Mixed grill', 'Lamb chops, chicken tikka, kofta and rice', 145000, ['halal', 'popular'], ['k' => 1600, 'pm' => 25, 'ph' => ['steak-veg'], 'g' => ['side'], 'sz' => [['For two', 145000], ['For four', 270000]]]],
                                ['Chicken tikka', 'Yogurt-marinated chicken, mint chutney', 52000, ['halal', 'spicy'], ['al' => ['milk'], 'k' => 640, 'pm' => 20, 'ph' => ['chicken-plate'], 'g' => ['side', 'heat']]],
                                ['Lamb kofta', 'Minced lamb skewers, onion and sumac', 56000, ['halal'], ['k' => 720, 'pm' => 20, 'g' => ['side', 'heat']]],
                                ['Grilled wings', 'Eight wings, lemon and garlic', 38000, ['halal'], ['k' => 690, 'pm' => 15, 'ph' => ['wings'], 'so' => true]],
                            ]],
                            ['Rice', null, [
                                ['Chicken biryani', 'Basmati, saffron, fried onions, raita', 48000, ['halal', 'popular'], ['al' => ['milk'], 'k' => 900, 'pm' => 15, 'ph' => ['biryani-dark'], 'g' => ['heat']]],
                                ['Lamb biryani', 'Slow-cooked lamb, spiced rice', 62000, ['halal'], ['al' => ['milk'], 'k' => 1050, 'pm' => 15, 'ph' => ['biryani-plates']]],
                                ['Vegetable pulao', 'Peas, carrots and cashews', 30000, ['vegetarian'], ['al' => ['nuts'], 'k' => 610]],
                            ]],
                            ['Starters & soup', null, [
                                ['Lentil soup', 'Red lentils, cumin and lemon', 12000, ['vegan'], ['k' => 240]],
                                ['Hummus', 'Pomegranate, mint and olives', 16000, ['vegan'], ['al' => ['sesame'], 'k' => 380, 'ph' => ['hummus-mint']]],
                                ['Samosa', 'Three crispy beef samosas', 14000, ['halal'], ['al' => ['gluten'], 'k' => 420, 'ph' => ['sambuusa']]],
                            ]],
                            ['Drinks', null, [
                                ['Fresh mango juice', null, 12000, ['vegan'], ['k' => 190, 'ph' => ['mango-juice']]],
                                ['Mint lemonade', null, 10000, ['vegan'], ['k' => 120, 'ph' => ['iced-mint-tea']]],
                                ['Karak tea', 'Spiced milk tea', 7000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['spiced-tea']]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-xamar', 'name' => 'Xamar Café', 'email' => 'xamar@example.com', 'plan' => 'plus', 'age' => 3,
                'style' => 'somali', 'color' => '#c2410c', 'logo' => 'bean', 'banner' => 'sambuusa', 'banner_text' => 'under',
                'about' => "Somali breakfast, bariis with hilib ari, and sweet shaah all day.\nA little Eastleigh in Bole.",
                'address' => 'Bole Rwanda, Addis Ababa', 'phone' => '+251 11 000 1099', 'hours' => [['06:00', '23:00']],
                'special' => ['Bariis for two', 'Bariis iskukaris, hilib ari, salad and banana — Br 1,100.', 'Bariis iskukaris', 'kabsa-platter'],
                'news' => ['Every morning:', 'Fresh canjeero and muufo from 06:00.'],
                'catalogue' => [
                    'groups' => [
                        'meat' => ['Meat', 1, 1, [['Goat (hilib ari)', 0], ['Chicken (digaag)', 0], ['Camel (hilib geel)', 6000]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Quraac · Breakfast', 'Until 11:00.', [
                                ['Canjeero iyo suqaar', 'Somali pancakes with spiced beef cubes', 22000, ['halal', 'popular'], ['k' => 680, 'pm' => 10, 'ph' => ['injera-platter']]],
                                ['Canjeero macaan', 'Two canjeero with sesame oil and sugar', 12000, ['vegan'], ['al' => ['sesame'], 'k' => 420]],
                                ['Beer iyo canjeero', 'Liver with onions and peppers, canjeero', 24000, ['halal'], ['k' => 610, 'pm' => 10, 'so' => true]],
                                ['Muufo', 'Flatbread from the clay oven', 4000, ['vegan'], ['al' => ['gluten'], 'k' => 210]],
                            ]],
                            ['Qado · Lunch', 'With banana and basbaas (green chili sauce).', [
                                ['Bariis iskukaris', 'Spiced rice with your choice of meat', 44000, ['halal', 'popular'], ['k' => 980, 'pm' => 15, 'ph' => ['biryani-plates'], 'g' => ['meat']]],
                                ['Hilib ari', 'Goat on the bone, slow-roasted', 52000, ['halal'], ['k' => 860, 'pm' => 20, 'ph' => ['tibs']]],
                                ['Baasto iyo suugo', 'Pasta with Somali beef sauce', 30000, ['halal'], ['al' => ['gluten'], 'k' => 790, 'pm' => 12, 'ph' => ['bolognese']]],
                                ['Kalluun', 'Fried kingfish with lemon and rice', 46000, ['new'], ['al' => ['fish'], 'k' => 720, 'pm' => 15]],
                            ]],
                            ['Cunto fudud · Snacks', null, [
                                ['Sambuusa', 'Crispy beef or lentil sambuusa', 9000, ['popular'], ['al' => ['gluten'], 'k' => 360, 'ph' => ['sambuusa'], 'sz' => [['3 pieces', 9000], ['6 pieces', 16000]]]],
                                ['Bajiye', 'Black-eyed pea fritters', 8000, ['vegan'], ['k' => 320]],
                            ]],
                            ['Cabitaan · Drinks', null, [
                                ['Shaah', 'Somali spiced tea with milk', 4000, ['vegetarian', 'popular'], ['al' => ['milk'], 'ph' => ['spiced-tea']]],
                                ['Mango & papaya juice', null, 10000, ['vegan'], ['k' => 180, 'ph' => ['mango-juice']]],
                                ['Cambe iyo caano', 'Mango and milk shake', 12000, ['vegetarian'], ['al' => ['milk'], 'k' => 290]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-atelier', 'name' => 'Atelier Coffee', 'email' => 'atelier@example.com', 'plan' => 'plus', 'age' => 2,
                'style' => 'mono', 'color' => '#9a3412', 'logo' => 'bean', 'banner' => 'cafe-bali', 'banner_text' => 'under',
                'options' => ['photos_greyscale' => true, 'mono_accent' => false],
                'about' => "Speciality coffee from Ethiopian farms, pastry baked in-house.\nQuiet tables and fast Wi-Fi.",
                'address' => 'Kazanchis, Addis Ababa', 'phone' => '+251 11 000 1100', 'hours' => [['07:00', '19:00']],
                'special' => ['Pour-over flight', 'Three single-origin coffees side by side — Br 360.', 'Pour-over', 'latte-biscotti'],
                'news' => ['This week:', 'Guji natural, roasted on Monday.'],
                'catalogue' => [
                    'groups' => [
                        'milk' => ['Milk', 1, 1, [['Whole milk', 0], ['Oat milk', 2000]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Coffee', 'Single origin, roasted every week.', [
                                ['Espresso', 'Yirgacheffe, washed', 9000, ['vegan'], ['ph' => ['espresso']]],
                                ['Macchiato', null, 10000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['macchiato'], 'g' => ['milk']]],
                                ['Flat white', 'Double ristretto, steamed milk', 14000, ['vegetarian', 'popular'], ['al' => ['milk'], 'ph' => ['latte'], 'g' => ['milk']]],
                                ['Cappuccino', null, 13000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['cappuccino'], 'g' => ['milk']]],
                                ['Pour-over', 'Guji or Sidamo, ask the barista', 16000, ['vegan', 'new'], ['ph' => ['latte-biscotti']]],
                                ['Cold brew', 'Eighteen hours, served over ice', 15000, ['vegan'], ['ph' => ['cold-brew']]],
                            ]],
                            ['Pastry', 'Baked here every morning.', [
                                ['Butter croissant', null, 9000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 280, 'ph' => ['croissants']]],
                                ['Pain au chocolat', null, 11000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 320, 'ph' => ['pain-choc']]],
                                ['Cardamom bun', null, 10000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 300, 'so' => true]],
                            ]],
                            ['Brunch', 'Until 14:00.', [
                                ['Avocado toast', 'Sourdough, lime, chili, egg', 24000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'k' => 480, 'pm' => 10, 'ph' => ['avocado-toast']]],
                                ['Poached eggs', 'Hollandaise and greens', 26000, ['vegetarian'], ['al' => ['eggs', 'milk'], 'k' => 520, 'pm' => 12, 'ph' => ['poached-eggs']]],
                                ['Granola', 'Yogurt, honey and berries', 18000, ['vegetarian'], ['al' => ['milk', 'nuts'], 'k' => 410, 'ph' => ['granola']]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-juicy', 'name' => 'Juicy Jungle', 'email' => 'juicy@example.com', 'plan' => 'plus', 'age' => 2,
                'style' => 'colour', 'color' => '#e8437f', 'logo' => 'drop', 'banner' => 'juices-table', 'banner_text' => 'under',
                'about' => "Fresh juice, smoothies, ice cream and snacks.\nKids' corner and free water refills.",
                'address' => 'CMC, Addis Ababa', 'phone' => '+251 11 000 1211', 'hours' => [['08:00', '22:00']],
                'special' => ['Rainbow cup', 'Five scoops in five colours, with sprinkles — Br 260.', 'Rainbow cup', 'ice-cream'],
                'news' => ['Weekends:', 'Kids under 6 get a free mini juice.'],
                'catalogue' => [
                    'groups' => [
                        'flavour' => ['Flavour', 1, 2, [['Vanilla', 0], ['Chocolate', 0], ['Mango', 0], ['Strawberry', 0]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Juices', 'Pressed to order, no sugar added.', [
                                ['Mango sunrise', 'Mango, orange and a little lime', 16000, ['vegan', 'popular'], ['k' => 190, 'ph' => ['mango-juice'], 'sz' => [['Small', 16000], ['Large', 22000]]]],
                                ['Kiwi kick', 'Kiwi, apple and mint', 15000, ['vegan'], ['k' => 150, 'ph' => ['kiwi-juice']]],
                                ['Orange', null, 12000, ['vegan'], ['k' => 140, 'ph' => ['orange-juice']]],
                                ['Avocado spris', 'Avocado, mango and papaya in layers', 18000, ['vegan', 'popular'], ['k' => 280, 'ph' => ['avocado-smoothie']]],
                            ]],
                            ['Smoothies', null, [
                                ['Berry blast', 'Strawberry, banana and yogurt', 19000, ['vegetarian'], ['al' => ['milk'], 'k' => 260, 'ph' => ['strawberry-smoothie']]],
                                ['Green glow', 'Spinach, apple and ginger', 18000, ['vegan', 'new'], ['k' => 170, 'ph' => ['smoothies']]],
                            ]],
                            ['Ice cream', 'Made here, every day.', [
                                ['Rainbow cup', 'Five scoops, five colours, sprinkles', 26000, ['vegetarian', 'popular'], ['al' => ['milk'], 'k' => 640, 'ph' => ['ice-cream']]],
                                ['Single scoop', 'Vanilla, chocolate, mango or strawberry', 7000, ['vegetarian'], ['al' => ['milk'], 'k' => 140, 'g' => ['flavour']]],
                            ]],
                            ['Waffles & sweets', null, [
                                ['Waffle', 'Honey, cream and fruit', 20000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 520, 'pm' => 8, 'ph' => ['waffle']]],
                                ['Donuts', 'Three glazed donuts', 15000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 690, 'ph' => ['donuts'], 'so' => true]],
                                ['Cupcake', null, 9000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 340, 'ph' => ['cupcake']]],
                            ]],
                            ['Snacks', null, [
                                ['Fruit salad', 'Seasonal fruit, lime and mint', 14000, ['vegan'], ['k' => 160, 'ph' => ['fruit-salad']]],
                                ['Fries', 'Salted, with ketchup', 11000, ['vegan'], ['k' => 410, 'ph' => ['fries-ketchup']]],
                            ]],
                            ["Kids' menu", 'For children under 10.', [
                                ['Mini juice', 'Any juice in a small cup', 8000, ['vegan'], ['k' => 90]],
                                ['Mini pancakes', 'Five little pancakes with honey', 12000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 360, 'pm' => 8, 'ph' => ['pancakes']]],
                            ]],
                        ]],
                    ],
                ],
            ],
            // M–Q (owner, 2026-11-19, QUESTIONS #232): no banner, so each shows its style's own header
            // picture; some categories carry their own photo, the others get the style's icon.
            [
                'slug' => 'demo-jebena', 'name' => 'Jebena Corner', 'email' => 'jebena@example.com', 'plan' => 'plus', 'age' => 1,
                'style' => 'coffee', 'color' => '#8a3b12', 'logo' => 'bean', 'banner' => null, 'banner_text' => 'under',
                'about' => "Coffee roasted in front of you, the way it is at home: three rounds, popcorn and incense.\nFresh bread, breakfast and spiced tea all day.",
                'address' => 'Piassa, Addis Ababa', 'phone' => '+251 11 000 1301', 'hours' => [['06:30', '21:00']],
                'special' => ['Sunday coffee ceremony', 'A full jebena for the table, roasted in front of you — Br 180, Sundays from 15:00.', 'Coffee ceremony', 'ceremony'],
                'news' => ['New:', 'Ginger and cinnamon tea, brewed fresh every hour.'],
                'catalogue' => [
                    'groups' => [
                        'milk' => ['Milk', 0, 1, [['A little milk', 0], ['Extra milk', 1000], ['Oat milk', 2500]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Coffee ceremony', 'Served in a jebena with three rounds.', [
                                ['Coffee ceremony', 'For 2–4, with popcorn and incense', 18000, ['popular'], ['d' => 'Green beans roasted at your table, ground by hand and brewed in a clay jebena. Three rounds: abol, tona and baraka.', 'pm' => 25, 'ph' => ['ceremony', 'jebena']]],
                                ['Jebena for one', 'Two small cups', 6000, [], ['ph' => ['ceremony-2']]],
                            ], 'jebena'],
                            ['Coffee', 'Ethiopian beans, roasted this week.', [
                                ['Macchiato', 'Strong, a little milk foam', 5500, ['vegetarian', 'popular'], ['al' => ['milk'], 'ph' => ['macchiato'], 'g' => ['milk']]],
                                ['Espresso', null, 4500, ['vegan'], ['ph' => ['espresso']]],
                                ['Cappuccino', null, 7000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['cappuccino'], 'g' => ['milk']]],
                                ['Buna with tena adam', 'Black coffee with a sprig of rue', 4000, ['vegan'], []],
                            ]],
                            ['Tea', null, [
                                ['Spiced tea', 'Cinnamon, clove and cardamom', 4000, ['vegan'], ['ph' => ['spiced-tea']]],
                                ['Ginger tea', 'Fresh ginger and honey', 4500, ['vegetarian', 'new'], ['ph' => ['ginger-tea']]],
                                ['Spris', 'Half tea, half coffee', 4500, ['vegan'], []],
                            ]],
                            ['Breakfast', 'Until 11:00.', [
                                ['Chechebsa', 'Torn flatbread, niter kibbeh, berbere and honey', 16000, ['vegetarian', 'spicy'], ['al' => ['gluten', 'milk'], 'k' => 520, 'pm' => 10]],
                                ['Ful', 'Fava beans, egg, tomato, warm bread', 15000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'k' => 480, 'pm' => 8]],
                                ['Eggs on toast', 'Two poached eggs, sourdough', 17000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'ph' => ['poached-eggs']]],
                            ]],
                            ['Pastries & sweets', null, [
                                ['Butter croissant', null, 7000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'ph' => ['croissants']]],
                                ['Sambusa', 'Lentils and green chilli, three pieces', 6000, ['vegan', 'spicy'], ['al' => ['gluten'], 'ph' => ['sambuusa']]],
                                ['Chocolate cake', 'One slice', 11000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'ph' => ['chocolate-cake'], 'so' => true]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-firewood', 'name' => 'Firewood Grill', 'email' => 'firewood@example.com', 'plan' => 'business', 'age' => 1,
                'style' => 'grill', 'color' => '#e8622a', 'logo' => 'ring', 'banner' => null, 'banner_text' => 'under',
                'about' => "Charcoal grill, awaze and cold drinks. Tibs and kitfo the way grandmother made them.\nGroups and football nights welcome.",
                'address' => 'Kazanchis, Addis Ababa', 'phone' => '+251 11 000 1402', 'hours' => [['11:00', '23:30']],
                'special' => ['Grill platter for four', 'Tibs, steak, wings and skewers on one board — Br 2,400.', 'Special tibs', 'steak-fries'],
                'news' => ['Football nights:', 'Big screen on match days, book a table.'],
                'happy' => [[0, 1, 2, 3, 4], '16:00', '18:00', [['Spicy wings', 20]]],
                'catalogue' => [
                    'groups' => [
                        'heat' => ['How spicy?', 1, 1, [['Mild', 0], ['Medium', 0], ['Hot (awaze)', 0]]],
                        'doneness' => ['Cooked', 1, 1, [['Medium rare', 0], ['Medium', 0], ['Well done', 0]]],
                        'side' => ['Side', 0, 2, [['Injera', 0], ['Fries', 4000], ['Rice', 3500], ['Salad', 3000]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Tibs', 'Sizzling in a clay pot.', [
                                ['Special tibs', 'Beef, rosemary, onion, green chilli', 42000, ['popular', 'spicy'], ['d' => 'Beef cubes seared over high heat with rosemary, onion and green chilli, served sizzling in a clay pot.', 'k' => 690, 'pm' => 15, 'ph' => ['tibs'], 'g' => ['heat', 'side']]],
                                ['Lamb tibs', 'Lamb, garlic, awaze', 46000, ['spicy'], ['k' => 720, 'pm' => 15, 'g' => ['heat', 'side']]],
                                ['Shekla tibs', 'On a charcoal burner at the table', 52000, ['new'], ['pm' => 20, 'g' => ['side']]],
                            ], 'tibs'],
                            ['Kitfo', 'Minced beef with mitmita and niter kibbeh.', [
                                ['Kitfo', 'Raw, leb leb (warm) or cooked', 45000, ['spicy'], ['al' => ['milk'], 'pm' => 12]],
                                ['Special kitfo', 'With ayib and gomen', 52000, ['popular'], ['al' => ['milk'], 'pm' => 12]],
                            ]],
                            ['From the grill', 'Charcoal, every day from 11:00.', [
                                ['Charcoal steak', '300 g, grilled vegetables', 65000, [], ['k' => 820, 'pm' => 20, 'ph' => ['steak-veg'], 'g' => ['doneness', 'side']]],
                                ['Pepper steak', 'Black pepper sauce, fries', 68000, [], ['k' => 880, 'pm' => 20, 'ph' => ['steak-sauce'], 'g' => ['doneness']]],
                                ['Spicy wings', 'Berbere glaze, twelve pieces', 29000, ['spicy', 'popular'], ['k' => 760, 'pm' => 15, 'ph' => ['wings']]],
                                ['Half chicken', 'Lemon, garlic, chilli', 38000, [], ['k' => 680, 'pm' => 25, 'ph' => ['chicken-plate'], 'g' => ['side']]],
                            ]],
                            ['Burgers', null, [
                                ['Firewood burger', 'Double beef, smoked cheese, onion', 36000, ['popular'], ['al' => ['gluten', 'milk'], 'k' => 980, 'ph' => ['burger-board']]],
                                ['Bacon burger', null, 38000, [], ['al' => ['gluten', 'milk'], 'k' => 1040, 'ph' => ['burger-bacon'], 'so' => true]],
                            ]],
                            ['Sides & drinks', null, [
                                ['Fries', null, 9000, ['vegan'], ['ph' => ['fries-bowl']]],
                                ['Cold soda', 'Cola, orange or lemon', 5000, ['vegan'], ['ph' => ['soda-glass']]],
                                ['Sparkling water', null, 4000, ['vegan'], []],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-green-spoon', 'name' => 'Green Spoon', 'email' => 'greenspoon@example.com', 'plan' => 'plus', 'age' => 1,
                'style' => 'garden', 'color' => '#1d6b43', 'logo' => 'drop', 'banner' => null, 'banner_text' => 'under',
                'about' => "Fresh juice, salads and bowls, and a full fasting menu on Wednesdays and Fridays.\nNo added sugar in our juices.",
                'address' => 'Bole Medhanialem, Addis Ababa', 'phone' => '+251 11 000 1503', 'hours' => [['07:00', '21:00']],
                'special' => ['Fasting platter', 'Beyaynetu for two, eight dishes on injera — Br 380.', 'Beyaynetu', 'injera-platter'],
                'news' => ['Wednesdays and Fridays:', 'The whole menu can be made fasting (vegan).'],
                'catalogue' => [
                    'groups' => [
                        'size' => ['Size', 1, 1, [['Regular', 0], ['Large', 3000]]],
                        'extras' => ['Add', 0, 3, [['Avocado', 3000], ['Chickpeas', 2000], ['Seeds', 1500]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Fresh juices', 'Pressed to order.', [
                                ['Avocado spris', 'Avocado, mango and papaya in layers', 9500, ['vegan', 'popular'], ['k' => 280, 'ph' => ['avocado-smoothie'], 'g' => ['size']]],
                                ['Mango juice', null, 8000, ['vegan'], ['k' => 190, 'ph' => ['mango-juice'], 'g' => ['size']]],
                                ['Kiwi & apple', 'With a little mint', 8500, ['vegan'], ['k' => 150, 'ph' => ['kiwi-juice']]],
                                ['Orange', null, 7000, ['vegan'], ['k' => 140, 'ph' => ['orange-juice']]],
                            ], 'juices-table'],
                            ['Salads', null, [
                                ['Garden salad', 'Lettuce, tomato, cucumber, lemon', 14000, ['vegan', 'gluten_free'], ['k' => 180, 'ph' => ['garden-salad'], 'g' => ['extras']]],
                                ['Beet & citrus salad', 'Beetroot, orange, seeds', 16000, ['vegan', 'gluten_free', 'new'], ['k' => 220, 'ph' => ['beet-salad'], 'g' => ['extras']]],
                                ['Timatim fitfit', 'Tomato, onion, chilli and injera', 12000, ['vegan', 'spicy'], ['k' => 260]],
                            ]],
                            ['Bowls', 'Warm or cold.', [
                                ['Chicken bowl', 'Grilled chicken, rice, greens', 22000, [], ['k' => 610, 'ph' => ['chicken-bowl']]],
                                ['Yogurt bowl', 'Fruit, honey, granola', 15000, ['vegetarian'], ['al' => ['milk', 'nuts'], 'k' => 420, 'ph' => ['yogurt-bowls']]],
                                ['Fruit salad', 'Papaya, mango, banana, passion fruit', 11000, ['vegan', 'gluten_free'], ['k' => 210, 'ph' => ['fruit-salad']]],
                            ]],
                            ['Fasting dishes', 'Vegan, every day.', [
                                ['Beyaynetu', 'Eight dishes on injera', 19000, ['vegan', 'popular'], ['k' => 640, 'ph' => ['injera-platter']]],
                                ['Shiro', 'Chickpea stew, berbere', 13000, ['vegan', 'spicy'], ['k' => 480]],
                                ['Misir wot', 'Red lentils, berbere', 13000, ['vegan', 'spicy'], ['k' => 450]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-dhow', 'name' => 'Dhow & Reef', 'email' => 'dhow@example.com', 'plan' => 'plus', 'age' => 1,
                'style' => 'coastal', 'color' => '#c4523a', 'logo' => 'drop', 'banner' => null, 'banner_text' => 'under',
                'currency' => 'USD', 'timezone' => 'Africa/Mogadishu',
                'about' => "Fish from this morning's boats, grilled over charcoal by the sea.\nFamily room upstairs, fresh juice all day.",
                'address' => 'Lido Beach Road, Mogadishu', 'phone' => '+252 61 000 1604', 'hours' => [['10:00', '23:00']],
                'special' => ['Catch of the day', 'Ask for today\'s fish: grilled whole with lime and chilli, rice and salad — $12.50.', 'Whole grilled fish', 'fish-tilapia'],
                'news' => ['Friday:', 'Family platter with fish, prawns and rice for four — $38.'],
                'catalogue' => [
                    'groups' => [
                        'sauce' => ['Sauce', 0, 2, [['Lime & chilli', 0], ['Garlic butter', 75], ['Basbaas (hot sauce)', 0]]],
                        'side' => ['Side', 1, 1, [['Rice', 0], ['Fries', 0], ['Banana', 0]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Grilled fish', 'Whole fish from the morning boats.', [
                                ['Whole grilled fish', 'Kingfish or snapper, lime, chilli', 1250, ['popular', 'gluten_free'], ['d' => 'Today\'s fish, scored, rubbed with spices and grilled whole over charcoal. With rice or fries and a little salad.', 'al' => ['fish'], 'pm' => 25, 'ph' => ['fish-tilapia'], 'g' => ['sauce', 'side']]],
                                ['Red mullet', 'Three small fish, lemon', 950, ['gluten_free'], ['al' => ['fish'], 'pm' => 20, 'ph' => ['fish-mullet'], 'g' => ['sauce', 'side']]],
                                ['Fish steak', 'Tuna, cumin, garlic', 1100, ['gluten_free', 'spicy'], ['al' => ['fish'], 'pm' => 15, 'g' => ['sauce', 'side']]],
                            ], 'fish-tilapia'],
                            ['Prawns & seafood', null, [
                                ['Prawn pasta', 'Garlic, tomato, chilli', 1050, ['spicy'], ['al' => ['gluten', 'crustaceans'], 'pm' => 18, 'ph' => ['shrimp-pasta']]],
                                ['Seafood pasta', 'Prawns, calamari, cream', 1150, [], ['al' => ['gluten', 'crustaceans', 'molluscs', 'milk'], 'pm' => 18, 'ph' => ['seafood-pasta']]],
                                ['Fried calamari', 'Lemon and garlic sauce', 850, ['new'], ['al' => ['gluten', 'molluscs', 'eggs'], 'pm' => 12]],
                            ]],
                            ['Rice', null, [
                                ['Bariis iskukaris', 'Spiced rice, raisins, banana', 450, ['vegan'], ['ph' => ['biryani-plates']]],
                                ['Fish & rice platter', 'For two', 2200, ['popular'], ['al' => ['fish'], 'ph' => ['kabsa-platter'], 'so' => true]],
                            ]],
                            ['Starters', null, [
                                ['Sambuusa', 'Tuna, three pieces', 300, ['spicy'], ['al' => ['gluten', 'fish'], 'ph' => ['sambuusa']]],
                                ['Fries', null, 250, ['vegan'], ['ph' => ['fries-bowl']]],
                            ]],
                            ['Juices', 'Fresh, no sugar added.', [
                                ['Mango', null, 250, ['vegan'], ['ph' => ['mango-juice']]],
                                ['Lemon & mint', null, 225, ['vegan'], ['ph' => ['iced-mint-tea']]],
                                ['Papaya', null, 250, ['vegan'], []],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-maison', 'name' => 'Maison Sheba', 'email' => 'maison@example.com', 'plan' => 'business', 'age' => 1,
                'style' => 'luxe', 'color' => '#7a2e3a', 'logo' => 'ring', 'banner' => null, 'banner_text' => 'under',
                'about' => "Ethiopian ingredients, French technique. A seven-table dining room and a tej cellar.\nPlease book; dress smart casual.",
                'address' => 'Old Airport, Addis Ababa', 'phone' => '+251 11 000 1705', 'hours' => [['18:30', '23:00']],
                'special' => ['Tasting menu', 'Seven courses chosen by the chef, with tej pairing — Br 7,800 per guest.', 'Lamb loin', 'fine-plated'],
                'news' => ['This month:', 'Harar honey and wild thyme on the dessert menu.'],
                'catalogue' => [
                    'groups' => [
                        'doneness' => ['Cooked', 1, 1, [['Rare', 0], ['Medium rare', 0], ['Medium', 0]]],
                    ],
                    'menus' => [
                        ['name' => 'Dinner', 'slug' => 'dinner', 'categories' => [
                            ['Starters', 'To begin.', [
                                ['Beef tartare', 'Mitmita, quail egg, teff crisp', 98000, ['spicy'], ['al' => ['eggs'], 'ph' => ['fine-starter']]],
                                ['Garden of the season', 'Vegetables, herb oil, seeds', 86000, ['vegan', 'gluten_free'], ['ph' => ['fine-salad']]],
                                ['Lentil velouté', 'Red lentils, berbere butter', 72000, ['vegetarian'], ['al' => ['milk']]],
                            ]],
                            ['Mains', 'With seasonal vegetables.', [
                                ['Lamb loin', 'Teff crumble, rosemary jus', 165000, ['popular'], ['d' => 'Lamb loin roasted pink, with a teff and herb crumble and a rosemary jus.', 'pm' => 25, 'ph' => ['fine-plated'], 'g' => ['doneness']]],
                                ['Beef fillet', 'Red wine, microgreens', 178000, [], ['pm' => 25, 'ph' => ['steak-micro'], 'g' => ['doneness']]],
                                ['Nile perch', 'Beurre blanc, crushed potatoes', 142000, ['gluten_free'], ['al' => ['fish', 'milk'], 'pm' => 20]],
                            ]],
                            ['Desserts', null, [
                                ['Honey panna cotta', 'Tej, berries', 52000, ['vegetarian', 'gluten_free'], ['al' => ['milk'], 'ph' => ['panna-cotta']]],
                                ['Chocolate & coffee', 'Yirgacheffe, cocoa, cream', 58000, ['vegetarian'], ['al' => ['milk', 'eggs', 'gluten'], 'ph' => ['dessert-plate']]],
                            ], 'dessert-plate'],
                            ['Tej & wine', 'By the glass.', [
                                ['House tej', 'Honey wine, semi-dry', 38000, ['vegan'], []],
                                ['Red wine', 'Rift Valley, glass', 52000, ['vegan'], []],
                                ['Sparkling water', null, 12000, ['vegan'], ['ph' => ['sparkling-water']]],
                            ]],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
