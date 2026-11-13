<?php

namespace Database\Seeders\Demo;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\OpeningHour;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlatformAnnouncement;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;

/**
 * Everything the Super Admin dashboard needs to look alive: plans, about 30
 * small generated restaurants in every state (trial, active, expiring soon,
 * expired, suspended, inactive), 12 months of payments, sign-ups spread over
 * the year, plan upgrades/downgrades and a platform announcement.
 * All names, e-mails (example.com) and phone numbers are fictional.
 */
class Platform
{
    /**
     * [name, kind, state, created days ago (0 = trial just ended, first payment this month), plan, setup (0–6 steps), visits per day]
     *
     * @return list<array{string, string, string, int, string, int, int}>
     */
    private static function small(): array
    {
        return [
            ['Abay Corner Café', 'cafe', 'trial', 3, 'trial', 2, 0],
            ['Tana Breeze Kitchen', 'ethiopian', 'trial', 6, 'trial', 4, 9],
            ['Little Merkato Bites', 'ethiopian', 'trial', 9, 'trial', 6, 14],
            ['Copper Kettle Roasters', 'cafe', 'trial', 12, 'trial', 5, 11],
            ['Saffron Spoon', 'grill', 'trial', 2, 'trial', 1, 0],
            ['Green Leaf Juice Stop', 'juice', 'trial', 11, 'trial', 3, 7],
            ['Hilltop Pizza Shack', 'pizza', 'expiring', 75, 'plus', 6, 24],
            ['Morning Dew Bakery', 'cafe', 'expiring', 160, 'pro', 6, 38],
            ['Riverbend Grill House', 'grill', 'expiring', 220, 'plus', 6, 21],
            ['Sunny Side Diner', 'cafe', 'expiring', 50, 'plus', 6, 17],
            ['Blue Door Bistro', 'grill', 'expiring', 300, 'pro', 6, 45],
            ['Golden Crust Pizzeria', 'pizza', 'active', 330, 'pro', 6, 62],
            ['Olive & Thyme', 'grill', 'active', 290, 'business', 6, 80],
            ['Nine Wells Coffee', 'cafe', 'active', 250, 'plus', 6, 28],
            ['Habesha Table', 'ethiopian', 'active', 210, 'pro', 6, 55],
            ['Kiwi & Lime Juice Bar', 'juice', 'active', 180, 'plus', 6, 19],
            ['Firewood Kitchen', 'grill', 'active', 140, 'business', 6, 70],
            ['Lemon Grove Café', 'cafe', 'active', 110, 'pro', 6, 33],
            ['Spice Route Ethiopian', 'ethiopian', 'active', 80, 'plus', 6, 26],
            ['Stone Oven Slices', 'pizza', 'active', 0, 'plus', 6, 22],
            ['Garden Terrace', 'grill', 'active', 0, 'pro', 6, 30],
            ['Mango Tree Juices', 'juice', 'expired', 200, 'plus', 6, 12],
            ['Corner Shiro House', 'ethiopian', 'expired', 130, 'plus', 6, 9],
            ['Quick Bite Express', 'pizza', 'expired', 95, 'plus', 5, 8],
            ['Night Owl Lounge', 'grill', 'suspended', 260, 'pro', 6, 15],
            ['Velvet Cup', 'cafe', 'suspended', 120, 'plus', 6, 10],
            ['Old Town Teahouse', 'cafe', 'inactive', 240, 'plus', 6, 14],
            ['Meadow Kitchen', 'grill', 'inactive', 190, 'plus', 4, 8],
            ['Berbere & Bread', 'ethiopian', 'inactive', 150, 'pro', 6, 18],
            ['Pocket Pizza', 'pizza', 'inactive', 100, 'plus', 6, 11],
        ];
    }

