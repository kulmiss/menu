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
                'about' => "A small dining room for slow evenings: seasonal plates, classic aperitifs and quiet music.\nBooking recommended on Fridays and Saturdays.",
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
                                ['Lentil velouté', 'Red lentils, cumin, crème fraîche', 34000, ['vegetarian', 'gluten_free'], ['al' => ['milk'], 'k' => 260, 'ph' => ['red-lentil-soup']]],
                                ['Seared prawns', 'Citrus, pickled vegetables, saffron', 52000, ['gluten_free'], ['al' => ['crustaceans'], 'k' => 240, 'ph' => ['shrimp-appetizer']]],
                                ['Crab cake', 'Lemon foam, herb purée', 54000, [], ['al' => ['crustaceans', 'gluten', 'eggs'], 'k' => 310, 'ph' => ['appetizer']]],
                                ['Oysters', 'Six on ice, lemon, mignonette', 76000, ['gluten_free'], ['al' => ['molluscs'], 'k' => 90, 'ph' => ['oysters'], 'sz' => [['Six', 76000], ['Twelve', 140000]]]],
                                ['Octopus salad', 'Charred octopus, tomato, lemon', 58000, ['gluten_free'], ['al' => ['molluscs'], 'k' => 280, 'ph' => ['octopus-salad']]],
                                ['Prawn & avocado salad', 'Leaves, lime dressing', 48000, ['gluten_free', 'new'], ['al' => ['crustaceans'], 'k' => 260, 'ph' => ['prawn-salad']]],
                                ['Ricotta ravioli', 'Brown butter, sage, parmesan', 56000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 520, 'ph' => ['ravioli']]],
                            ]],
                            ['Mains', 'Served with seasonal vegetables.', [
                                ['Beef tenderloin', '200 g, red wine jus, microgreens', 135000, ['popular'], ['d' => 'Grass-fed tenderloin, seared and rested, with a red wine jus.', 'k' => 690, 'pm' => 25, 'ph' => ['steak-micro', 'steak-veg'], 'g' => ['doneness', 'sides']]],
                                ['Ribeye & pepper sauce', '300 g, asparagus, tomato', 148000, [], ['k' => 880, 'pm' => 25, 'ph' => ['steak-sauce'], 'g' => ['doneness', 'sides']]],
                                ['Seafood linguine', 'Prawns, mushrooms, cream', 98000, [], ['al' => ['gluten', 'crustaceans', 'milk'], 'k' => 760, 'pm' => 18, 'ph' => ['seafood-pasta']]],
                                ['Prawn tagliatelle', 'Garlic butter, lemon, chili', 104000, ['spicy'], ['al' => ['gluten', 'crustaceans', 'milk'], 'k' => 720, 'pm' => 18, 'ph' => ['shrimp-pasta']]],
                                ['Wild mushroom risotto', 'Parmesan, thyme', 78000, ['vegetarian', 'gluten_free'], ['al' => ['milk'], 'k' => 640, 'pm' => 22, 'ph' => ['risotto']]],
                                ['Roast salmon', 'Couscous, roasted vegetables, herb sauce', 118000, [], ['al' => ['fish', 'gluten'], 'k' => 610, 'pm' => 20, 'ph' => ['salmon-plated'], 'so' => true]],
                                ['Duck breast', 'Glazed vegetables, cherry jus', 126000, ['gluten_free'], ['k' => 680, 'pm' => 25, 'ph' => ['duck']]],
                                ['Rack of lamb', 'Herb sauce, roasted roots', 152000, ['gluten_free', 'popular'], ['k' => 790, 'pm' => 25, 'ph' => ['lamb-rack2'], 'g' => ['doneness', 'sides']]],
                                ['Braised beef cheek', 'Carrots, green beans, red wine sauce', 108000, ['gluten_free'], ['k' => 720, 'pm' => 20, 'ph' => ['meat-plated']]],
                                ['Grilled squid', 'Pesto, roasted vegetables, lemon', 92000, ['gluten_free'], ['al' => ['molluscs'], 'k' => 480, 'pm' => 15, 'ph' => ['grilled-squid']]],
                                ['Mussel & prawn spaghetti', 'Tomato, garlic, parsley', 96000, [], ['al' => ['gluten', 'crustaceans', 'molluscs'], 'k' => 740, 'pm' => 18, 'ph' => ['seafood-spaghetti']]],
                                ['Spaghetti pomodoro', 'Slow tomato sauce, basil', 64000, ['vegan'], ['al' => ['gluten'], 'k' => 620, 'pm' => 15, 'ph' => ['pomodoro']]],
                            ]],
                            ['Desserts', null, [
                                ['Orange panna cotta', 'Walnut, pomegranate', 36000, ['vegetarian', 'gluten_free'], ['al' => ['milk', 'nuts'], 'k' => 320, 'ph' => ['panna-cotta']]],
                                ['Dessert trio', 'Chocolate mousse, cheesecake, berries', 42000, ['vegetarian', 'popular'], ['al' => ['milk', 'eggs', 'gluten'], 'k' => 520, 'ph' => ['dessert-plate']]],
                                ['Tiramisu', 'Mascarpone, espresso, cocoa', 38000, ['vegetarian'], ['al' => ['milk', 'eggs', 'gluten'], 'k' => 450, 'ph' => ['tiramisu-art']]],
                                ['Crème brûlée', 'Vanilla, mint', 34000, ['vegetarian', 'gluten_free'], ['al' => ['milk', 'eggs'], 'k' => 380, 'ph' => ['creme-brulee']]],
                                ['Baked cheesecake', 'Berry compote', 36000, ['vegetarian'], ['al' => ['milk', 'eggs', 'gluten'], 'k' => 480, 'ph' => ['cheesecake']]],
                                ['Dark chocolate cake', 'Warm, with cream', 38000, ['vegetarian'], ['al' => ['milk', 'eggs', 'gluten'], 'k' => 560, 'ph' => ['chocolate-cake']]],
                            ]],
                        ]],
                        ['name' => 'Drinks', 'slug' => 'drinks', 'categories' => [
                            ['Aperitifs', 'Before dinner.', [
                                ['Negroni', 'Gin, red bitter, vermouth, orange', 46000, ['vegan'], ['ph' => ['negroni']]],
                                ['Clarified negroni', 'Crystal-clear, over one big ice cube', 52000, ['vegan', 'new'], ['ph' => ['clear-cocktail']]],
                                ['Sunset layers', 'Orange, passion fruit, grenadine', 42000, ['vegan'], ['ph' => ['layered']]],
                                ['Strawberry & orange spritz', 'Fresh fruit, soda — alcohol-free on request', 38000, ['vegan'], ['ph' => ['strawberry-orange']]],
                                ['Mint & lime mojito', 'Fresh mint, lime, soda', 36000, ['vegan'], ['ph' => ['mojito']]],
                            ]],
                            ['After dinner', null, [
                                ['Espresso', null, 9000, ['vegan'], ['ph' => ['espresso']]],
                                ['Cappuccino', null, 11000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['cappuccino-2']]],
                                ['Fresh mint tea', null, 9000, ['vegan'], ['ph' => ['green-tea']]],
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
                                ['Margherita', 'Tomato, mozzarella, basil', 42000, ['vegetarian', 'popular'], ['al' => ['gluten', 'milk'], 'k' => 820, 'pm' => 12, 'ph' => ['pizzas-plates'], 'g' => ['crust', 'toppings'], 'sz' => [['Medium', 42000], ['Large', 58000]]]],
                                ['Pepperoni', 'Beef pepperoni, mozzarella, oregano', 52000, ['popular'], ['al' => ['gluten', 'milk'], 'k' => 980, 'pm' => 12, 'ph' => ['pepperoni-slice', 'pizza-checkered'], 'g' => ['crust', 'toppings'], 'sz' => [['Medium', 52000], ['Large', 68000]]]],
                                ['Ham & mushroom', 'Ham, mushrooms, mozzarella', 54000, [], ['al' => ['gluten', 'milk'], 'k' => 940, 'pm' => 12, 'ph' => ['pizza-ham'], 'g' => ['crust', 'toppings'], 'sz' => [['Medium', 54000], ['Large', 70000]]]],
                                ['Veggie garden', 'Peppers, olives, onion, tomato', 46000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 780, 'pm' => 12, 'ph' => ['pizzas-wood'], 'g' => ['crust', 'toppings'], 'sz' => [['Medium', 46000], ['Large', 62000]]]],
                                ['Four cheese', 'Mozzarella, gorgonzola, parmesan, ayib', 56000, ['vegetarian', 'new'], ['al' => ['gluten', 'milk'], 'k' => 1020, 'pm' => 12, 'ph' => ['pizzas-wine'], 'g' => ['crust'], 'sz' => [['Medium', 56000], ['Large', 72000]]]],
                                ['Berbere chicken', 'Spiced chicken, peppers, onion, green chili', 55000, ['spicy'], ['al' => ['gluten', 'milk'], 'k' => 910, 'pm' => 12, 'ph' => ['pizza-peppers'], 'g' => ['crust', 'toppings'], 'sz' => [['Medium', 55000], ['Large', 71000]]]],
                                ['Basil & cherry tomato', 'Fresh tomatoes, mozzarella, basil', 44000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 800, 'pm' => 12, 'ph' => ['margherita'], 'g' => ['crust', 'toppings'], 'sz' => [['Medium', 44000], ['Large', 60000]]]],
                                ['Mushroom & pepper', 'Mushrooms, sweet peppers, oregano', 48000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 820, 'pm' => 12, 'ph' => ['pizza-mushroom'], 'g' => ['crust', 'toppings'], 'sz' => [['Medium', 48000], ['Large', 64000]]]],
                                ['Olive & pepper', 'Black olives, peppers, mozzarella', 47000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 830, 'pm' => 12, 'ph' => ['pizza-olive'], 'g' => ['crust', 'toppings'], 'sz' => [['Medium', 47000], ['Large', 63000]]]],
                                ['Spinach & mushroom', 'Spinach, mushrooms, garlic', 48000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 790, 'pm' => 12, 'ph' => ['pizza-spinach'], 'g' => ['crust', 'toppings'], 'sz' => [['Medium', 48000], ['Large', 64000]]]],
                                ['Salami & rocket', 'Beef salami, mushrooms, rocket', 56000, [], ['al' => ['gluten', 'milk'], 'k' => 960, 'pm' => 12, 'ph' => ['pizza-salami'], 'g' => ['crust', 'toppings'], 'sz' => [['Medium', 56000], ['Large', 72000]]]],
                                ['Supreme veggie', 'Mushrooms, tomato, olives, red pepper', 50000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 840, 'pm' => 12, 'ph' => ['pizza-veg'], 'g' => ['crust', 'toppings'], 'sz' => [['Medium', 50000], ['Large', 66000]]]],
                                ['Mini pizzas', 'Four little pizzas with olives and rocket', 30000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 620, 'pm' => 10, 'ph' => ['mini-pizzas']]],
                            ]],
                            ['Burgers', 'Smashed patties, potato bun.', [
                                ['Stack burger', 'Double smashed beef, cheese, pickles', 44000, ['popular'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 1050, 'pm' => 10, 'ph' => ['burger-tray']]],
                                ['Bacon double', 'Bacon, cheddar, BBQ sauce', 48000, [], ['al' => ['gluten', 'milk'], 'k' => 1180, 'pm' => 10, 'ph' => ['burger-boards']]],
                                ['Cheeseburger', 'Beef, cheddar, onion, ketchup', 38000, [], ['al' => ['gluten', 'milk'], 'k' => 880, 'pm' => 10, 'ph' => ['cheeseburger']]],
                                ['BBQ board burger', 'Smoked cheese, onion rings, BBQ sauce', 46000, [], ['al' => ['gluten', 'milk'], 'k' => 1120, 'pm' => 10, 'ph' => ['burger-board']]],
                                ['Bacon cheeseburger', 'Crispy bacon, cheddar, lettuce, tomato', 47000, [], ['al' => ['gluten', 'milk'], 'k' => 1090, 'pm' => 10, 'ph' => ['burger-bacon']]],
                                ['Smash & fries', 'Single smash burger with fries', 36000, ['new'], ['al' => ['gluten', 'milk'], 'k' => 980, 'pm' => 10, 'ph' => ['burger-half']]],
                            ]],
                            ['Hot dogs', null, [
                                ['Classic hot dog', 'Beef sausage, ketchup, mustard', 22000, [], ['al' => ['gluten'], 'k' => 520, 'pm' => 6, 'ph' => ['hot-dog']]],
                                ['Chili cheese dog', 'Beef chili, cheddar, sweet potato fries', 30000, ['spicy'], ['al' => ['gluten', 'milk'], 'k' => 780, 'pm' => 8, 'ph' => ['chili-dog']]],
                                ['Bacon dog', 'Wrapped in bacon, with fries', 29000, [], ['al' => ['gluten'], 'k' => 820, 'pm' => 8, 'ph' => ['bacon-dog']]],
                                ['Loaded dog & fries', 'Crispy toppings, fries on the side', 28000, [], ['al' => ['gluten'], 'k' => 790, 'pm' => 8, 'ph' => ['hot-dog-fries']]],
                                ['Hot dog party tray', 'Five hot dogs with different toppings', 95000, ['popular'], ['al' => ['gluten', 'milk'], 'k' => 2600, 'pm' => 12, 'ph' => ['hot-dogs-tray']]],
                            ]],
                            ['Chicken', null, [
                                ['Chicken wings', 'Eight crispy wings', 36000, ['spicy'], ['al' => ['gluten'], 'k' => 780, 'pm' => 14, 'ph' => ['wings'], 'g' => ['dip'], 'sz' => [['8 pieces', 36000], ['16 pieces', 64000]]]],
                                ['Chicken & fries', 'Three pieces, fries, dip', 38000, [], ['al' => ['gluten'], 'k' => 990, 'pm' => 14, 'ph' => ['chicken-fries'], 'g' => ['dip']]],
                                ['Chicken plate', 'Crispy chicken, fries, salad', 42000, ['new'], ['al' => ['gluten'], 'k' => 1010, 'pm' => 14, 'ph' => ['chicken-plate'], 'g' => ['dip']]],
                            ]],
                            ['Pasta', null, [
                                ['Spaghetti bolognese', 'Beef ragù, parmesan', 36000, ['popular'], ['al' => ['gluten', 'milk'], 'k' => 760, 'pm' => 12, 'ph' => ['bolognese']]],
                                ['Carbonara', 'Beef bacon, egg, parmesan, black pepper', 38000, [], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 820, 'pm' => 12, 'ph' => ['carbonara']]],
                                ['Mushroom pasta', 'Creamy mushroom sauce', 34000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 700, 'pm' => 12, 'ph' => ['pasta-mushroom']]],
                                ['Chicken cream pasta', 'Chicken, cream, garlic', 37000, [], ['al' => ['gluten', 'milk'], 'k' => 790, 'pm' => 12, 'ph' => ['pasta-cream']]],
                            ]],
                            ['Sides & drinks', null, [
                                ['Fries', 'With ketchup', 14000, ['vegan'], ['k' => 420, 'ph' => ['fries-ketchup'], 'g' => ['dip']]],
                                ['Cheesy fries', 'Cheese sauce, spring onion', 19000, ['vegetarian'], ['al' => ['milk'], 'k' => 640, 'ph' => ['fries-bowl'], 'g' => ['dip']]],
                                ['Garden salad', 'Lettuce, tomato, cucumber', 16000, ['vegan', 'gluten_free'], ['k' => 160, 'ph' => ['garden-salad']]],
                                ['Cola', null, 6000, ['vegan'], ['ph' => ['soda-glass'], 'sz' => [['Small', 6000], ['Large', 9000]]]],
                                ['Sparkling water', null, 6000, ['vegan'], ['ph' => ['sparkling-water']]],
                                ['Freakshake', 'Chocolate shake, cream, pretzels and treats', 24000, ['vegetarian'], ['al' => ['milk', 'gluten'], 'k' => 980, 'ph' => ['freakshake'], 'so' => true]],
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
                                ['Butter croissant', null, 8000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 280, 'ph' => ['croissants']]],
                                ['Pain au chocolat', null, 10000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 320, 'ph' => ['pain-choc']]],
                                ['Cinnamon roll', 'Cream cheese icing', 11000, ['vegetarian', 'popular'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 420, 'ph' => ['cinnamon-plate']]],
                                ['Cinnamon buns to go', 'Box of six', 58000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'ph' => ['cinnamon-box']]],
                                ['Sweet rolls', 'Soft milk bread, four pieces', 12000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 520, 'ph' => ['sweet-rolls']]],
                                ['Blueberry muffin', null, 9000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 380, 'ph' => ['muffins']]],
                                ['Berry muffin', 'Strawberry and currant', 9000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 370, 'ph' => ['berry-muffins']]],
                                ['Cookies', 'Chocolate chip, oat or double chocolate', 6000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 210, 'ph' => ['cookies']]],
                                ['Pastry & flan of the day', 'From the counter', 10000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'ph' => ['bakery-counter']]],
                            ]],
                            ['Cakes', null, [
                                ['Éclairs', 'Chocolate, coffee, pistachio', 11000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs', 'nuts'], 'k' => 290, 'ph' => ['eclairs']]],
                                ['Berry roulade', 'Meringue, cream, berries', 13000, ['vegetarian', 'gluten_free'], ['al' => ['milk', 'eggs'], 'k' => 340, 'ph' => ['roulade']]],
                                ['Berry cups', 'Cream, berries, crumble', 12000, ['vegetarian'], ['al' => ['milk', 'gluten'], 'k' => 310, 'ph' => ['berry-desserts']]],
                                ['Cheesecake', 'Baked, with berries', 13000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 450, 'ph' => ['cheesecake']]],
                                ['Chocolate cake', 'One slice', 12500, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 520, 'ph' => ['chocolate-cake']]],
                                ['Cupcake', 'Vanilla, buttercream', 8000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 340, 'ph' => ['cupcake']]],
                                ['Tiramisu cup', 'Mascarpone, coffee, cocoa', 13000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 420, 'ph' => ['tiramisu-cups']]],
                                ['Panna cotta', 'Orange, walnut, pomegranate', 12000, ['vegetarian', 'gluten_free'], ['al' => ['milk', 'nuts'], 'k' => 320, 'ph' => ['panna-cotta'], 'so' => true]],
                            ]],
                            ['Breakfast', 'Until 12:00.', [
                                ['Avocado toast', 'Sourdough, lime, chili, egg', 22000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'k' => 480, 'ph' => ['avocado-toast']]],
                                ['Pancakes & berries', 'Maple syrup, fresh berries', 20000, ['vegetarian', 'popular'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 560, 'ph' => ['pancakes-berries']]],
                                ['Waffle', 'Honey, cream and fruit', 19000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 520, 'ph' => ['waffle']]],
                                ['Granola', 'Yogurt, honey and berries', 16000, ['vegetarian'], ['al' => ['milk', 'nuts'], 'k' => 410, 'ph' => ['granola']]],
                                ['Yogurt bowls', 'Fruit, seeds and honey', 15000, ['vegetarian'], ['al' => ['milk'], 'k' => 380, 'ph' => ['yogurt-bowls']]],
                                ['Grilled cheese', 'Toasted sourdough, tomato soup', 18000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 560, 'ph' => ['grilled-cheese']]],
                            ]],
                        ]],
                        ['name' => 'Juice bar', 'slug' => 'juice-bar', 'categories' => [
                            ['Fresh juices', 'Cold-pressed to order.', [
                                ['Juice of the day', 'Ask what\'s fresh', 10000, ['vegan'], ['ph' => ['juice-jars'], 'sz' => [['Small', 10000], ['Large', 14000]]]],
                                ['Berry mint cooler', 'Berries, lime, mint', 12000, ['vegan', 'popular'], ['k' => 120, 'ph' => ['berry-mint']]],
                                ['Mixed fruit trio', 'Three small juices to taste', 18000, ['vegan'], ['ph' => ['juices-table']]],
                                ['Green kiwi', 'Kiwi, apple, mint', 12000, ['vegan', 'new'], ['k' => 110, 'ph' => ['kiwi-juice']]],
                                ['Orange', null, 9000, ['vegan'], ['k' => 140, 'ph' => ['orange-juice']]],
                                ['Watermelon', 'With mint', 9000, ['vegan'], ['k' => 90, 'ph' => ['watermelon-juice']]],
                                ['Pineapple', null, 10000, ['vegan'], ['k' => 130, 'ph' => ['pineapple-juice']]],
                                ['Strawberry', 'Strawberry and lime', 11000, ['vegan'], ['k' => 120, 'ph' => ['strawberry-juice']]],
                                ['Mango', null, 10000, ['vegan'], ['k' => 190, 'ph' => ['mango-juice']]],
                            ]],
                            ['Coffee & matcha', null, [
                                ['Matcha latte', 'Ceremonial matcha, oat milk', 13000, ['vegan'], ['al' => ['gluten'], 'k' => 140, 'ph' => ['matcha']]],
                                ['Flat white', null, 10000, [], ['al' => ['milk'], 'ph' => ['flat-white']]],
                                ['Macchiato', null, 7500, [], ['al' => ['milk'], 'ph' => ['macchiato']]],
                                ['Cappuccino', null, 9500, [], ['al' => ['milk'], 'ph' => ['cappuccino']]],
                                ['Iced coffee', 'Cold brew, milk, ice', 11000, [], ['al' => ['milk'], 'ph' => ['iced-coffee']]],
                                ['Hot chocolate', 'Whipped cream', 11000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['hot-chocolate']]],
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
                                ['Misir wot', 'Red lentils, berbere, garlic', 20000, ['vegan', 'spicy'], ['k' => 480, 'pm' => 15, 'ph' => ['lentil-dish'], 'g' => ['spice']]],
                                ['Key wot', 'Beef in a rich berbere sauce', 40000, ['spicy'], ['k' => 760, 'pm' => 20, 'ph' => ['beef-curry'], 'g' => ['spice', 'side']]],
                                ['Yebeg alicha', 'Mild lamb stew with turmeric and ginger', 42000, ['gluten_free'], ['k' => 690, 'pm' => 20, 'ph' => ['meat-stew-pot'], 'g' => ['side']]],
                                ['Kitfo', 'Minced beef, mitmita, ayib, gomen', 46000, [], ['al' => ['milk'], 'k' => 820, 'pm' => 15, 'ph' => ['raw-kebbe'], 'g' => ['spice'], 'so' => true]],
                            ]],
                            ['Tibs & grill · ጥብስ', null, [
                                ['Shekla tibs', 'Sizzling beef on a clay dish, rosemary, onion', 48000, ['popular'], ['k' => 860, 'pm' => 20, 'ph' => ['tibs'], 'g' => ['spice', 'side']]],
                                ['Lamb chops with awaze', 'Grilled lamb, garlic, awaze dip', 62000, ['spicy'], ['k' => 880, 'pm' => 20, 'ph' => ['lamb-chops'], 'g' => ['side']]],
                                ['Awaze beef skewers', 'Charcoal-grilled, peppers, lemon', 44000, ['spicy'], ['k' => 720, 'pm' => 18, 'ph' => ['kebab-tray'], 'g' => ['spice', 'side']]],
                                ['Mixed grill', 'Kebab, chicken, fries and pickles', 78000, ['new'], ['k' => 1300, 'pm' => 25, 'ph' => ['mixed-grill'], 'sz' => [['For one', 78000], ['For two', 140000]]]],
                                ['Grilled tilapia', 'Whole fish from Lake Ziway, lemon', 46000, ['gluten_free'], ['al' => ['fish'], 'k' => 620, 'pm' => 25, 'ph' => ['fish-tilapia']]],
                            ]],
                            ['Somali kitchen', 'With banana and basbaas.', [
                                ['Suqaar', 'Somali beef cubes with potato and peppers', 40000, ['new'], ['k' => 740, 'pm' => 20, 'ph' => ['spicy-beef-stew']]],
                                ['Bariis iskukaris', 'Spiced rice with goat, banana on the side', 44000, [], ['k' => 900, 'pm' => 25, 'ph' => ['lamb-mandi']]],
                                ['Bariis iyo digaag', 'Spiced rice with roast chicken', 40000, [], ['k' => 880, 'pm' => 25, 'ph' => ['chicken-rice-sides']]],
                                ['Hilib ari', 'Goat on the bone over rice', 52000, ['popular'], ['k' => 950, 'pm' => 25, 'ph' => ['mandi-meat']]],
                                ['Bariis platter', 'Rice, grilled meat and sauces for the table', 98000, [], ['k' => 2100, 'pm' => 25, 'ph' => ['grill-meat-rice']]],
                                ['Baasto iyo suugo', 'Pasta with Somali beef sauce', 30000, [], ['al' => ['gluten'], 'k' => 790, 'pm' => 12, 'ph' => ['bolognese']]],
                                ['Sambuusa', 'Beef or lentil, three pieces', 9000, ['popular'], ['al' => ['gluten'], 'k' => 360, 'ph' => ['sambuusa'], 'sz' => [['3 pieces', 9000], ['6 pieces', 16000]]]],
                            ]],
                            ['Breakfast', 'Until 11:00.', [
                                ['Ful', 'Fava beans, olive oil, tomato, warm bread', 15000, ['vegan'], ['al' => ['gluten'], 'k' => 480, 'pm' => 8, 'ph' => ['ful']]],
                                ['Eggs & spiced tea', 'Two fried eggs, bread, a glass of tea', 14000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'k' => 420, 'ph' => ['eggs-tea']]],
                            ]],
                            ['Soup & salad', null, [
                                ['Misir shorba', 'Lentil soup with lemon', 12000, ['vegan'], ['k' => 260, 'ph' => ['lentil-soup']]],
                                ['Ye-salata', 'Lettuce, tomato, onion, lemon', 11000, ['vegan', 'gluten_free'], ['k' => 140, 'ph' => ['garden-salad']]],
                            ]],
                            ['Coffee & tea', 'Our coffee is roasted every morning.', [
                                ['Coffee ceremony · ቡና', 'Three rounds from the jebena, with popcorn', 18000, ['vegan', 'popular'], ['ph' => ['ceremony-2', 'jebena'], 'sz' => [['For one', 18000], ['For the table', 45000]]]],
                                ['Spiced tea', 'Black tea, cinnamon, clove, cardamom', 6000, ['vegan'], ['ph' => ['spiced-tea']]],
                                ['Ginger tea', 'Fresh ginger and honey', 6500, ['vegetarian'], ['ph' => ['ginger-tea']]],
                                ['Macchiato', null, 7000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['macchiato']]],
                                ['Espresso', null, 6000, ['vegan'], ['ph' => ['espresso']]],
                            ]],
                            ['Juices & drinks', 'Fresh, no sugar added.', [
                                ['Avocado spris', 'Avocado, mango and papaya in layers', 12000, ['vegan', 'popular'], ['k' => 280, 'ph' => ['avocado-smoothie']]],
                                ['Mango juice', null, 10000, ['vegan'], ['k' => 190, 'ph' => ['mango-juice']]],
                                ['Fresh juice jars', 'Papaya, mango or orange', 10000, ['vegan'], ['ph' => ['juice-jars']]],
                                ['Orange juice', null, 9000, ['vegan'], ['k' => 140, 'ph' => ['orange-juice']]],
                                ['Watermelon juice', null, 9000, ['vegan'], ['k' => 90, 'ph' => ['watermelon-juice2']]],
                                ['Cold soda', 'Cola, orange or lemon', 5000, ['vegan'], ['ph' => ['soda-glass']]],
                                ['Sparkling water', null, 5000, ['vegan'], ['ph' => ['sparkling-water']]],
                            ]],
                            ['Sweet', null, [
                                ['Fruit salad', 'Papaya, mango, banana, passion fruit', 12000, ['vegan', 'gluten_free'], ['k' => 210, 'ph' => ['fruit-salad']]],
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
                                ['Poolside tropical', 'Passion fruit, cucumber, mint', 42000, ['vegan'], ['k' => 150, 'ph' => ['tropical'], 'g' => ['ice']]],
                                ['Ruby spritz', 'Pomegranate, citrus, soda', 40000, ['vegan'], ['k' => 140, 'ph' => ['red-mocktail'], 'g' => ['ice']]],
                                ['Berry & blue duo', 'Two drinks: blueberry lagoon and berry fizz', 68000, ['vegan', 'popular'], ['k' => 260, 'ph' => ['berry-mocktail'], 'g' => ['ice']]],
                                ['Amber sunset', 'Orange, passion fruit and grenadine in layers', 44000, ['vegan'], ['k' => 170, 'ph' => ['layered'], 'g' => ['ice']]],
                            ]],
                            ['Classics', null, [
                                ['Negroni', 'Gin, red bitter, sweet vermouth, orange', 52000, ['vegan'], ['ph' => ['negroni'], 'g' => ['ice']]],
                                ['Clarified punch', 'Crystal-clear, over one big ice cube', 56000, ['vegan', 'new'], ['ph' => ['clear-cocktail']]],
                                ['Mojito', 'Rum, lime, mint, soda — or without rum', 46000, ['vegan'], ['ph' => ['mojito'], 'g' => ['ice']]],
                                ['Cocktail flight', 'Four minis chosen by the bartender', 98000, ['popular'], ['ph' => ['four-cocktails']]],
                                ['Bartender\'s choice', 'Tell us what you like', 52000, [], ['ph' => ['cocktails-bar']]],
                            ]],
                            ['Alcohol-free', null, [
                                ['Pink lemonade', 'Strawberry, lemon, soda', 28000, ['vegan'], ['k' => 130, 'ph' => ['strawberry-lemonade'], 'g' => ['ice']]],
                                ['Mint lemonade', 'Fresh mint, lemon', 26000, ['vegan'], ['k' => 110, 'ph' => ['mint-lemonade'], 'g' => ['ice']]],
                                ['Strawberry & orange cooler', 'Fresh fruit, soda', 30000, ['vegan'], ['k' => 140, 'ph' => ['strawberry-orange'], 'g' => ['ice']]],
                            ]],
                            ['Sharing plates', null, [
                                ['Spiced wings', 'Eight wings, mitmita glaze', 39000, ['spicy', 'popular'], ['al' => ['gluten'], 'k' => 780, 'pm' => 15, 'ph' => ['wings'], 'g' => ['dip'], 'sz' => [['8 pieces', 39000], ['16 pieces', 69000]]]],
                                ['Loaded fries', 'Cheese, jalapeños, spring onion', 26000, ['vegetarian'], ['al' => ['milk'], 'k' => 690, 'pm' => 10, 'ph' => ['fries-bowl'], 'g' => ['dip']]],
                                ['Slider trio', 'Three mini burgers, cheddar, pickles', 52000, [], ['al' => ['gluten', 'milk'], 'k' => 940, 'pm' => 15, 'ph' => ['burger-half']]],
                                ['Hummus & bread', 'Olive oil, paprika, warm flatbread', 22000, ['vegan'], ['al' => ['gluten', 'sesame'], 'k' => 520, 'ph' => ['hummus-pita']]],
                                ['Calamari rings', 'Lemon, garlic mayo', 36000, [], ['al' => ['gluten', 'molluscs', 'eggs'], 'k' => 560, 'pm' => 12, 'ph' => ['calamari-rings'], 'g' => ['dip']]],
                                ['Grilled prawns', 'Chili, lime, dipping sauce', 48000, ['spicy'], ['al' => ['crustaceans'], 'k' => 320, 'pm' => 12, 'ph' => ['grilled-shrimp']]],
                                ['Chilled prawns', 'On ice, three sauces', 52000, ['gluten_free'], ['al' => ['crustaceans'], 'k' => 280, 'ph' => ['shrimp-ice']]],
                                ['Chicken skewers', 'Garlic butter, charcoal', 34000, ['gluten_free'], ['k' => 480, 'pm' => 15, 'ph' => ['chicken-skewers'], 'g' => ['dip']]],
                                ['Mini pizzas', 'Four little pizzas, olives, rocket', 32000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 620, 'pm' => 12, 'ph' => ['mini-pizzas']]],
                                ['Mini hot dogs', 'Five, with different toppings', 42000, [], ['al' => ['gluten', 'milk'], 'k' => 1100, 'pm' => 10, 'ph' => ['hot-dogs-tray']]],
                                ['Mezze board', 'Hummus, falafel, tabbouleh, bread', 44000, ['vegan'], ['al' => ['gluten', 'sesame'], 'k' => 880, 'ph' => ['mezze']]],
                                ['Falafel', 'Crispy, with tahini', 24000, ['vegan'], ['al' => ['sesame'], 'k' => 420, 'pm' => 10, 'ph' => ['falafel']]],
                                ['Lamb kebab bites', 'Pita, herbs, yogurt sauce', 46000, [], ['al' => ['gluten', 'milk'], 'k' => 720, 'pm' => 15, 'ph' => ['kebab-pita']]],
                                ['Seafood platter', 'Crab, prawns and calamari to share', 120000, ['new'], ['al' => ['crustaceans', 'molluscs'], 'k' => 1200, 'pm' => 20, 'ph' => ['seafood-platter']]],
                            ]],
                            ['Sweet', null, [
                                ['Nut sundae', 'Vanilla, chocolate sauce, nuts', 24000, ['vegetarian'], ['al' => ['milk', 'nuts'], 'k' => 520, 'ph' => ['sundae-nuts']]],
                                ['Tiramisu cups', 'Two little cups', 26000, ['vegetarian'], ['al' => ['milk', 'eggs', 'gluten'], 'k' => 480, 'ph' => ['tiramisu-cups']]],
                            ]],
                            ['Soft drinks', null, [
                                ['Sparkling water', null, 9000, ['vegan'], ['ph' => ['sparkling-water']]],
                                ['Cola', null, 9000, ['vegan'], ['ph' => ['soda-glass'], 'sz' => [['Glass', 9000], ['Bottle', 14000]]]],
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
                                ['BBQ board burger', 'Smoked cheese, onion, BBQ sauce', 38000, [], ['al' => ['gluten', 'milk'], 'k' => 1050, 'pm' => 10, 'ph' => ['burger-board'], 'g' => ['extras', 'sauce']]],
                                ['Smash & fries basket', 'Single smash burger and fries', 30000, ['new'], ['al' => ['gluten', 'milk'], 'k' => 980, 'pm' => 8, 'ph' => ['burger-half'], 'g' => ['sauce']]],
                            ]],
                            ['Hot dogs & wraps', null, [
                                ['Classic hot dog', 'Beef sausage, ketchup, mustard', 16000, [], ['al' => ['gluten'], 'k' => 520, 'pm' => 5, 'ph' => ['hot-dog'], 'g' => ['sauce']]],
                                ['Chili cheese dog', 'Beef chili, cheddar, sweet potato fries', 24000, ['spicy'], ['al' => ['gluten', 'milk'], 'k' => 780, 'pm' => 6, 'ph' => ['chili-dog']]],
                                ['Bacon dog', 'Wrapped in bacon, fries', 23000, [], ['al' => ['gluten'], 'k' => 820, 'pm' => 6, 'ph' => ['bacon-dog']]],
                                ['Hot dog combo', 'Loaded dog with fries', 22000, ['popular'], ['al' => ['gluten'], 'k' => 790, 'pm' => 6, 'ph' => ['hot-dog-fries']]],
                                ['Shawarma wrap', 'Beef, pickles, tahini', 22000, [], ['al' => ['gluten', 'sesame'], 'k' => 640, 'pm' => 6, 'ph' => ['shawarma-wraps']]],
                                ['Chicken shawarma plate', 'With fries and garlic sauce', 28000, [], ['al' => ['gluten'], 'k' => 880, 'pm' => 8, 'ph' => ['shawarma-fries']]],
                                ['Beef kebab flatbread', 'Grilled kebab, salad, lemon', 27000, [], ['al' => ['gluten'], 'k' => 740, 'pm' => 10, 'ph' => ['kebab-flatbread']]],
                                ['Falafel wrap', 'Falafel, salad, tahini', 19000, ['vegan'], ['al' => ['gluten', 'sesame'], 'k' => 620, 'pm' => 6, 'ph' => ['falafel-wrap']]],
                            ]],
                            ['Skewers', null, [
                                ['Seekh kebab', 'Spiced minced beef, two skewers', 26000, ['spicy'], ['k' => 560, 'pm' => 10, 'ph' => ['seekh'], 'g' => ['sauce']]],
                                ['Chicken skewers', 'Garlic butter, two skewers', 24000, [], ['k' => 480, 'pm' => 10, 'ph' => ['chicken-skewers'], 'g' => ['sauce']]],
                            ]],
                            ['Sides & snacks', null, [
                                ['Fries', 'Salted, with ketchup', 12000, ['vegan'], ['k' => 420, 'ph' => ['fries-ketchup'], 'g' => ['sauce'], 'sz' => [['Regular', 12000], ['Large', 16000]]]],
                                ['Cheesy fries', 'Cheese sauce, jalapeños', 17000, ['vegetarian', 'spicy'], ['al' => ['milk'], 'k' => 640, 'ph' => ['fries-bowl'], 'g' => ['sauce']]],
                                ['Sambusa', 'Three crispy lentil sambusa', 9000, ['vegan', 'popular'], ['al' => ['gluten'], 'k' => 360, 'ph' => ['sambuusa']]],
                                ['Grilled cheese', 'Toasted, with tomato soup', 18000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 560, 'ph' => ['grilled-cheese']]],
                                ['Wings', 'Six wings, hot sauce', 24000, ['spicy'], ['k' => 610, 'ph' => ['chicken-fries'], 'g' => ['sauce']]],
                                ['Toastie & fries', 'Cheese and tomato toastie', 20000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 690, 'ph' => ['toastie-fries']]],
                                ['Club sandwich', 'Chicken, egg, tomato, lettuce', 26000, [], ['al' => ['gluten', 'eggs'], 'k' => 720, 'ph' => ['club-sandwich']]],
                                ['BLT', 'Beef bacon, lettuce, tomato', 23000, [], ['al' => ['gluten', 'eggs'], 'k' => 610, 'ph' => ['blt']]],
                                ['Pepperoni slice', 'One big slice', 14000, [], ['al' => ['gluten', 'milk'], 'k' => 380, 'ph' => ['pepperoni-slice']]],
                                ['Veggie slice', 'Peppers, olives, corn', 12000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 340, 'ph' => ['pizza-slice-veg']]],
                            ]],
                            ['Juice', 'Pressed fresh, no sugar added.', [
                                ['Avocado spris', 'Avocado, mango, papaya', 12000, ['vegan', 'popular'], ['k' => 280, 'ph' => ['avocado-smoothie']]],
                                ['Mango juice', null, 10000, ['vegan'], ['k' => 190, 'ph' => ['mango-juice']]],
                                ['Orange juice', null, 9500, ['vegan'], ['k' => 150, 'ph' => ['orange-ice']]],
                                ['Strawberry juice', null, 11000, ['vegan'], ['k' => 120, 'ph' => ['strawberry-juice']]],
                                ['Watermelon juice', null, 9000, ['vegan'], ['k' => 90, 'ph' => ['watermelon-juice2']]],
                                ['Pineapple juice', null, 10000, ['vegan'], ['k' => 130, 'ph' => ['pineapple-juice']]],
                                ['Mango shake', 'Mango and milk', 13000, ['vegetarian'], ['al' => ['milk'], 'k' => 290, 'ph' => ['mango-shake']]],
                                ['Cold soda', 'Cola, orange or lemon', 5000, ['vegan'], ['ph' => ['soda-glass']]],
                            ]],
                            ['Sweet', null, [
                                ['Donuts', 'Three glazed donuts', 12000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 690, 'ph' => ['donuts']]],
                                ['Waffle', 'Honey and fruit', 16000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 520, 'ph' => ['waffle']]],
                                ['Chocolate sundae', 'Whipped cream, cherry', 15000, ['vegetarian'], ['al' => ['milk'], 'k' => 480, 'ph' => ['sundae-choc']]],
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
                                ['حنيذ', 'Haneeth: slow-roasted lamb, the Yemeni way', 82000, ['halal'], ['k' => 1150, 'pm' => 30, 'ph' => ['lamb-mandi'], 'g' => ['rice'], 'so' => true]],
                                ['كبسة دجاج', 'Chicken kabsa: spiced tomato rice, raisins and almonds', 48000, ['halal', 'new'], ['al' => ['nuts'], 'k' => 980, 'pm' => 20, 'ph' => ['chicken-rice-sides'], 'g' => ['sauce']]],
                                ['مضغوط لحم', 'Madghout: lamb and rice cooked together', 72000, ['halal'], ['k' => 1100, 'pm' => 25, 'ph' => ['mandi-meat'], 'g' => ['sauce']]],
                                ['صينية العائلة', 'Family tray: lamb, chicken, rice and sides for four', 260000, ['halal', 'popular'], ['k' => 4200, 'pm' => 30, 'ph' => ['arab-feast']]],
                            ]],
                            ['مشاوي · Grills', 'From the charcoal grill.', [
                                ['مشاوي مشكلة', 'Mixed grill on rice with sauces', 88000, ['halal', 'popular'], ['k' => 1300, 'pm' => 25, 'ph' => ['grill-meat-rice'], 'g' => ['sauce']]],
                                ['كباب لحم', 'Lamb kebab with bread, herbs and yogurt', 56000, ['halal'], ['al' => ['gluten', 'milk'], 'k' => 760, 'pm' => 18, 'ph' => ['kebab-pita']]],
                                ['شيش طاووق', 'Shish tawook: chicken skewers, garlic sauce', 46000, ['halal'], ['k' => 620, 'pm' => 18, 'ph' => ['chicken-skewers'], 'g' => ['sauce']]],
                                ['كباب وكبة', 'Kebab and grilled kibbeh, to share', 64000, ['halal'], ['al' => ['gluten'], 'k' => 980, 'pm' => 20, 'ph' => ['kebab-kibbeh']]],
                                ['دجاج مشوي', 'Grilled chicken with bread and sauces', 48000, ['halal'], ['al' => ['gluten'], 'k' => 820, 'pm' => 20, 'ph' => ['grill-chicken-platter'], 'g' => ['sauce']]],
                            ]],
                            ['شاورما · Shawarma', null, [
                                ['صحن شاورما دجاج', 'Chicken shawarma plate with fries and toum', 34000, ['halal', 'popular'], ['al' => ['gluten'], 'k' => 880, 'pm' => 10, 'ph' => ['shawarma-fries'], 'g' => ['sauce']]],
                                ['شاورما لحم', 'Beef shawarma wrap, pickles, tahini', 26000, ['halal'], ['al' => ['gluten', 'sesame'], 'k' => 640, 'pm' => 8, 'ph' => ['shawarma-wraps']]],
                                ['وجبة شاورما عائلية', 'Family shawarma: meat, hummus, pickles and bread', 98000, ['halal'], ['al' => ['gluten', 'sesame'], 'k' => 2400, 'pm' => 15, 'ph' => ['shawarma-spread']]],
                                ['فلافل', 'Falafel: six pieces with tahini and salad', 16000, ['vegan'], ['al' => ['sesame'], 'k' => 420, 'pm' => 8, 'ph' => ['falafel']]],
                                ['ساندويتش فلافل', 'Falafel wrap with salad and tahini', 14000, ['vegan'], ['al' => ['gluten', 'sesame'], 'k' => 520, 'pm' => 6, 'ph' => ['falafel-wrap']]],
                            ]],
                            ['مقبلات · Mezze', 'To share, with warm bread.', [
                                ['حمص', 'Hummus with olive oil and warm bread', 14000, ['vegan'], ['al' => ['sesame', 'gluten'], 'k' => 380, 'ph' => ['hummus-pita']]],
                                ['حمص باللحمة', 'Hummus topped with spiced minced lamb', 22000, ['halal'], ['al' => ['sesame'], 'k' => 560, 'ph' => ['hummus-board']]],
                                ['فتوش', 'Fattoush: crisp bread, sumac and garden salad', 15000, ['vegan'], ['al' => ['gluten'], 'k' => 260, 'ph' => ['fattoush']]],
                                ['تبولة', 'Tabbouleh: parsley, tomato, bulgur, lemon', 14000, ['vegan'], ['al' => ['gluten'], 'k' => 220, 'ph' => ['tabbouleh']]],
                                ['ورق عنب', 'Stuffed vine leaves with pomegranate', 16000, ['vegan'], ['k' => 300, 'ph' => ['dolma']]],
                                ['كبة وسمبوسة', 'Kibbeh and sambusa, six pieces', 20000, ['halal'], ['al' => ['gluten'], 'k' => 620, 'ph' => ['kibbeh-samosa']]],
                                ['مناقيش زعتر', 'Manakish: flatbread with za\'atar and cheese', 14000, ['vegetarian'], ['al' => ['gluten', 'milk', 'sesame'], 'k' => 480, 'ph' => ['manakish']]],
                                ['فتة', 'Fatteh: chickpeas, yogurt, crisp bread', 18000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 520, 'ph' => ['fatteh']]],
                                ['مقبلات مشكلة', 'Mixed mezze: hummus, falafel, tabbouleh', 36000, ['vegan', 'popular'], ['al' => ['gluten', 'sesame'], 'k' => 880, 'ph' => ['mezze']]],
                                ['شوربة عدس', 'Lentil soup with lemon', 10000, ['vegan'], ['k' => 240, 'ph' => ['red-lentil-soup']]],
                            ]],
                            ['فطور · Breakfast', 'Until 11:00.', [
                                ['فطور عربي', 'Arabic breakfast: dips, eggs, vegetables, tea', 38000, ['vegetarian'], ['al' => ['gluten', 'eggs', 'milk'], 'k' => 820, 'ph' => ['arabic-breakfast']]],
                                ['فول', 'Ful: fava beans, olive oil, lemon', 12000, ['vegan'], ['k' => 420, 'ph' => ['ful']]],
                            ]],
                            ['قهوة وحلويات · Coffee & sweets', null, [
                                ['قهوة عربية', 'Arabic coffee with cardamom, a pot for the table', 12000, ['vegan', 'popular'], ['ph' => ['arabic-coffee'], 'sz' => [['Small pot', 12000], ['Large pot', 20000]]]],
                                ['كنافة', 'Kunafa: warm cheese pastry, syrup and pistachio', 18000, ['vegetarian'], ['al' => ['milk', 'gluten', 'nuts'], 'k' => 520, 'ph' => ['kunafa']]],
                                ['كنافة بالقشطة', 'Kunafa with cream and pistachio', 20000, ['vegetarian'], ['al' => ['milk', 'gluten', 'nuts'], 'k' => 560, 'ph' => ['kunafa-2']]],
                                ['بقلاوة', 'Baklava with pistachio, six pieces', 16000, ['vegetarian'], ['al' => ['gluten', 'nuts', 'milk'], 'k' => 480, 'ph' => ['baklava']]],
                                ['حلويات مشكلة', 'Mixed sweets platter for the table', 42000, ['vegetarian'], ['al' => ['gluten', 'nuts', 'milk'], 'k' => 1400, 'ph' => ['arabic-sweets']]],
                                ['شاي عدني', 'Adeni tea: milk tea with cardamom', 6000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['chai-latte']]],
                                ['ليمون بالنعناع', 'Lemon and mint juice', 9000, ['vegan'], ['ph' => ['mint-lemonade']]],
                                ['عصير مانجو', 'Fresh mango juice', 10000, ['vegan'], ['ph' => ['mango-juice']]],
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
                                ['Mixed grill', 'Kebab, chicken, fries and pickles', 145000, ['halal', 'popular'], ['k' => 1600, 'pm' => 25, 'ph' => ['mixed-grill'], 'g' => ['side'], 'sz' => [['For two', 145000], ['For four', 270000]]]],
                                ['Chicken tikka', 'Yogurt-marinated chicken, mint chutney', 52000, ['halal', 'spicy'], ['al' => ['milk'], 'k' => 640, 'pm' => 20, 'ph' => ['chicken-skewers'], 'g' => ['side', 'heat']]],
                                ['Lamb kofta', 'Minced lamb skewers, onion and sumac', 56000, ['halal'], ['k' => 720, 'pm' => 20, 'ph' => ['seekh'], 'g' => ['side', 'heat']]],
                                ['Grilled wings', 'Eight wings, lemon and garlic', 38000, ['halal'], ['k' => 690, 'pm' => 15, 'ph' => ['wings'], 'so' => true]],
                                ['Lamb chops', 'Garlic, herbs, grilled tomato', 78000, ['halal', 'gluten_free'], ['k' => 880, 'pm' => 20, 'ph' => ['lamb-chops'], 'g' => ['side']]],
                                ['Beef skewers & fries', 'Two skewers, salad, fries', 58000, ['halal'], ['k' => 950, 'pm' => 20, 'ph' => ['beef-skewers-fries']]],
                                ['Shashlik board', 'Beef shashlik, seasoned fries, vegetables', 64000, ['halal'], ['k' => 1050, 'pm' => 20, 'ph' => ['shashlik']]],
                                ['Grill selection', 'Lamb, beef and chicken with flatbread', 98000, ['halal', 'new'], ['al' => ['gluten'], 'k' => 1400, 'pm' => 25, 'ph' => ['grill-selection']]],
                                ['Kebab on flatbread', 'Lamb kebab, salad, lemon, sauce', 48000, ['halal'], ['al' => ['gluten'], 'k' => 780, 'pm' => 18, 'ph' => ['kebab-flatbread']]],
                                ['Beef kebab tray', 'Four skewers with peppers and lemon', 62000, ['halal'], ['k' => 980, 'pm' => 20, 'ph' => ['kebab-tray'], 'g' => ['side']]],
                            ]],
                            ['Curries', 'With rice or naan.', [
                                ['Butter chicken', 'Tomato, butter, cream, fenugreek', 52000, ['halal', 'popular'], ['al' => ['milk'], 'k' => 820, 'pm' => 15, 'ph' => ['butter-chicken'], 'g' => ['side']]],
                                ['Butter chicken & naan', 'A bowl of butter chicken with garlic naan', 58000, ['halal'], ['al' => ['milk', 'gluten'], 'k' => 1020, 'pm' => 15, 'ph' => ['butter-chicken-naan']]],
                                ['Chicken karahi', 'In a copper pan, ginger and green chili', 54000, ['halal', 'spicy'], ['al' => ['milk'], 'k' => 760, 'pm' => 18, 'ph' => ['curry-copper'], 'g' => ['heat']]],
                                ['Paneer makhani', 'Cottage cheese in a creamy tomato sauce', 42000, ['vegetarian'], ['al' => ['milk'], 'k' => 680, 'pm' => 15, 'ph' => ['paneer']]],
                                ['Lamb curry', 'Slow-cooked lamb, whole spices', 62000, ['halal', 'spicy'], ['k' => 840, 'pm' => 20, 'ph' => ['lamb-curry'], 'g' => ['heat']]],
                                ['Keema peas', 'Spiced minced beef with green peas', 44000, ['halal'], ['k' => 720, 'pm' => 15, 'ph' => ['keema'], 'g' => ['heat']]],
                                ['Curry trio', 'Three curries to share, with naan and rice', 110000, ['halal', 'popular'], ['al' => ['milk', 'gluten'], 'k' => 2100, 'pm' => 20, 'ph' => ['curries']]],
                                ['Curry feast for two', 'Curries, naan, rice and sides', 150000, ['halal'], ['al' => ['milk', 'gluten'], 'k' => 2800, 'pm' => 20, 'ph' => ['naan-spread']]],
                            ]],
                            ['Rice', null, [
                                ['Chicken biryani', 'Basmati, saffron, fried onions, raita', 48000, ['halal', 'popular'], ['al' => ['milk'], 'k' => 900, 'pm' => 15, 'ph' => ['biryani-dark'], 'g' => ['heat']]],
                                ['Lamb biryani', 'Slow-cooked lamb, spiced rice', 62000, ['halal'], ['al' => ['milk'], 'k' => 1050, 'pm' => 15, 'ph' => ['biryani-plates']]],
                                ['Chicken & rice', 'Spiced chicken, rice, lime', 40000, ['halal'], ['k' => 820, 'pm' => 15, 'ph' => ['chicken-rice']]],
                                ['Tandoori platter', 'Chicken, naan, salad and sauces', 72000, ['halal'], ['al' => ['milk', 'gluten'], 'k' => 1300, 'pm' => 20, 'ph' => ['indian-platter']]],
                                ['Grilled chicken & saffron rice', 'Half chicken, vegetables', 52000, ['halal'], ['k' => 980, 'pm' => 20, 'ph' => ['grilled-chicken-rice']]],
                            ]],
                            ['Starters & soup', null, [
                                ['Lentil soup', 'Red lentils, cumin and lemon', 12000, ['vegan'], ['k' => 240, 'ph' => ['lentil-soup']]],
                                ['Hummus', 'Pomegranate, mint and olives', 16000, ['vegan'], ['al' => ['sesame'], 'k' => 380, 'ph' => ['hummus-mint']]],
                                ['Samosa', 'Three crispy samosas, tamarind chutney', 14000, ['vegetarian'], ['al' => ['gluten'], 'k' => 420, 'ph' => ['samosa']]],
                                ['Falafel salad', 'Falafel, greens, tahini', 18000, ['vegan'], ['al' => ['sesame'], 'k' => 460, 'ph' => ['falafel-salad']]],
                                ['Tabbouleh', 'Parsley, tomato, mint, lemon', 14000, ['vegan'], ['al' => ['gluten'], 'k' => 220, 'ph' => ['tabbouleh-2']]],
                            ]],
                            ['Sweets', null, [
                                ['Kunafa', 'Warm cheese pastry, syrup, pistachio', 18000, ['vegetarian'], ['al' => ['milk', 'gluten', 'nuts'], 'k' => 520, 'ph' => ['kunafa']]],
                                ['Baklava', 'Pistachio, six pieces', 16000, ['vegetarian'], ['al' => ['gluten', 'nuts', 'milk'], 'k' => 480, 'ph' => ['baklava-2']]],
                                ['Strawberry sundae', 'Ice cream, cream, sprinkles — kids love it', 14000, ['vegetarian'], ['al' => ['milk'], 'k' => 420, 'ph' => ['sundae-strawberry']]],
                            ]],
                            ['Drinks', null, [
                                ['Fresh mango juice', null, 12000, ['vegan'], ['k' => 190, 'ph' => ['mango-juice']]],
                                ['Mint lemonade', null, 10000, ['vegan'], ['k' => 120, 'ph' => ['mint-lemonade']]],
                                ['Karak tea', 'Spiced milk tea', 7000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['spiced-tea']]],
                                ['Mango lassi', 'Mango, yogurt, cardamom', 13000, ['vegetarian'], ['al' => ['milk'], 'k' => 260, 'ph' => ['mango-lassi']]],
                                ['Sweet lassi', 'In a clay cup', 11000, ['vegetarian'], ['al' => ['milk'], 'k' => 220, 'ph' => ['lassi-clay']]],
                                ['Fresh orange', null, 11000, ['vegan'], ['k' => 140, 'ph' => ['orange-juice']]],
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
                'news' => ['Every morning:', 'Fresh canjeero from 06:00.'],
                'catalogue' => [
                    'groups' => [
                        'meat' => ['Meat', 1, 1, [['Goat (hilib ari)', 0], ['Chicken (digaag)', 0], ['Camel (hilib geel)', 6000]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Quraac · Breakfast', 'Until 11:00.', [
                                ['Canjeero iyo suqaar', 'Somali pancakes with spiced beef cubes', 22000, ['halal', 'popular'], ['k' => 680, 'pm' => 10, 'ph' => ['injera-platter']]],
                                ['Ful', 'Fava beans, oil, tomato, bread', 13000, ['vegan'], ['al' => ['gluten'], 'k' => 480, 'pm' => 8, 'ph' => ['ful']]],
                                ['Ukun iyo shaah', 'Eggs, bread and a glass of shaah', 12000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'k' => 420, 'ph' => ['eggs-tea']]],
                                ['Quraac buuxa', 'Full breakfast plate: eggs, beans, bread', 24000, [], ['al' => ['gluten', 'eggs'], 'k' => 820, 'pm' => 10, 'ph' => ['full-breakfast']]],
                            ]],
                            ['Qado · Lunch', 'With banana and basbaas (green chili sauce).', [
                                ['Bariis iskukaris', 'Spiced rice with your choice of meat', 44000, ['halal', 'popular'], ['k' => 980, 'pm' => 15, 'ph' => ['biryani-plates'], 'g' => ['meat']]],
                                ['Hilib ari', 'Goat on the bone over rice', 52000, ['halal'], ['k' => 860, 'pm' => 20, 'ph' => ['mandi-meat']]],
                                ['Hilib ari iyo bariis', 'Roast goat, rice, chili and lemon', 56000, ['halal'], ['k' => 1020, 'pm' => 20, 'ph' => ['lamb-mandi']]],
                                ['Bariis iyo digaag', 'Spiced rice with roast chicken', 40000, ['halal'], ['k' => 900, 'pm' => 20, 'ph' => ['chicken-mandi']]],
                                ['Baasto iyo suugo', 'Pasta with Somali beef sauce', 30000, ['halal'], ['al' => ['gluten'], 'k' => 790, 'pm' => 12, 'ph' => ['bolognese']]],
                                ['Suqaar', 'Beef cubes, potato and peppers', 36000, ['halal', 'spicy'], ['k' => 720, 'pm' => 15, 'ph' => ['spicy-beef-stew']]],
                                ['Maraq hilib', 'Meat soup with lemon and herbs', 18000, ['halal', 'gluten_free'], ['k' => 380, 'pm' => 10, 'ph' => ['meat-stew-pot']]],
                                ['Hilib ari suugo', 'Goat in a spiced sauce', 46000, ['halal'], ['k' => 780, 'pm' => 15, 'ph' => ['lamb-curry'], 'g' => ['meat']]],
                                ['Digaag suugo', 'Chicken in tomato and ginger sauce', 40000, ['halal', 'spicy'], ['k' => 720, 'pm' => 15, 'ph' => ['curry-copper']]],
                                ['Kalluun duban', 'Whole grilled kingfish, lemon', 46000, ['new'], ['al' => ['fish'], 'k' => 720, 'pm' => 20, 'ph' => ['fish-tilapia']]],
                                ['Kalluun iyo bariis', 'Fried fish with rice and salad', 44000, [], ['al' => ['fish'], 'k' => 880, 'pm' => 15, 'ph' => ['grill-fish-rice']]],
                                ['Kalluun suugo', 'Fish in a spiced coconut sauce', 42000, ['spicy'], ['al' => ['fish'], 'k' => 640, 'pm' => 15, 'ph' => ['fish-moqueca']]],
                                ['Digaag duban iyo bariis', 'Grilled chicken, rice, vegetables', 42000, ['halal'], ['k' => 920, 'pm' => 20, 'ph' => ['grilled-chicken-rice']]],
                                ['Hilib skewers', 'Beef skewers with peppers and lemon', 38000, ['halal'], ['k' => 740, 'pm' => 15, 'ph' => ['kebab-tray']]],
                                ['Family bariis platter', 'Rice, goat, chicken and sides for four', 180000, ['halal', 'popular'], ['k' => 4000, 'pm' => 25, 'ph' => ['arab-feast']]],
                            ]],
                            ['Cunto fudud · Snacks', null, [
                                ['Sambuusa', 'Crispy beef or lentil sambuusa', 9000, ['popular'], ['al' => ['gluten'], 'k' => 360, 'ph' => ['sambuusa'], 'sz' => [['3 pieces', 9000], ['6 pieces', 16000]]]],
                                ['Bajiye', 'Black-eyed pea fritters', 8000, ['vegan'], ['k' => 320, 'ph' => ['falafel-bowl2']]],
                                ['Digaag skewers', 'Chicken skewers, garlic butter', 22000, ['halal'], ['k' => 480, 'pm' => 10, 'ph' => ['chicken-skewers']]],
                                ['Shawarma', 'Beef shawarma wrap', 20000, ['halal'], ['al' => ['gluten', 'sesame'], 'k' => 640, 'ph' => ['shawarma-wraps']]],
                                ['Fries', null, 9000, ['vegan'], ['k' => 420, 'ph' => ['fries-ketchup']]],
                                ['Fruit salad', 'Papaya, mango, banana', 10000, ['vegan', 'gluten_free'], ['k' => 210, 'ph' => ['fruit-salad']]],
                            ]],
                            ['Cabitaan · Drinks', null, [
                                ['Shaah', 'Somali spiced tea with milk', 4000, ['vegetarian', 'popular'], ['al' => ['milk'], 'ph' => ['spiced-tea']]],
                                ['Shaah cadays', 'Milk tea with cardamom, extra milk', 5000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['chai-latte']]],
                                ['Qaxwo', 'Somali coffee with cardamom', 6000, ['vegan'], ['ph' => ['arabic-coffee']]],
                                ['Mango & papaya juice', null, 10000, ['vegan'], ['k' => 180, 'ph' => ['mango-juice']]],
                                ['Cambe iyo caano', 'Mango and milk shake', 12000, ['vegetarian'], ['al' => ['milk'], 'k' => 290, 'ph' => ['mango-shake']]],
                                ['Liin iyo naanac', 'Lemon and mint', 8000, ['vegan'], ['k' => 110, 'ph' => ['mint-lemonade']]],
                                ['Qaraha', 'Watermelon juice', 8000, ['vegan'], ['k' => 90, 'ph' => ['watermelon-juice']]],
                                ['Avocado juice', 'Avocado, mango, papaya in layers', 11000, ['vegan'], ['k' => 280, 'ph' => ['avocado-smoothie']]],
                                ['Strawberry juice', null, 10000, ['vegan'], ['k' => 120, 'ph' => ['strawberry-juice']]],
                            ]],
                            ['Macmacaan · Sweets', null, [
                                ['Baklava', 'Pistachio and syrup', 12000, ['vegetarian'], ['al' => ['gluten', 'nuts', 'milk'], 'k' => 420, 'ph' => ['baklava']]],
                                ['Chocolate cake', 'One slice', 10000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 480, 'ph' => ['chocolate-cake']]],
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
                                ['Cortado', 'Equal parts espresso and milk', 12000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['flat-white'], 'g' => ['milk']]],
                                ['Latte', 'Swan, heart or rosetta', 14000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['swan-latte'], 'g' => ['milk']]],
                                ['Oat latte', 'Oat milk, double shot', 15000, ['vegan'], ['ph' => ['pink-latte']]],
                                ['Café au lait', 'Filter coffee, hot milk', 11000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['black-mug']]],
                                ['Espresso flight', 'Two origins side by side', 18000, ['vegan'], ['ph' => ['portafilter']]],
                                ['Mocha', 'Espresso, chocolate, milk', 15000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['mocha'], 'g' => ['milk']]],
                                ['Iced latte', null, 15000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['iced-coffee'], 'g' => ['milk']]],
                                ['Large cappuccino', null, 15000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['cappuccino-2'], 'g' => ['milk']]],
                            ]],
                            ['Tea & more', null, [
                                ['Matcha latte', 'Ceremonial matcha', 15000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['matcha'], 'g' => ['milk']]],
                                ['Chai latte', 'Spiced black tea, milk', 13000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['chai-latte'], 'g' => ['milk']]],
                                ['Hot chocolate', 'Dark chocolate, cream', 14000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['hot-chocolate']]],
                                ['Green tea', 'Sencha, in a pot', 9000, ['vegan'], ['ph' => ['green-tea']]],
                                ['Iced lemon tea', 'Black tea, lemon', 10000, ['vegan'], ['ph' => ['iced-lemon-tea']]],
                            ]],
                            ['Pastry', 'Baked here every morning.', [
                                ['Butter croissant', null, 9000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 280, 'ph' => ['croissants']]],
                                ['Pain au chocolat', null, 11000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 320, 'ph' => ['pain-choc']]],
                                ['Cardamom bun', 'Cinnamon and cardamom', 10000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 300, 'ph' => ['cinnamon-rolls'], 'so' => true]],
                                ['Blueberry muffin', null, 9000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 380, 'ph' => ['muffins']]],
                                ['Cookies', 'Chocolate chip', 6000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 210, 'ph' => ['cookies']]],
                                ['Apple galette', 'Rustic, cinnamon', 11000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 380, 'ph' => ['apple-galette']]],
                                ['Éclair', 'Coffee or chocolate', 10000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 290, 'ph' => ['eclairs']]],
                                ['Cheesecake', 'Baked, berries', 13000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 450, 'ph' => ['cheesecake']]],
                                ['Tiramisu', 'Our espresso, mascarpone', 14000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 450, 'ph' => ['tiramisu']]],
                            ]],
                            ['Brunch', 'Until 14:00.', [
                                ['Avocado toast', 'Sourdough, lime, chili, egg', 24000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'k' => 480, 'pm' => 10, 'ph' => ['avocado-toast']]],
                                ['Poached eggs', 'Hollandaise and greens', 26000, ['vegetarian'], ['al' => ['eggs', 'milk'], 'k' => 520, 'pm' => 12, 'ph' => ['poached-eggs']]],
                                ['Granola', 'Yogurt, honey and berries', 18000, ['vegetarian'], ['al' => ['milk', 'nuts'], 'k' => 410, 'ph' => ['granola']]],
                                ['Pancakes', 'Berries, maple syrup', 22000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 560, 'pm' => 10, 'ph' => ['pancakes-berries']]],
                                ['Brunch plate', 'Eggs, toast, salad, fruit', 32000, ['vegetarian', 'popular'], ['al' => ['gluten', 'eggs'], 'k' => 780, 'pm' => 12, 'ph' => ['brunch']]],
                                ['Club sandwich', 'Chicken, egg, tomato, lettuce', 28000, [], ['al' => ['gluten', 'eggs'], 'k' => 720, 'pm' => 10, 'ph' => ['club-sandwich']]],
                                ['Smoothie bowl', 'Fruit, seeds, nuts', 22000, ['vegan'], ['al' => ['nuts'], 'k' => 420, 'ph' => ['smoothie-bowls']]],
                                ['Açaí bowl', 'Berries, granola, coconut', 24000, ['vegan'], ['al' => ['nuts'], 'k' => 440, 'ph' => ['acai']]],
                                ['Yogurt bowl', 'Fruit, honey, granola', 18000, ['vegetarian'], ['al' => ['milk', 'nuts'], 'k' => 380, 'ph' => ['yogurt-bowls']]],
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
                                ['Watermelon cooler', 'Watermelon and mint', 13000, ['vegan'], ['k' => 90, 'ph' => ['watermelon-juice']]],
                                ['Pineapple punch', 'Pineapple and ginger', 14000, ['vegan'], ['k' => 130, 'ph' => ['pineapple-juice']]],
                                ['Strawberry splash', 'Strawberry and lime', 15000, ['vegan'], ['k' => 120, 'ph' => ['strawberry-juice']]],
                                ['Juice jars to go', 'Three jars, mix your own', 36000, ['vegan'], ['ph' => ['juice-jars']]],
                                ['Pink lemonade', 'Strawberry and lemon', 12000, ['vegan'], ['k' => 130, 'ph' => ['strawberry-lemonade']]],
                                ['Mint lemonade', null, 11000, ['vegan'], ['k' => 110, 'ph' => ['mint-lemonade']]],
                            ]],
                            ['Smoothies & bowls', null, [
                                ['Berry blast', 'Strawberry, banana and yogurt', 19000, ['vegetarian'], ['al' => ['milk'], 'k' => 260, 'ph' => ['strawberry-smoothie']]],
                                ['Green glow', 'Spinach, apple and ginger', 18000, ['vegan', 'new'], ['k' => 170, 'ph' => ['smoothies']]],
                                ['Mango shake', 'Mango and milk', 17000, ['vegetarian'], ['al' => ['milk'], 'k' => 290, 'ph' => ['mango-shake']]],
                                ['Mango lassi', 'Mango, yogurt, cardamom', 17000, ['vegetarian'], ['al' => ['milk'], 'k' => 260, 'ph' => ['mango-lassi']]],
                                ['Açaí bowl', 'Berries, granola, coconut', 24000, ['vegan', 'popular'], ['al' => ['nuts'], 'k' => 440, 'ph' => ['acai']]],
                                ['Pink smoothie bowl', 'Dragon fruit, banana, chia', 22000, ['vegan'], ['k' => 380, 'ph' => ['acai-banana']]],
                                ['Rainbow bowls', 'Two bowls, all the fruit', 38000, ['vegan'], ['al' => ['nuts'], 'k' => 760, 'ph' => ['smoothie-bowls']]],
                            ]],
                            ['Ice cream', 'Made here, every day.', [
                                ['Rainbow cup', 'Five scoops, five colours, sprinkles', 26000, ['vegetarian', 'popular'], ['al' => ['milk'], 'k' => 640, 'ph' => ['ice-cream']]],
                                ['Ice cream bowl', 'Two scoops, chocolate sauce, cookie', 12000, ['vegetarian'], ['al' => ['milk', 'gluten'], 'k' => 320, 'ph' => ['ice-cream-bowls'], 'g' => ['flavour']]],
                                ['Strawberry sundae', 'Strawberries, cream, sprinkles', 22000, ['vegetarian'], ['al' => ['milk'], 'k' => 480, 'ph' => ['sundae-strawberry']]],
                                ['Chocolate sundae', 'Whipped cream and a cherry', 22000, ['vegetarian'], ['al' => ['milk'], 'k' => 520, 'ph' => ['sundae-choc']]],
                                ['Raspberry sundae', 'Two glasses to share', 36000, ['vegetarian'], ['al' => ['milk'], 'k' => 760, 'ph' => ['sundae-raspberry']]],
                                ['Banana split', 'Three scoops, cherries, sprinkles', 28000, ['vegetarian', 'popular'], ['al' => ['milk'], 'k' => 720, 'ph' => ['banana-split']]],
                                ['Nutty sundae', 'Caramel, nuts, chocolate', 24000, ['vegetarian'], ['al' => ['milk', 'nuts'], 'k' => 580, 'ph' => ['sundae-nuts']]],
                                ['Gelato & wafers', 'Chocolate and vanilla, crunchy wafers', 22000, ['vegetarian'], ['al' => ['milk', 'gluten'], 'k' => 520, 'ph' => ['gelato-waffle']]],
                                ['Freakshake', 'Chocolate shake with everything on top', 32000, ['vegetarian', 'new'], ['al' => ['milk', 'gluten'], 'k' => 980, 'ph' => ['freakshake']]],
                            ]],
                            ['Waffles & sweets', null, [
                                ['Waffle', 'Honey, cream and fruit', 20000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 520, 'pm' => 8, 'ph' => ['waffle']]],
                                ['Donuts', 'Three glazed donuts', 15000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 690, 'ph' => ['donuts'], 'so' => true]],
                                ['Cupcake', null, 9000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 340, 'ph' => ['cupcake']]],
                                ['Strawberry pancakes', 'Stack of three, cream', 22000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 620, 'pm' => 8, 'ph' => ['pancakes-strawberry']]],
                                ['Cinnamon roll', 'Cream cheese icing', 12000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 420, 'ph' => ['cinnamon-plate']]],
                            ]],
                            ['Snacks', null, [
                                ['Fruit salad', 'Seasonal fruit, lime and mint', 14000, ['vegan'], ['k' => 160, 'ph' => ['fruit-salad']]],
                                ['Fries', 'Salted, with ketchup', 11000, ['vegan'], ['k' => 410, 'ph' => ['fries-ketchup']]],
                                ['Mini pizzas', 'Four little pizzas', 22000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 620, 'pm' => 10, 'ph' => ['mini-pizzas']]],
                                ['Hot dog', 'Beef sausage, ketchup', 16000, [], ['al' => ['gluten'], 'k' => 520, 'pm' => 6, 'ph' => ['hot-dog']]],
                            ]],
                            ['Kids\' menu', 'For children under 10.', [
                                ['Mini juice', 'A small orange juice', 8000, ['vegan'], ['k' => 90, 'ph' => ['orange-ice']]],
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
                                ['Latte', null, 7500, ['vegetarian'], ['al' => ['milk'], 'ph' => ['latte'], 'g' => ['milk']]],
                                ['Flat white', null, 7500, ['vegetarian'], ['al' => ['milk'], 'ph' => ['flat-white'], 'g' => ['milk']]],
                                ['Mocha', 'Espresso, chocolate, milk', 9000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['mocha']]],
                                ['Iced coffee', null, 8500, ['vegetarian'], ['al' => ['milk'], 'ph' => ['iced-coffee'], 'g' => ['milk']]],
                                ['Cold brew', 'Over ice', 8000, ['vegan'], ['ph' => ['cold-brew']]],
                                ['Latte & biscotti', 'A latte with two almond biscotti', 11000, ['vegetarian'], ['al' => ['milk', 'gluten', 'nuts'], 'ph' => ['latte-biscotti']]],
                            ]],
                            ['Tea', null, [
                                ['Spiced tea', 'Cinnamon, clove and cardamom', 4000, ['vegan'], ['ph' => ['spiced-tea']]],
                                ['Ginger tea', 'Fresh ginger and honey', 4500, ['vegetarian', 'new'], ['ph' => ['ginger-tea']]],
                                ['Chai latte', 'Spiced tea with milk', 7000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['chai-latte']]],
                                ['Green tea', null, 4500, ['vegan'], ['ph' => ['green-tea']]],
                                ['Iced mint tea', null, 6000, ['vegan'], ['ph' => ['iced-mint-tea']]],
                                ['Hot chocolate', null, 8000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['hot-chocolate']]],
                            ]],
                            ['Breakfast', 'Until 11:00.', [
                                ['Ful', 'Fava beans, olive oil, tomato, warm bread', 15000, ['vegan'], ['al' => ['gluten'], 'k' => 480, 'pm' => 8, 'ph' => ['ful']]],
                                ['Eggs on toast', 'Two poached eggs, sourdough', 17000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'ph' => ['poached-eggs']]],
                                ['Eggs & tea', 'Fried eggs, bread and spiced tea', 14000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'ph' => ['eggs-tea']]],
                                ['Full breakfast', 'Eggs, beans, bread, salad', 24000, [], ['al' => ['gluten', 'eggs'], 'pm' => 10, 'ph' => ['full-breakfast']]],
                                ['Avocado toast', 'Lime, chili, egg', 19000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'ph' => ['avocado-toast']]],
                                ['Pancakes', 'With honey', 14000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'ph' => ['pancakes']]],
                                ['Brunch plate', 'Eggs, toast, salad, fruit', 26000, ['vegetarian', 'popular'], ['al' => ['gluten', 'eggs'], 'pm' => 10, 'ph' => ['brunch']]],
                                ['Granola', 'Yogurt, honey, fruit', 14000, ['vegetarian'], ['al' => ['milk', 'nuts'], 'ph' => ['granola']]],
                            ]],
                            ['Pastries & sweets', null, [
                                ['Butter croissant', null, 7000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'ph' => ['croissants']]],
                                ['Sambusa', 'Lentils and green chilli, three pieces', 6000, ['vegan', 'spicy'], ['al' => ['gluten'], 'ph' => ['sambuusa']]],
                                ['Chocolate cake', 'One slice', 11000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'ph' => ['chocolate-cake'], 'so' => true]],
                                ['Pain au chocolat', null, 8000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'ph' => ['pain-choc']]],
                                ['Cinnamon roll', null, 8000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'ph' => ['cinnamon-rolls']]],
                                ['Berry muffin', null, 7000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'ph' => ['berry-muffins']]],
                                ['Cookies', 'Two pieces', 5000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'ph' => ['cookies']]],
                                ['Donuts', 'Three glazed', 9000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'ph' => ['donuts']]],
                                ['Sweet rolls', 'Soft milk bread', 6000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'ph' => ['sweet-rolls']]],
                            ]],
                            ['Juice', null, [
                                ['Mango juice', null, 8000, ['vegan'], ['ph' => ['mango-juice']]],
                                ['Avocado spris', 'Avocado, mango, papaya', 9000, ['vegan', 'popular'], ['ph' => ['avocado-smoothie']]],
                                ['Orange juice', null, 7000, ['vegan'], ['ph' => ['orange-juice']]],
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
                            ['Tibs & wot', 'Sizzling in a clay pot.', [
                                ['Special tibs', 'Beef, rosemary, onion, green chilli', 42000, ['popular', 'spicy'], ['d' => 'Beef cubes seared over high heat with rosemary, onion and green chilli, served sizzling in a clay pot.', 'k' => 690, 'pm' => 15, 'ph' => ['tibs'], 'g' => ['heat', 'side']]],
                                ['Key wot', 'Beef in berbere sauce', 40000, ['spicy'], ['k' => 720, 'pm' => 15, 'ph' => ['beef-curry'], 'g' => ['heat', 'side']]],
                                ['Kitfo', 'Minced beef, mitmita, niter kibbeh', 45000, ['spicy'], ['al' => ['milk'], 'pm' => 12, 'ph' => ['raw-kebbe'], 'sz' => [['Kitfo', 45000], ['Special, with ayib and gomen', 52000]]]],
                            ], 'tibs'],
                            ['From the grill', 'Charcoal, every day from 11:00.', [
                                ['Charcoal steak', '300 g, grilled vegetables', 65000, [], ['k' => 820, 'pm' => 20, 'ph' => ['steak-veg'], 'g' => ['doneness', 'side']]],
                                ['Pepper steak', 'Black pepper sauce, fries', 68000, [], ['k' => 880, 'pm' => 20, 'ph' => ['steak-sauce'], 'g' => ['doneness']]],
                                ['Spicy wings', 'Berbere glaze, twelve pieces', 29000, ['spicy', 'popular'], ['k' => 760, 'pm' => 15, 'ph' => ['wings']]],
                                ['Half chicken', 'Lemon, garlic, chilli', 38000, [], ['k' => 680, 'pm' => 25, 'ph' => ['chicken-plate'], 'g' => ['side']]],
                                ['Lamb chops', 'Garlic, herbs, grilled tomato', 62000, ['gluten_free'], ['k' => 840, 'pm' => 20, 'ph' => ['lamb-chops'], 'g' => ['side']]],
                                ['Rack of lamb', 'For two, carved at the table', 118000, ['new'], ['k' => 1500, 'pm' => 30, 'ph' => ['lamb-rack-board'], 'g' => ['doneness', 'side']]],
                                ['Beef skewers & fries', 'Two skewers, salad', 46000, [], ['k' => 900, 'pm' => 18, 'ph' => ['beef-skewers-fries']]],
                                ['Mixed grill platter', 'Kebab, chicken, fries, pickles', 89000, ['popular'], ['k' => 1500, 'pm' => 25, 'ph' => ['mixed-grill']]],
                                ['Shashlik board', 'Beef shashlik, seasoned fries', 52000, [], ['k' => 980, 'pm' => 20, 'ph' => ['shashlik']]],
                                ['Seekh kebab', 'Spiced minced beef, two skewers', 36000, ['spicy'], ['k' => 620, 'pm' => 15, 'ph' => ['seekh'], 'g' => ['heat', 'side']]],
                                ['Grill selection', 'Lamb, beef and chicken, flatbread', 96000, [], ['al' => ['gluten'], 'k' => 1400, 'pm' => 25, 'ph' => ['grill-selection']]],
                                ['Kebab board', 'Beef kebab, flatbread, vegetables', 48000, [], ['al' => ['gluten'], 'k' => 880, 'pm' => 18, 'ph' => ['beef-kebab-board']]],
                                ['Chicken skewers', 'Garlic butter', 34000, [], ['k' => 480, 'pm' => 15, 'ph' => ['chicken-skewers'], 'g' => ['side']]],
                                ['Steak & fries', 'Sliced sirloin, fries, sauce', 58000, [], ['k' => 980, 'pm' => 20, 'ph' => ['steak-fries'], 'g' => ['doneness']]],
                                ['Grilled prawns', 'On the charcoal, lime', 62000, ['gluten_free'], ['al' => ['crustaceans'], 'k' => 380, 'pm' => 15, 'ph' => ['grill-seafood']]],
                                ['Chicken & rice', 'Grilled half chicken, saffron rice', 44000, [], ['k' => 980, 'pm' => 20, 'ph' => ['grilled-chicken-rice']]],
                                ['Meat platter', 'Kebabs, chicken and sauces for the table', 140000, [], ['k' => 2600, 'pm' => 25, 'ph' => ['meat-platter-sauces']]],
                                ['Beef fillet', '200 g, red wine sauce', 78000, [], ['k' => 640, 'pm' => 20, 'ph' => ['steak-micro'], 'g' => ['doneness', 'side']]],
                                ['Kebab & pita', 'Herbs, yogurt sauce', 42000, [], ['al' => ['gluten', 'milk'], 'k' => 760, 'pm' => 15, 'ph' => ['kebab-pita']]],
                                ['Lamb kebab flatbread', 'Salad, lemon, sauce', 40000, [], ['al' => ['gluten'], 'k' => 780, 'pm' => 15, 'ph' => ['kebab-flatbread']]],
                                ['Grill & bread board', 'Grilled meat, flatbread, salads', 88000, [], ['al' => ['gluten'], 'k' => 1600, 'pm' => 25, 'ph' => ['med-grill']]],
                            ]],
                            ['Burgers & dogs', null, [
                                ['Firewood burger', 'Double beef, smoked cheese, onion', 36000, ['popular'], ['al' => ['gluten', 'milk'], 'k' => 980, 'ph' => ['burger-board']]],
                                ['Bacon burger', null, 38000, [], ['al' => ['gluten', 'milk'], 'k' => 1040, 'ph' => ['burger-bacon'], 'so' => true]],
                                ['Cheeseburger', null, 32000, [], ['al' => ['gluten', 'milk'], 'k' => 880, 'ph' => ['cheeseburger']]],
                                ['Smash burger', 'Pickles, house sauce', 30000, [], ['al' => ['gluten', 'milk'], 'k' => 820, 'ph' => ['burger-tray']]],
                                ['Chili cheese dog', 'Beef chili, cheddar', 26000, ['spicy'], ['al' => ['gluten', 'milk'], 'k' => 780, 'ph' => ['chili-dog']]],
                                ['Grill dog & fries', null, 24000, [], ['al' => ['gluten'], 'k' => 790, 'ph' => ['hot-dog-fries']]],
                            ]],
                            ['Sides & drinks', null, [
                                ['Fries', null, 9000, ['vegan'], ['ph' => ['fries-bowl']]],
                                ['Garden salad', null, 9000, ['vegan', 'gluten_free'], ['ph' => ['garden-salad']]],
                                ['Cold soda', 'Cola, orange or lemon', 5000, ['vegan'], ['ph' => ['soda-glass']]],
                                ['Sparkling water', null, 4000, ['vegan'], ['ph' => ['sparkling-water']]],
                                ['Mint lemonade', null, 8000, ['vegan'], ['ph' => ['mint-lemonade']]],
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
                                ['Watermelon & mint', null, 7000, ['vegan'], ['k' => 90, 'ph' => ['watermelon-juice'], 'g' => ['size']]],
                                ['Pineapple', null, 8000, ['vegan'], ['k' => 130, 'ph' => ['pineapple-juice'], 'g' => ['size']]],
                                ['Strawberry & lime', null, 9000, ['vegan'], ['k' => 120, 'ph' => ['strawberry-juice']]],
                                ['Green smoothie', 'Spinach, banana, apple', 10000, ['vegan', 'new'], ['k' => 180, 'ph' => ['smoothies']]],
                            ], 'juices-table'],
                            ['Salads', null, [
                                ['Garden salad', 'Lettuce, tomato, cucumber, lemon', 14000, ['vegan', 'gluten_free'], ['k' => 180, 'ph' => ['garden-salad'], 'g' => ['extras']]],
                                ['Beet & citrus salad', 'Beetroot, orange, seeds', 16000, ['vegan', 'gluten_free', 'new'], ['k' => 220, 'ph' => ['beet-salad'], 'g' => ['extras']]],
                                ['Tabbouleh', 'Parsley, tomato, mint, bulgur', 13000, ['vegan'], ['al' => ['gluten'], 'k' => 220, 'ph' => ['tabbouleh']]],
                                ['Fattoush', 'Crisp bread, sumac, pomegranate', 14000, ['vegan'], ['al' => ['gluten'], 'k' => 260, 'ph' => ['fattoush']]],
                                ['Quinoa & fruit salad', 'Quinoa, cranberries, apple, carrot', 17000, ['vegan', 'gluten_free'], ['k' => 380, 'ph' => ['quinoa-fruit'], 'g' => ['extras']]],
                                ['Big salad bowl', 'Everything green, two dressings', 19000, ['vegan', 'gluten_free'], ['k' => 320, 'ph' => ['salad-bowls'], 'g' => ['extras']]],
                                ['Falafel salad', 'Falafel, greens, tahini', 16000, ['vegan'], ['al' => ['sesame'], 'k' => 460, 'ph' => ['falafel-salad']]],
                            ]],
                            ['Bowls', 'Warm or cold.', [
                                ['Chicken bowl', 'Grilled chicken, rice, greens', 22000, [], ['k' => 610, 'ph' => ['chicken-bowl']]],
                                ['Yogurt bowl', 'Fruit, honey, granola', 15000, ['vegetarian'], ['al' => ['milk', 'nuts'], 'k' => 420, 'ph' => ['yogurt-bowls']]],
                                ['Fruit salad', 'Papaya, mango, banana, passion fruit', 11000, ['vegan', 'gluten_free'], ['k' => 210, 'ph' => ['fruit-salad']]],
                                ['Buddha bowl', 'Greens, beets, seeds, citrus', 19000, ['vegan', 'gluten_free', 'popular'], ['k' => 420, 'ph' => ['buddha1'], 'g' => ['extras']]],
                                ['Roast vegetable bowl', 'Squash, eggplant, tahini', 19000, ['vegan', 'gluten_free'], ['al' => ['sesame'], 'k' => 460, 'ph' => ['buddha2'], 'g' => ['extras']]],
                                ['Tofu & chickpea bowl', 'Brussels sprouts, hemp seeds', 20000, ['vegan'], ['al' => ['soy'], 'k' => 480, 'ph' => ['buddha-tofu'], 'g' => ['extras']]],
                                ['Sweet potato bowl', 'Avocado, chickpeas, spinach, quinoa', 21000, ['vegan', 'gluten_free'], ['k' => 520, 'ph' => ['buddha-sweetpotato'], 'g' => ['extras']]],
                                ['Avocado & tofu bowl', 'Broccoli, tahini dressing', 21000, ['vegan'], ['al' => ['soy', 'sesame'], 'k' => 500, 'ph' => ['buddha-avocado'], 'g' => ['extras']]],
                                ['Squash & chickpea bowl', 'Greens, seeds', 19000, ['vegan', 'gluten_free'], ['k' => 460, 'ph' => ['buddha-squash'], 'g' => ['extras']]],
                                ['Kale & chickpea bowl', 'Lemon, carrots', 18000, ['vegan', 'gluten_free'], ['k' => 420, 'ph' => ['buddha-kale'], 'g' => ['extras']]],
                                ['Quinoa & pineapple bowl', 'Avocado, asparagus', 21000, ['vegan', 'gluten_free'], ['k' => 480, 'ph' => ['quinoa-pineapple'], 'g' => ['extras']]],
                                ['Quinoa & mushroom bowl', 'Pickled onion, roasted peppers', 20000, ['vegan', 'gluten_free'], ['k' => 460, 'ph' => ['quinoa-mushroom'], 'g' => ['extras']]],
                                ['Falafel bowl', 'Falafel, avocado, greens', 20000, ['vegan'], ['al' => ['sesame'], 'k' => 560, 'ph' => ['falafel-bowl']]],
                                ['Rice & beans bowl', 'Black beans, corn, sweet potato', 18000, ['vegan', 'gluten_free'], ['k' => 540, 'ph' => ['rice-beans-bowl'], 'g' => ['extras']]],
                                ['Açaí bowl', 'Berries, granola, coconut', 19000, ['vegan'], ['al' => ['nuts'], 'k' => 440, 'ph' => ['acai']]],
                                ['Pink smoothie bowl', 'Dragon fruit, banana, chia', 18000, ['vegan', 'gluten_free'], ['k' => 380, 'ph' => ['acai-banana']]],
                            ]],
                            ['Fasting dishes', 'Vegan, every day.', [
                                ['Beyaynetu', 'Eight dishes on injera', 19000, ['vegan', 'popular'], ['k' => 640, 'ph' => ['injera-platter']]],
                                ['Misir wot', 'Red lentils, berbere', 13000, ['vegan', 'spicy'], ['k' => 450, 'ph' => ['lentil-dish']]],
                                ['Ful', 'Fava beans, oil, tomato', 12000, ['vegan'], ['k' => 420, 'ph' => ['ful']]],
                                ['Lentil soup', 'With lemon and herbs', 10000, ['vegan'], ['k' => 260, 'ph' => ['lentil-soup']]],
                                ['Bean stew', 'Beans in tomato, warm bread', 13000, ['vegan'], ['al' => ['gluten'], 'k' => 480, 'ph' => ['bean-stew']]],
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
                                ['Fish steak & rice', 'Tuna, cumin, garlic, vegetables', 1100, ['gluten_free', 'spicy'], ['al' => ['fish'], 'pm' => 15, 'ph' => ['grill-fish-rice'], 'g' => ['sauce']]],
                                ['Fish curry', 'Coconut, tomato, chilli, in a clay pot', 1050, ['spicy'], ['al' => ['fish'], 'pm' => 20, 'ph' => ['fish-moqueca']]],
                                ['Grilled salmon', 'Couscous, roasted vegetables', 1450, [], ['al' => ['fish', 'gluten'], 'pm' => 20, 'ph' => ['salmon-plated']]],
                            ], 'fish-tilapia'],
                            ['Prawns & seafood', null, [
                                ['Prawn pasta', 'Garlic, tomato, chilli', 1050, ['spicy'], ['al' => ['gluten', 'crustaceans'], 'pm' => 18, 'ph' => ['shrimp-pasta']]],
                                ['Seafood pasta', 'Prawns, calamari, cream', 1150, [], ['al' => ['gluten', 'crustaceans', 'molluscs', 'milk'], 'pm' => 18, 'ph' => ['seafood-pasta']]],
                                ['Seafood spaghetti', 'Mussels, prawns, tomato', 1250, [], ['al' => ['gluten', 'crustaceans', 'molluscs'], 'pm' => 18, 'ph' => ['seafood-spaghetti']]],
                                ['Prawn & mussel linguine', 'Garlic, parsley, lemon', 1200, [], ['al' => ['gluten', 'crustaceans', 'molluscs'], 'pm' => 18, 'ph' => ['seafood-pasta2']]],
                                ['Fried calamari', 'Lemon and garlic sauce', 850, ['new'], ['al' => ['gluten', 'molluscs', 'eggs'], 'pm' => 12, 'ph' => ['calamari']]],
                                ['Calamari rings', 'Crispy, on a tray', 800, [], ['al' => ['gluten', 'molluscs', 'eggs'], 'pm' => 12, 'ph' => ['calamari-rings']]],
                                ['Spicy calamari', 'Red peppers, chilli', 900, ['spicy'], ['al' => ['molluscs'], 'pm' => 12, 'ph' => ['spicy-calamari']]],
                                ['Grilled calamari', 'Potatoes, herbs', 950, ['gluten_free'], ['al' => ['molluscs'], 'pm' => 15, 'ph' => ['grilled-calamari']]],
                                ['Squid stew', 'With white rice', 900, [], ['al' => ['molluscs'], 'pm' => 15, 'ph' => ['squid-stew']]],
                                ['Grilled prawns', 'Chilli, lime, dipping sauce', 1300, ['popular'], ['al' => ['crustaceans'], 'pm' => 15, 'ph' => ['grilled-shrimp'], 'g' => ['sauce']]],
                                ['Garlic prawns', 'Sizzling in the pan', 1350, [], ['al' => ['crustaceans'], 'pm' => 12, 'ph' => ['prawns-pan']]],
                                ['Spicy prawns', 'Grilled, served with lemon', 1300, ['spicy'], ['al' => ['crustaceans'], 'pm' => 15, 'ph' => ['grilled-shrimp2']]],
                                ['Lime prawns', 'Grilled, lime, lettuce', 1250, ['gluten_free'], ['al' => ['crustaceans'], 'pm' => 15, 'ph' => ['shrimp-lime']]],
                                ['Prawn skewers', 'From the charcoal grill', 1400, ['gluten_free'], ['al' => ['crustaceans'], 'pm' => 15, 'ph' => ['grill-seafood']]],
                                ['Prawns on ice', 'Three dipping sauces', 1400, ['gluten_free'], ['al' => ['crustaceans'], 'ph' => ['shrimp-ice']]],
                                ['Oysters', 'Six, lemon', 1600, ['gluten_free'], ['al' => ['molluscs'], 'ph' => ['oysters'], 'sz' => [['Six', 1600], ['Twelve', 3000]]]],
                                ['Octopus salad', 'Greens, tomato, lemon', 1150, ['gluten_free'], ['al' => ['molluscs'], 'pm' => 12, 'ph' => ['octopus-salad']]],
                                ['Prawn & avocado salad', null, 1100, ['gluten_free'], ['al' => ['crustaceans'], 'ph' => ['prawn-salad']]],
                                ['Seafood platter', 'Crab, prawns, calamari for two', 3200, ['popular'], ['al' => ['crustaceans', 'molluscs'], 'pm' => 25, 'ph' => ['seafood-platter']]],
                                ['Seafood boil', 'Crab, prawns, corn, bread', 3600, [], ['al' => ['crustaceans', 'gluten'], 'pm' => 25, 'ph' => ['seafood-boil']]],
                                ['Seafood hotplate', 'Sizzling prawns and fish', 2400, [], ['al' => ['crustaceans', 'fish'], 'pm' => 20, 'ph' => ['seafood-hotplate']]],
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
                                ['Pineapple', null, 250, ['vegan'], ['ph' => ['pineapple-juice']]],
                                ['Watermelon', null, 225, ['vegan'], ['ph' => ['watermelon-juice2']]],
                                ['Orange', null, 250, ['vegan'], ['ph' => ['orange-ice']]],
                                ['Tropical cooler', 'Passion fruit, cucumber, mint', 350, ['vegan'], ['ph' => ['tropical']]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-maison', 'name' => 'Maison Sheba', 'email' => 'maison@example.com', 'plan' => 'business', 'age' => 1,
                'style' => 'luxe', 'color' => '#7a2e3a', 'logo' => 'ring', 'banner' => null, 'banner_text' => 'under',
                'about' => "Ethiopian ingredients, French technique. A seven-table dining room with a short cocktail list.\nPlease book; dress smart casual.",
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
                                ['Lentil velouté', 'Red lentils, berbere butter', 72000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['red-lentil-soup']]],
                                ['Seared prawns', 'Citrus, saffron, pickled vegetables', 96000, ['gluten_free'], ['al' => ['crustaceans'], 'ph' => ['shrimp-appetizer']]],
                                ['Oysters', 'Six, mignonette', 120000, ['gluten_free'], ['al' => ['molluscs'], 'ph' => ['oysters']]],
                                ['Crispy croquette', 'Smoked ayib, microgreens', 78000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'ph' => ['fine-crispy']]],
                                ['Charred octopus', 'Tomato, lemon, greens', 104000, ['gluten_free'], ['al' => ['molluscs'], 'ph' => ['octopus-salad']]],
                                ['Crab & citrus', 'Lemon foam, herb purée', 99000, [], ['al' => ['crustaceans', 'eggs'], 'ph' => ['appetizer']]],
                                ['Chef\'s tasting bites', 'Four small plates', 110000, ['new'], ['al' => ['gluten', 'milk'], 'ph' => ['gourmet-marble']]],
                            ]],
                            ['Mains', 'With seasonal vegetables.', [
                                ['Lamb loin', 'Teff crumble, rosemary jus', 165000, ['popular'], ['d' => 'Lamb loin roasted pink, with a teff and herb crumble and a rosemary jus.', 'pm' => 25, 'ph' => ['fine-plated'], 'g' => ['doneness']]],
                                ['Beef fillet', 'Red wine, microgreens', 178000, [], ['pm' => 25, 'ph' => ['steak-micro'], 'g' => ['doneness']]],
                                ['Roast salmon', 'Couscous, roasted vegetables', 148000, [], ['al' => ['fish', 'gluten'], 'pm' => 20, 'ph' => ['salmon-plated']]],
                                ['Duck breast', 'Asparagus, radish, jus', 158000, ['gluten_free'], ['pm' => 25, 'ph' => ['duck-asparagus']]],
                                ['Rack of lamb', 'Green herb sauce, roasted roots', 172000, ['gluten_free'], ['pm' => 25, 'ph' => ['lamb-rack2'], 'g' => ['doneness']]],
                                ['Roast lamb', 'Crispy kale, polenta', 150000, ['gluten_free'], ['pm' => 25, 'ph' => ['roast-lamb']]],
                                ['Herb-crusted lamb chops', 'Vegetables, sauce', 162000, [], ['pm' => 25, 'ph' => ['lamb-crust'], 'g' => ['doneness']]],
                                ['Braised beef cheek', 'Carrots, green beans, red wine', 138000, ['gluten_free'], ['pm' => 20, 'ph' => ['meat-plated']]],
                                ['Beef sirloin', 'Garden vegetables', 156000, ['gluten_free'], ['pm' => 25, 'ph' => ['steak-veg'], 'g' => ['doneness']]],
                                ['Wild mushroom risotto', 'Parmesan, thyme', 118000, ['vegetarian', 'gluten_free'], ['al' => ['milk'], 'pm' => 22, 'ph' => ['risotto']]],
                                ['Ricotta ravioli', 'Brown butter, sage', 112000, ['vegetarian'], ['al' => ['gluten', 'milk', 'eggs'], 'pm' => 18, 'ph' => ['ravioli']]],
                            ]],
                            ['Desserts', null, [
                                ['Honey panna cotta', 'Tej, berries', 52000, ['vegetarian', 'gluten_free'], ['al' => ['milk'], 'ph' => ['panna-cotta']]],
                                ['Chocolate & coffee', 'Yirgacheffe, cocoa, cream', 58000, ['vegetarian'], ['al' => ['milk', 'eggs', 'gluten'], 'ph' => ['dessert-plate']]],
                                ['Tiramisu', 'Harar coffee, mascarpone', 56000, ['vegetarian'], ['al' => ['milk', 'eggs', 'gluten'], 'ph' => ['tiramisu']]],
                                ['Crème brûlée', 'Vanilla, mint', 52000, ['vegetarian', 'gluten_free'], ['al' => ['milk', 'eggs'], 'ph' => ['creme-brulee']]],
                                ['Cheesecake', 'Berry compote', 54000, ['vegetarian'], ['al' => ['milk', 'eggs', 'gluten'], 'ph' => ['cheesecake']]],
                                ['Éclairs', 'Coffee and chocolate', 48000, ['vegetarian'], ['al' => ['milk', 'eggs', 'gluten'], 'ph' => ['eclairs']]],
                            ], 'dessert-plate'],
                            ['Aperitifs & after dinner', null, [
                                ['Negroni', 'Gin, red bitter, vermouth', 62000, ['vegan'], ['ph' => ['negroni']]],
                                ['Clarified negroni', 'Crystal-clear, one big ice cube', 68000, ['vegan', 'new'], ['ph' => ['clear-cocktail']]],
                                ['Sunset layers', 'Orange, passion fruit, grenadine', 54000, ['vegan'], ['ph' => ['layered']]],
                                ['Mint mojito', 'Alcohol-free on request', 52000, ['vegan'], ['ph' => ['mojito']]],
                                ['Sparkling water', null, 12000, ['vegan'], ['ph' => ['sparkling-water']]],
                                ['Buna', 'Coffee roasted at the table', 36000, ['vegan'], ['ph' => ['ceremony']]],
                                ['Espresso', null, 18000, ['vegan'], ['ph' => ['espresso']]],
                                ['Macchiato', null, 18000, ['vegetarian'], ['al' => ['milk'], 'ph' => ['macchiato']]],
                                ['Fresh mint tea', null, 16000, ['vegan'], ['ph' => ['green-tea']]],
                            ]],
                        ]],
                    ],
                ],
            ],
            // R–T (owner, 2026-11-21, QUESTIONS #243): every dish its own photo, named after what it shows. R and T long menus;
            // S · Injera & Tibs a short traditional menu on purpose (owner: "only a few dishes").
            [
                'slug' => 'demo-bigbite', 'name' => 'Big Bite Burger & Pizza', 'email' => 'bigbite@example.com', 'plan' => 'plus', 'age' => 1,
                'style' => 'burger', 'color' => '#d7261e', 'logo' => 'slice', 'banner' => null, 'banner_text' => 'under',
                'about' => "Smashed burgers, crispy chicken and pizza from the stone oven.\nOpen late every day, families and football crowds welcome.",
                'address' => 'Bole Atlas, Addis Ababa', 'phone' => '+251 11 000 1501', 'hours' => [['10:30', '23:59']],
                'special' => ['Burger feast for two', 'Two burgers, onion rings, fries and two shakes — Br 1,450.', null, 'burger-feast'],
                'news' => ['Late night:', 'The kitchen is open until midnight every day.'],
                'catalogue' => [
                    'groups' => [
                        'extras' => ['Extras', 0, 4, [['Cheese', 3000], ['Beef bacon', 5000], ['Jalapeños', 2500], ['Fried egg', 3000]]],
                        'crust' => ['Crust', 1, 1, [['Classic', 0], ['Thin', 0], ['Stuffed crust', 6000]]],
                        'dip' => ['Dip', 0, 2, [['Garlic mayo', 1500], ['BBQ', 1500], ['Hot sauce', 1500]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Smash burgers', 'Fresh beef, smashed on the griddle, toasted potato bun.', [
                                ['Big Bite double', 'Two smashed patties, double cheddar, house sauce', 46000, ['popular'], ['d' => 'Two thin beef patties smashed on a hot griddle, double cheddar, pickles, onion and our house sauce in a toasted potato bun.', 'al' => ['gluten', 'milk', 'eggs'], 'k' => 1150, 'pm' => 10, 'ph' => ['double-cheese'], 'g' => ['extras', 'dip']]],
                                ['Double bacon smash', 'Two patties, beef bacon, melted cheese', 52000, ['new'], ['al' => ['gluten', 'milk'], 'k' => 1290, 'pm' => 10, 'ph' => ['double-bacon'], 'g' => ['extras']]],
                                ['Mushroom & onion double', 'Sautéed mushrooms, grilled onions, cheese', 49000, [], ['al' => ['gluten', 'milk'], 'k' => 1180, 'pm' => 10, 'ph' => ['burger-mushroom'], 'g' => ['extras']]],
                                ['Onion ring burger', 'Crispy onion rings, cheddar, BBQ sauce', 47000, [], ['al' => ['gluten', 'milk'], 'k' => 1210, 'pm' => 10, 'ph' => ['burger-onion-rings'], 'g' => ['extras']]],
                                ['Classic cheeseburger', 'Beef, cheddar, onion, ketchup', 36000, [], ['al' => ['gluten', 'milk'], 'k' => 880, 'pm' => 8, 'ph' => ['cheeseburger'], 'g' => ['extras']]],
                                ['Bacon cheeseburger', 'Beef bacon, cheddar, lettuce, tomato', 44000, [], ['al' => ['gluten', 'milk'], 'k' => 1090, 'pm' => 10, 'ph' => ['burger-bacon'], 'g' => ['extras']]],
                                ['Smokehouse burger', 'Smoked cheese, onion rings, BBQ sauce', 46000, [], ['al' => ['gluten', 'milk'], 'k' => 1120, 'pm' => 10, 'ph' => ['burger-board'], 'g' => ['extras']]],
                                ['Smash burger', 'One patty, pickles, house sauce', 32000, [], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 820, 'pm' => 8, 'ph' => ['burger-tray'], 'g' => ['extras']]],
                                ['Smash & fries', 'One smash burger with fries', 38000, [], ['al' => ['gluten', 'milk'], 'k' => 980, 'pm' => 10, 'ph' => ['burger-half']]],
                            ]],
                            ['Crispy chicken', null, [
                                ['Crispy chicken burger', 'Fried chicken thigh, lettuce, mayo', 40000, ['popular'], ['al' => ['gluten', 'eggs'], 'k' => 940, 'pm' => 12, 'ph' => ['chicken-burger'], 'g' => ['extras']]],
                                ['Hot chicken burger', 'Spicy fried chicken, fresh chillies', 42000, ['spicy'], ['al' => ['gluten', 'eggs'], 'k' => 960, 'pm' => 12, 'ph' => ['spicy-chicken-burger'], 'g' => ['extras']]],
                                ['Chicken deluxe', 'Crispy chicken, cheese, lettuce, tomato', 44000, [], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 1010, 'pm' => 12, 'ph' => ['chicken-burger-2'], 'g' => ['extras']]],
                                ['Chicken burger meal', 'Crispy chicken burger with fries', 48000, [], ['al' => ['gluten', 'milk', 'eggs'], 'k' => 1240, 'pm' => 12, 'ph' => ['chicken-burger-fries'], 'g' => ['dip']]],
                                ['Chicken mayo burger', 'Crispy chicken, garlic mayo, lettuce', 39000, [], ['al' => ['gluten', 'eggs'], 'k' => 900, 'pm' => 12, 'ph' => ['chicken-burger-plate']]],
                                ['Spicy wings', 'Crispy wings, hot glaze', 34000, ['spicy'], ['al' => ['gluten'], 'k' => 760, 'pm' => 14, 'ph' => ['wings'], 'g' => ['dip'], 'sz' => [['8 pieces', 34000], ['16 pieces', 60000]]]],
                                ['Nuggets & fries', 'Eight nuggets, fries, ketchup', 30000, [], ['al' => ['gluten'], 'k' => 820, 'pm' => 10, 'ph' => ['nuggets-fries'], 'g' => ['dip']]],
                                ['Chicken & fries', 'Three crispy pieces, fries, dip', 38000, [], ['al' => ['gluten'], 'k' => 990, 'pm' => 14, 'ph' => ['chicken-fries'], 'g' => ['dip']]],
                            ]],
                            ['Stone-oven pizza', 'Medium 30 cm or large 40 cm.', [
                                ['Margherita', 'Tomato, mozzarella, basil', 40000, ['vegetarian', 'popular'], ['al' => ['gluten', 'milk'], 'k' => 820, 'pm' => 12, 'ph' => ['margherita'], 'g' => ['crust'], 'sz' => [['Medium', 40000], ['Large', 56000]]]],
                                ['Pepperoni', 'Beef pepperoni, mozzarella, oregano', 50000, ['popular'], ['al' => ['gluten', 'milk'], 'k' => 980, 'pm' => 12, 'ph' => ['pepperoni-slice', 'pizza-checkered'], 'g' => ['crust'], 'sz' => [['Medium', 50000], ['Large', 66000]]]],
                                ['Salami & rocket', 'Beef salami, mushrooms, rocket', 54000, [], ['al' => ['gluten', 'milk'], 'k' => 960, 'pm' => 12, 'ph' => ['pizza-salami'], 'g' => ['crust'], 'sz' => [['Medium', 54000], ['Large', 70000]]]],
                                ['Ham & mushroom', 'Ham, mushrooms, mozzarella', 52000, [], ['al' => ['gluten', 'milk'], 'k' => 940, 'pm' => 12, 'ph' => ['pizza-ham'], 'g' => ['crust'], 'sz' => [['Medium', 52000], ['Large', 68000]]]],
                                ['Mushroom & pepper', 'Mushrooms, sweet peppers, oregano', 46000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 820, 'pm' => 12, 'ph' => ['pizza-mushroom'], 'g' => ['crust'], 'sz' => [['Medium', 46000], ['Large', 62000]]]],
                                ['Olive & pepper', 'Black olives, peppers, mozzarella', 45000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 830, 'pm' => 12, 'ph' => ['pizza-olive'], 'g' => ['crust'], 'sz' => [['Medium', 45000], ['Large', 61000]]]],
                                ['Pepper & onion', 'Three peppers, red onion, green chilli', 46000, ['vegetarian', 'spicy'], ['al' => ['gluten', 'milk'], 'k' => 810, 'pm' => 12, 'ph' => ['pizza-peppers'], 'g' => ['crust'], 'sz' => [['Medium', 46000], ['Large', 62000]]]],
                                ['Spinach & mushroom', 'Spinach, mushrooms, garlic', 46000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 790, 'pm' => 12, 'ph' => ['pizza-spinach'], 'g' => ['crust'], 'sz' => [['Medium', 46000], ['Large', 62000]]]],
                                ['Veggie supreme', 'Mushrooms, tomato, olives, red pepper', 48000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 840, 'pm' => 12, 'ph' => ['pizza-veg'], 'g' => ['crust'], 'sz' => [['Medium', 48000], ['Large', 64000]]]],
                                ['Pizza party', 'Three large pizzas of your choice', 160000, ['new'], ['al' => ['gluten', 'milk'], 'pm' => 15, 'ph' => ['pizzas-plates']]],
                                ['Mini pizzas', 'Four little pizzas for the kids', 28000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 620, 'pm' => 10, 'ph' => ['mini-pizzas']]],
                            ]],
                            ['Sides & snacks', null, [
                                ['Fries', 'With ketchup', 12000, ['vegan'], ['k' => 420, 'ph' => ['fries-ketchup'], 'g' => ['dip']]],
                                ['Cheesy fries', 'Cheese sauce, spring onion', 18000, ['vegetarian'], ['al' => ['milk'], 'k' => 640, 'ph' => ['fries-bowl']]],
                                ['Onion rings', 'Basket with three dips', 16000, ['vegetarian', 'popular'], ['al' => ['gluten'], 'k' => 520, 'ph' => ['onion-rings']]],
                                ['Panko onion rings', 'Extra crunchy, garlic dip', 17000, ['vegetarian'], ['al' => ['gluten', 'eggs'], 'k' => 560, 'ph' => ['onion-rings-3']]],
                                ['Mozzarella sticks', 'Six sticks, tomato dip', 22000, ['vegetarian'], ['al' => ['gluten', 'milk'], 'k' => 620, 'ph' => ['mozzarella-sticks']]],
                                ['Snack platter', 'Fries, onion rings and nuggets to share', 42000, [], ['al' => ['gluten'], 'k' => 1400, 'ph' => ['snack-plate']]],
                                ['Hot dog & fries', 'Beef sausage, crispy onions, fries', 28000, [], ['al' => ['gluten'], 'k' => 790, 'ph' => ['hot-dog-fries']]],
                                ['Chili cheese dog', 'Beef chili, cheddar', 30000, ['spicy'], ['al' => ['gluten', 'milk'], 'k' => 780, 'ph' => ['chili-dog']]],
                            ]],
                            ['Shakes & drinks', null, [
                                ['Chocolate shake', 'Whipped cream, chocolate sauce', 18000, ['vegetarian', 'popular'], ['al' => ['milk'], 'k' => 720, 'ph' => ['choc-shake']]],
                                ['Cookies & cream shake', 'Chocolate, crushed cookies', 20000, ['vegetarian'], ['al' => ['milk', 'gluten'], 'k' => 780, 'ph' => ['choc-shake-2']]],
                                ['Strawberry shake', 'Fresh strawberries, cream', 18000, ['vegetarian'], ['al' => ['milk'], 'k' => 640, 'ph' => ['strawberry-shake']]],
                                ['Freakshake', 'Chocolate shake with every topping', 26000, ['vegetarian', 'new'], ['al' => ['milk', 'gluten'], 'k' => 980, 'ph' => ['freakshake']]],
                                ['Mango shake', 'Ripe mango, milk', 16000, ['vegetarian'], ['al' => ['milk'], 'k' => 420, 'ph' => ['mango-shake']]],
                                ['Cola', null, 6000, ['vegan'], ['ph' => ['soda-glass'], 'sz' => [['Small', 6000], ['Large', 9000]]]],
                                ['Mint lemonade', null, 9000, ['vegan'], ['ph' => ['mint-lemonade']]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-shekla', 'name' => 'Shekla House', 'email' => 'shekla@example.com', 'plan' => 'plus', 'age' => 1,
                'style' => 'injera', 'color' => '#a8321f', 'logo' => 'drop', 'banner' => null, 'banner_text' => 'under',
                'about' => "Injera baked every morning on the mitad, tibs sizzling in the clay shekla.\nFasting dishes every day, and a coffee ceremony after every meal.",
                'address' => 'Arat Kilo, Addis Ababa', 'phone' => '+251 11 000 1601', 'hours' => [['07:00', '22:30']],
                'special' => ['Today: shekla tibs', 'Beef tibs sizzling in the clay pot, with injera and awaze.', 'Shekla tibs · ሸክላ ጥብስ', 'tibs'],
                'news' => ['Fasting days:', 'The full fasting menu is on every Wednesday and Friday.'],
                'catalogue' => [
                    'groups' => [
                        'spice' => ['How spicy?', 1, 1, [['Mild', 0], ['Medium', 0], ['Hot (awaze)', 0]]],
                        'side' => ['With', 0, 1, [['Extra injera', 2000], ['Bread', 1500], ['Rice', 3500]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Tibs & kitfo · ጥብስ', 'Sizzling, with injera.', [
                                ['Shekla tibs · ሸክላ ጥብስ', 'Beef, rosemary, onion, green chilli, in the clay pot', 48000, ['popular', 'spicy'], ['d' => 'Beef cubes seared with rosemary, onion and green chilli, brought to the table still sizzling in the clay shekla, with injera and awaze.', 'k' => 860, 'pm' => 20, 'ph' => ['tibs'], 'g' => ['spice', 'side']]],
                                ['Doro tibs combo', 'Chicken tibs, kik alicha, gomen, injera rolls', 42000, ['new'], ['k' => 820, 'pm' => 18, 'ph' => ['injera-stews'], 'g' => ['spice']]],
                                ['Kitfo · ክትፎ', 'Minced beef, mitmita, niter kibbeh, ayib', 46000, ['spicy'], ['al' => ['milk'], 'k' => 820, 'pm' => 15, 'ph' => ['raw-kebbe'], 'g' => ['spice'], 'sz' => [['Raw', 46000], ['Leb leb (lightly warmed)', 46000], ['Cooked', 46000]]]],
                                ['Kurt · ቁርጥ', 'Fresh raw beef cuts, awaze, mitmita, bread', 62000, [], ['k' => 700, 'pm' => 10, 'ph' => ['kurt-platter'], 'sz' => [['For one', 62000], ['For the table', 150000]]]],
                            ]],
                            ['Wot & beyaynetu · ወጥ', 'Stews, served on injera.', [
                                ['Beyaynetu · በያይነቱ', 'Fasting stews on one injera', 28000, ['vegan', 'popular'], ['k' => 640, 'pm' => 15, 'ph' => ['injera-platter'], 'sz' => [['For one', 28000], ['For two', 50000]]]],
                                ['Family platter', 'Meat and fasting dishes for the table', 120000, [], ['k' => 2200, 'pm' => 20, 'ph' => ['injera-dishes']]],
                                ['Key wot', 'Beef in a rich berbere sauce', 40000, ['spicy'], ['k' => 760, 'pm' => 20, 'ph' => ['beef-curry'], 'g' => ['spice', 'side']]],
                                ['Misir wot', 'Red lentils, berbere, garlic', 20000, ['vegan', 'spicy'], ['k' => 480, 'pm' => 15, 'ph' => ['lentil-dish'], 'g' => ['spice']]],
                            ]],
                            ['Breakfast · ቁርስ', 'Until 11:00.', [
                                ['Enkulal firfir', 'Scrambled eggs with berbere and spices, bread', 16000, ['vegetarian'], ['al' => ['eggs', 'gluten'], 'k' => 420, 'pm' => 8, 'ph' => ['scrambled-spices']]],
                                ['Ful', 'Fava beans, oil, tomato, warm bread', 15000, ['vegan'], ['al' => ['gluten'], 'k' => 480, 'pm' => 8, 'ph' => ['ful']]],
                            ]],
                            ['Coffee & spris · ቡና', null, [
                                ['Coffee ceremony · ቡና', 'Three rounds from the jebena, popcorn and incense', 18000, ['vegan', 'popular'], ['ph' => ['ceremony', 'ceremony-2'], 'sz' => [['For one', 18000], ['For the table', 45000]]]],
                                ['Avocado & mango spris', 'Avocado and mango in layers', 12000, ['vegan'], ['k' => 300, 'ph' => ['mango-avocado']]],
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'demo-spris', 'name' => 'Spris Juice House', 'email' => 'spris@example.com', 'plan' => 'plus', 'age' => 1,
                'style' => 'juice', 'color' => '#2f8f3a', 'logo' => 'drop', 'banner' => null, 'banner_text' => 'under',
                'about' => "Juice pressed to order: avocado, mango, papaya and our layered spris.\nNo sugar added unless you ask for it.",
                'address' => 'Piassa, Addis Ababa', 'phone' => '+251 11 000 1701', 'hours' => [['07:30', '22:00']],
                'special' => ['Spris of the day', 'Strawberry, mango and avocado in layers — Br 150 until noon.', 'Rainbow spris', 'layered-smoothie'],
                'news' => ['Fresh today:', 'Papaya from Arba Minch and mangoes from Harar.'],
                'catalogue' => [
                    'groups' => [
                        'sweet' => ['Sugar', 1, 1, [['No sugar', 0], ['A little', 0], ['Sweet', 0]]],
                        'extra' => ['Extras', 0, 3, [['Honey', 1500], ['Ginger', 1000], ['Chia seeds', 2000], ['Lime', 500]]],
                    ],
                    'menus' => [
                        ['name' => 'Menu', 'slug' => 'menu', 'categories' => [
                            ['Spris & layers', 'Our famous layered juices.', [
                                ['Rainbow spris', 'Strawberry, mango and avocado in layers', 15000, ['vegan', 'popular'], ['d' => 'Three fruits blended one by one and poured in layers: strawberry, mango and avocado, topped with fresh fruit.', 'k' => 340, 'pm' => 6, 'ph' => ['layered-smoothie'], 'g' => ['sweet', 'extra']]],
                                ['Avocado & mango spris', 'Avocado, mango, a squeeze of lime', 14000, ['vegan', 'popular'], ['k' => 320, 'pm' => 5, 'ph' => ['mango-avocado'], 'g' => ['sweet', 'extra']]],
                                ['Sunset layers', 'Orange, passion fruit, grenadine', 13000, ['vegan'], ['k' => 210, 'pm' => 5, 'ph' => ['layered'], 'g' => ['sweet']]],
                                ['Avocado juice', 'Thick, with lime and a little honey', 12000, ['vegetarian'], ['k' => 300, 'pm' => 5, 'ph' => ['avocado-smoothie'], 'g' => ['sweet', 'extra']]],
                            ]],
                            ['Fresh juices', 'Pressed when you order.', [
                                ['Mango juice', 'Ripe Harar mango', 11000, ['vegan', 'popular'], ['k' => 190, 'ph' => ['mango-juice'], 'g' => ['sweet', 'extra'], 'sz' => [['Glass', 11000], ['Jug', 30000]]]],
                                ['Papaya juice', 'Ripe papaya, a squeeze of lime', 11000, ['vegan'], ['k' => 150, 'ph' => ['papaya-juice'], 'g' => ['sweet', 'extra']]],
                                ['Tropical jar', 'Papaya, mango and berries', 13000, ['vegan'], ['k' => 210, 'ph' => ['papaya-jar'], 'g' => ['sweet']]],
                                ['Orange juice', null, 10000, ['vegan'], ['k' => 140, 'ph' => ['orange-juice'], 'g' => ['extra']]],
                                ['Pineapple juice', null, 10000, ['vegan'], ['k' => 150, 'ph' => ['pineapple-juice'], 'g' => ['sweet', 'extra']]],
                                ['Watermelon juice', null, 9000, ['vegan'], ['k' => 90, 'ph' => ['watermelon-juice'], 'g' => ['extra']]],
                                ['Watermelon & mint', 'With fresh mint leaves', 10000, ['vegan'], ['k' => 95, 'ph' => ['watermelon-juice2']]],
                                ['Strawberry juice', null, 12000, ['vegan'], ['k' => 120, 'ph' => ['strawberry-juice'], 'g' => ['sweet']]],
                                ['Kiwi juice', null, 12000, ['vegan', 'new'], ['k' => 130, 'ph' => ['kiwi-juice'], 'g' => ['sweet']]],
                                ['Carrot & orange', 'With a cherry on top', 11000, ['vegan'], ['k' => 120, 'ph' => ['carrot-juice'], 'g' => ['extra']]],
                                ['Carrot, orange & ginger', 'Wakes you up', 11000, ['vegan'], ['k' => 125, 'ph' => ['carrot-orange']]],
                                ['Juice of the day', 'Ask what came in fresh this morning', 10000, ['vegan'], ['ph' => ['juices-glasses']]],
                            ]],
                            ['Smoothies & shakes', null, [
                                ['Strawberry smoothie', 'Strawberries, yogurt, honey', 14000, ['vegetarian'], ['al' => ['milk'], 'k' => 260, 'ph' => ['strawberry-smoothie'], 'g' => ['extra']]],
                                ['Strawberry banana', 'Strawberries, banana, milk', 14000, ['vegetarian'], ['al' => ['milk'], 'k' => 290, 'ph' => ['strawberry-smoothie-2']]],
                                ['Pink strawberry', 'Strawberries, yogurt, ice', 13000, ['vegetarian'], ['al' => ['milk'], 'k' => 240, 'ph' => ['strawberry-smoothie-3']]],
                                ['Berry smoothie', 'Raspberries, blackberries, yogurt', 16000, ['vegetarian'], ['al' => ['milk'], 'k' => 260, 'ph' => ['berry-smoothie']]],
                                ['Smoothie trio', 'Kiwi, blackberry and strawberry', 32000, ['vegetarian', 'new'], ['al' => ['milk'], 'k' => 600, 'ph' => ['fruit-smoothies']]],
                                ['Garden smoothie', 'Carrot, apple, kiwi, lemon', 14000, ['vegan'], ['k' => 210, 'ph' => ['veg-smoothie']]],
                                ['Mango lassi', 'Mango, yogurt, cardamom', 14000, ['vegetarian'], ['al' => ['milk'], 'k' => 280, 'ph' => ['mango-lassi']]],
                                ['Mango shake', 'Ripe mango, milk', 15000, ['vegetarian'], ['al' => ['milk'], 'k' => 420, 'ph' => ['mango-shake']]],
                                ['Strawberry milkshake', 'Strawberries, milk, cream', 16000, ['vegetarian'], ['al' => ['milk'], 'k' => 520, 'ph' => ['strawberry-shake-2']]],
                            ]],
                            ['Lemonade & iced tea', null, [
                                ['Mint lemonade', 'Lemon, mint, a little sugar', 9000, ['vegan', 'popular'], ['ph' => ['mint-lemonade']]],
                                ['Strawberry lemonade', null, 11000, ['vegan'], ['ph' => ['strawberry-lemonade']]],
                                ['Iced lemon tea', null, 8000, ['vegan'], ['ph' => ['iced-lemon-tea']]],
                                ['Iced mint tea', null, 8000, ['vegan'], ['ph' => ['iced-mint-tea']]],
                                ['Berry mocktail', 'Berries, lime, soda', 14000, ['vegan'], ['ph' => ['berry-mocktail']]],
                            ]],
                            ['Bowls & fruit', null, [
                                ['Açaí bowl', 'Açaí, banana, granola, berries', 22000, ['vegan'], ['al' => ['gluten'], 'k' => 420, 'ph' => ['acai']]],
                                ['Smoothie bowl', 'Mango and berry, fruit on top', 20000, ['vegan'], ['k' => 380, 'ph' => ['smoothie-bowls']]],
                                ['Fruit salad', 'Papaya, mango, banana, passion fruit', 12000, ['vegan', 'gluten_free'], ['k' => 210, 'ph' => ['fruit-salad']]],
                                ['Yogurt bowl', 'Yogurt, granola, honey, fruit', 16000, ['vegetarian'], ['al' => ['milk', 'gluten'], 'k' => 360, 'ph' => ['yogurt-bowls']]],
                            ]],
                            ['To take away', null, [
                                ['Bottled juices', 'Any juice in a bottle to go', 12000, ['vegan'], ['ph' => ['juice-bottles']]],
                                ['Juice for the table', 'A glass of each fresh juice, for four', 40000, ['vegan'], ['ph' => ['juices-pool']]],
                            ]],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