    /**
     * Dish lists per kind: [name, price, photo key or null].
     *
     * @var array<string, list<array{string, int, string|null}>>
     */
    private const DISHES = [
        'cafe' => [['Macchiato', 7000, 'macchiato'], ['Cappuccino', 9500, 'cappuccino'], ['Buna', 6000, null], ['Croissant', 8000, 'croissants'], ['Chocolate cake', 12000, 'chocolate-cake'], ['Avocado toast', 19000, null], ['Pancakes', 18000, null], ['Fresh juice', 10000, null], ['Club sandwich', 28000, null], ['Shai', 4500, null], ['Cheesecake', 13000, null], ['Iced coffee', 11000, null]],
        'ethiopian' => [['Beyaynetu', 26000, 'injera-platter'], ['Tibs', 34000, 'tibs'], ['Shiro', 20000, null], ['Doro wat', 40000, null], ['Kitfo', 42000, null], ['Misir wat', 18000, null], ['Gomen', 16000, null], ['Firfir', 15000, null], ['Buna', 6000, null], ['Extra injera', 2500, null]],
        'pizza' => [['Margherita', 40000, 'pizzas-plates'], ['Pepperoni', 50000, 'pepperoni-slice'], ['Veggie pizza', 44000, null], ['Cheeseburger', 34000, 'cheeseburger'], ['Fries', 12000, 'fries-ketchup'], ['Chicken wings', 34000, null], ['Cola', 6000, null], ['Milkshake', 14000, null]],
        'grill' => [['Grilled steak', 95000, 'steak-fries'], ['Chicken plate', 42000, null], ['Burger', 36000, 'burger-board'], ['Garden salad', 18000, 'garden-salad'], ['Spaghetti bolognese', 32000, null], ['Fries', 12000, null], ['Fruit salad', 12000, null], ['Lemonade', 8000, null]],
        'juice' => [['Avocado spris', 12000, 'avocado-smoothie'], ['Mango juice', 10000, 'mango-juice'], ['Orange juice', 9500, null], ['Strawberry smoothie', 12000, null], ['Papaya juice', 9500, null], ['Fruit salad', 11000, null], ['Granola bowl', 16000, null]],
    ];

    /** The demo restaurant on style D (/demo-nine-wells-coffee). */
    public const MINIMAL = 'Nine Wells Coffee';

    public function __construct(
        private readonly CurrentTenant $current,
        private readonly Photos $photos,
        private readonly Insights $insights,
        private readonly MenuBuilder $menus,
    ) {}

    /** @return array<string, Plan> by slug */
    public function plans(): array
    {
        // Free, Plus, Pro and Business come from the plan-ladder migration (QUESTIONS #184).
        $plans = Plan::whereIn('slug', ['free', 'plus', 'pro', 'business'])->get()->keyBy('slug')->all();
        // Trials get everything (like Business) so new restaurants can try it all; not on the website.
        $plans['trial'] = Plan::firstOrCreate(['slug' => 'trial'], [
            'name' => ['en' => 'Trial'], 'price_minor' => 0, 'currency' => 'ETB', 'interval' => 'month',
            'trial_days' => 14, 'limits' => [], 'sort' => 9, 'is_public' => false,
        ]);

        return $plans;
    }

    /**
     * Payments for the café and the showcase restaurants (monthly, paid on time).
     *
     * @param  list<Tenant>  $tenants
     */
    public function payFor(array $tenants, User $admin): void
    {
        foreach ($tenants as $i => $tenant) {
            $this->payments($tenant, $tenant->plan, self::renewsNextMonth($i), $admin);
        }
    }

    /**
     * @param  array<string, Plan>  $plans
     * @return list<Tenant>
     */
    public function smallRestaurants(array $plans, User $admin): array
    {
        $tenants = [];
        mt_srand(2026);
        foreach (self::small() as $n => [$name, $kind, $state, $age, $planSlug, $setup, $visits]) {
            $slug = 'demo-'.str($name)->slug();
            $age = $age ?: 14 + max(1, intdiv(now()->day, 2));
            $created = CarbonImmutable::now()->subDays($age)->setTime(mt_rand(8, 19), mt_rand(0, 59));
            $plan = $plans[$planSlug];
            $tenant = Tenant::create(['slug' => $slug, 'name' => $name, 'plan_id' => $plan->id, 'timezone' => 'Africa/Addis_Ababa', 'default_locale' => 'en', 'currency_code' => 'ETB']);
            $tenant->forceFill([
                'created_at' => $created, 'updated_at' => $created,
                'trial_ends_at' => $created->addDays(14),
                'qr_downloaded_at' => $setup >= 6 ? $created->addDays(3) : null,
            ])->save();
            $user = User::firstOrCreate(['email' => "owner.{$slug}@example.com"], ['name' => 'Demo Owner · '.$name, 'password' => 'password']);
            $user->forceFill(['email_verified_at' => $created])->save();
            Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => Role::Owner]);
            $this->log($tenant, $user, 'tenant.created', $created);

            $this->current->runAs($tenant, fn () => $this->setUp($tenant, $kind, $setup));

            // Subscription dates by state.
            $now = CarbonImmutable::now();
            $paidUntil = match ($state) {
                'trial' => null,
                'expiring' => $now->addDays([3, 5, 6, 10, 13][$n % 5]),
                'expired' => $now->subDays([3, 12, 26][$n % 3]),
                default => self::renewsNextMonth($n),
            };
            if ($paidUntil) {
                $this->payments($tenant, $plan, $paidUntil, $admin);
            }
            if ($state === 'suspended') {
                $tenant->forceFill(['status' => Tenant::STATUS_SUSPENDED, 'suspended_at' => $now->subDays(9), 'suspended_reason' => 'Unpaid invoice (demo).'])->save();
                $this->log($tenant, $admin, 'platform.restaurant_suspended', $now->subDays(9), null, true);
            }

            // Visits and edits: inactive restaurants went quiet 20–60 days ago.
            $quietSince = $state === 'inactive' ? $now->subDays(20 + ($n * 11) % 40) : null;
            if ($visits > 0) {
                $this->insights->seed($tenant, 90, $visits, $quietSince, false);
            }
            $lastEdit = $quietSince ?? $now->subDays(mt_rand(0, 6));
            foreach (range(0, 3) as $k) {
                $at = $lastEdit->subDays($k * mt_rand(3, 12));
                if ($at->gt($created)) {
                    $this->log($tenant, $user, 'item.updated', $at);
                }
            }
            $tenants[] = $tenant;
        }

        $this->planChanges($tenants, $plans, $admin);

        return $tenants;
    }

    /** A news post on every restaurant's dashboard. */
    public function announcement(User $admin): void
    {
        PlatformAnnouncement::create([
            'title' => ['en' => 'New: "My order" lists and printable PDF menus'],
            'body' => ['en' => 'Guests can now build a list to show the waiter, and you can download a print-ready menu from the menu editor.'],
            'level' => 'info', 'starts_at' => now()->subDays(2), 'ends_at' => now()->addDays(28), 'created_by' => $admin->id,
        ]);
    }

    /** A small menu, and as many setup steps as the restaurant has done (logo, banner, dishes, photos, hours, QR). */
    private function setUp(Tenant $tenant, string $kind, int $setup): void
    {
        $dishes = self::DISHES[$kind];
        $count = $setup >= 3 ? count($dishes) : 3;
        $photos = $setup >= 4;
        $rows = array_map(fn (array $d) => [$d[0], null, $d[1], [], ['ph' => $photos && $d[2] ? [$d[2]] : []]], array_slice($dishes, 0, $count));
        $this->menus->build($tenant, ['menus' => [['name' => 'Menu', 'slug' => 'menu', 'categories' => [['Menu', null, $rows]]]]]);

        $colors = ['cafe' => '#8a4b2a', 'ethiopian' => '#a3361f', 'pizza' => '#b3261e', 'grill' => '#5b3a29', 'juice' => '#2e6b47'];
        $banner = ['cafe' => 'cafe-bali', 'ethiopian' => 'injera-dishes', 'pizza' => 'pizzas-plates', 'grill' => 'cafe-tables', 'juice' => 'juices-table'][$kind];
        TenantAppearance::create([
            ...TenantAppearance::DEFAULTS,
            // Nine Wells Coffee shows style D · Minimal, with banner and logo (owner, 2026-11-12).
            'style' => $tenant->name === self::MINIMAL ? 'minimal' : ['cafe' => 'bistro', 'ethiopian' => 'grid', 'pizza' => 'grid', 'grill' => 'editorial', 'juice' => 'bistro'][$kind],
            'primary_color' => $colors[$kind],
            'logo_media_id' => $setup >= 1 ? $this->photos->logo($tenant, $colors[$kind], ['cafe' => 'bean', 'pizza' => 'slice', 'juice' => 'wheat'][$kind] ?? 'ring')?->id : null,
            'cover_media_id' => $setup >= 2 ? $this->photos->media($tenant, $banner, true)?->id : null,
        ]);
        if ($setup >= 5) {
            foreach (range(1, 6) as $day) {
                OpeningHour::create(['branch_id' => null, 'weekday' => $day, 'opens_at' => '08:00', 'closes_at' => '22:00']);
            }
        }
    }

    /**
     * Active subscriptions renew early next month (on day 1 … today's day), so
     * this month's payment is already in and the revenue widget compares
     * like with like.
     */
    private static function renewsNextMonth(int $n): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        return $now->startOfMonth()->addMonth()->addDays($n % max(1, $now->day))->setTime(12, 0);
    }

    /** Monthly payments (yearly for a yearly plan) up to $paidUntil, starting after the trial. */
    private function payments(Tenant $tenant, ?Plan $plan, CarbonImmutable $paidUntil, User $admin): void
    {
        if (! $plan || $plan->price_minor === 0) {
            return;
        }
        $step = $plan->interval === 'year' ? 12 : 1;
        $firstPaid = CarbonImmutable::parse($tenant->created_at)->addDays(14);
        if ($step === 12) {
            $paidUntil = $firstPaid->addYear(); // one yearly payment when the trial ended
        }
        $methods = ['bank', 'mobile_money', 'mobile_money', 'cash'];
        $end = $paidUntil;
        $k = 0;
        do { // newest period first, back to the end of the trial
            $start = $end->subMonths($step);
            Payment::create([
                'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'amount_minor' => $plan->price_minor, 'currency' => 'ETB',
                'method' => $methods[($tenant->id + $k) % 4], 'reference' => 'DEMO-'.$tenant->id.'-'.$start->format('ym'),
                'paid_at' => $start->max($firstPaid)->min(CarbonImmutable::now())->toDateString(), 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
                'recorded_by' => $admin->id,
            ]);
            $end = $start;
            $k++;
        } while ($end->subMonths($step)->gte($firstPaid) && $k < 24);
        $tenant->forceFill(['paid_until' => $paidUntil])->save();
    }

    /**
     * Upgrades and downgrades: logged like the Super Admin screen logs them
     * (plan_id from → to); the restaurant keeps the new plan.
     *
     * @param  list<Tenant>  $tenants
     * @param  array<string, Plan>  $plans
     */
    private function planChanges(array $tenants, array $plans, User $admin): void
    {
        $now = CarbonImmutable::now();
        $changes = [
            // [restaurant index, from, to, days ago]
            [11, 'plus', 'pro', 2], [12, 'pro', 'business', 4], [14, 'plus', 'pro', 1], [16, 'pro', 'business', 6],
            [18, 'pro', 'plus', 3], [17, 'plus', 'pro', 48], [13, 'pro', 'plus', 75], [7, 'plus', 'pro', 110],
        ];
        foreach ($changes as $c => [$i, $from, $to, $ago]) {
            $tenant = $tenants[$i];
            $ago = $c < 5 ? min($ago, $now->day - 1) : $ago; // the first five fall in this month
            if ($tenant->plan_id !== $plans[$to]->id) {
                continue; // only log changes that end on the plan the restaurant has now
            }
            $this->log($tenant, $admin, 'platform.restaurant_updated', $now->subDays(max(0, $ago)), ['plan_id' => ['from' => $plans[$from]->id, 'to' => $plans[$to]->id]], true);
        }
    }

    /** @param  array<string, mixed>|null  $changes */
    private function log(Tenant $tenant, User $actor, string $action, CarbonImmutable $at, ?array $changes = null, bool $platform = false): void
    {
        $log = ActivityLog::create([
            'tenant_id' => $tenant->id, 'actor_id' => $actor->id, 'action' => $action,
            'subject_type' => $platform ? 'Tenant' : null, 'subject_id' => $platform ? $tenant->id : null, 'changes' => $changes,
        ]);
        $log->forceFill(['created_at' => $at])->save();
    }
}
