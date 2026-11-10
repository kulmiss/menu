<?php

namespace App\Http\Controllers\Platform;

use App\Enums\Role;
use App\Enums\SubscriptionState;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\ValidSlug;
use App\Support\ActivityLogger;
use App\Support\Features;
use App\Support\ImpersonationManager;
use App\Support\PublicMenu\MenuPageCache;
use App\Support\Qr\QrMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin › Restaurants (PLAN.md §7.3 screen 2): list, create, edit,
 * suspend, delete/restore, features, payments, "log in as".
 * Platform code: reads every restaurant, so it lives in this namespace only.
 */
class RestaurantController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', Rule::in(array_column(SubscriptionState::cases(), 'value'))],
            'plan_id' => ['sometimes', 'nullable', 'integer'],
            'deleted' => ['sometimes', 'boolean'],
        ]);
        $query = Tenant::query()->with(['plan', 'memberships' => fn ($q) => $q->where('role', Role::Owner)->with('user')])
            ->when($request->boolean('deleted'), fn ($q) => $q->onlyTrashed())
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%")))
            ->when($data['plan_id'] ?? null, fn ($q, $p) => $q->where('plan_id', $p))
            ->latest('id');

        // The state is computed from dates; filter after loading (restaurant counts are small).
        $rows = $query->limit(1000)->get()->map(fn (Tenant $t) => $this->row($t));
        if (! empty($data['state'])) {
            $rows = $rows->where('state', $data['state'])->values();
        }

        return response()->json(['data' => $rows->take(500)->values()]);
    }

    public function show(int $restaurant): JsonResponse
    {
        $tenant = Tenant::withTrashed()->with(['plan', 'memberships.user'])->findOrFail($restaurant);
        $overrides = DB::table('tenant_feature_overrides')->where('tenant_id', $tenant->id)->pluck('enabled', 'feature_key');

        return response()->json(['data' => [
            ...$this->row($tenant),
            'members' => $tenant->memberships->map(fn (Membership $m) => ['id' => $m->user_id, 'name' => $m->user->name, 'email' => $m->user->email, 'role' => $m->role->value])->values(),
            'features' => Features::for($tenant),
            'overrides' => $overrides,
            'payments' => Payment::with('plan')->where('tenant_id', $tenant->id)->latest('paid_at')->get()->map(fn (Payment $p) => PaymentController::row($p))->values(),
            'counts' => [
                'menus' => DB::table('menus')->where('tenant_id', $tenant->id)->count(),
                'items' => DB::table('items')->where('tenant_id', $tenant->id)->count(),
                'branches' => DB::table('branches')->where('tenant_id', $tenant->id)->count(),
                'views_30d' => (int) DB::table('stats_daily')->where('tenant_id', $tenant->id)->where('metric', 'menu_view')->where('date', '>=', now()->subDays(30)->toDateString())->sum('count'),
            ],
        ]]);
    }

    /** A new restaurant with its owner (an existing user, or a new one who gets a set-password e-mail). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', new ValidSlug],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190'],
            'plan_id' => ['sometimes', 'nullable', 'integer', Rule::exists('plans', 'id')],
            'trial_days' => ['sometimes', 'integer', 'between:0,365'],
        ]);

        [$tenant, $newUser] = DB::transaction(function () use ($data) {
            $user = User::where('email', $data['owner_email'])->first();
            $new = $user === null;
            if ($user === null) {
                // The set-password e-mail proves the address, so it counts as verified.
                $user = User::create(['name' => $data['owner_name'], 'email' => $data['owner_email'], 'password' => Str::password(32)]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $plan = isset($data['plan_id']) ? Plan::find($data['plan_id']) : Plan::where('slug', 'trial')->first();
            $tenant = Tenant::create([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'plan_id' => $plan?->id,
                'trial_ends_at' => now()->addDays($data['trial_days'] ?? ($plan->trial_days ?? (int) config('menu.trial_days'))),
                'default_locale' => 'en',
                'currency_code' => (string) config('menu.default_currency', 'ETB'),
            ]);
            Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => Role::Owner]);

            return [$tenant, $new ? $user : null];
        });
        if ($newUser !== null) {
            Password::broker()->sendResetLink(['email' => $newUser->email]);
        }
        $this->activity->log('platform.restaurant_created', $tenant, ['slug' => ['to' => $tenant->slug]], null);

        return response()->json(['data' => $this->row($tenant->load('plan'))], 201);
    }

    public function update(Request $request, int $restaurant): JsonResponse
    {
        $tenant = Tenant::findOrFail($restaurant);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'plan_id' => ['sometimes', 'nullable', 'integer', Rule::exists('plans', 'id')],
            'trial_ends_at' => ['sometimes', 'nullable', 'date'],
            'paid_until' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['sometimes', 'timezone:all'],
            'currency_code' => ['sometimes', 'string', 'size:3', 'alpha'],
        ]);
        $tenant->forceFill($data)->save();
        Features::forget($tenant->id);
        $tenant->bumpCacheVersion();
        $this->activity->log('platform.restaurant_updated', $tenant, ActivityLogger::diff($tenant), null);

        return response()->json(['data' => $this->row($tenant->load('plan'))]);
    }

    public function suspend(Request $request, int $restaurant): JsonResponse
    {
        $tenant = Tenant::findOrFail($restaurant);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $tenant->forceFill(['status' => Tenant::STATUS_SUSPENDED, 'suspended_at' => now(), 'suspended_reason' => $data['reason']])->save();
        MenuPageCache::forget($tenant);
        $this->activity->log('platform.restaurant_suspended', $tenant, ['reason' => ['to' => $data['reason']]], null);

        return response()->json(['data' => $this->row($tenant)]);
    }

    public function unsuspend(int $restaurant): JsonResponse
    {
        $tenant = Tenant::findOrFail($restaurant);
        $tenant->forceFill(['status' => Tenant::STATUS_ACTIVE, 'suspended_at' => null, 'suspended_reason' => null])->save();
        MenuPageCache::forget($tenant);
        $this->activity->log('platform.restaurant_unsuspended', $tenant, null, null);

        return response()->json(['data' => $this->row($tenant)]);
    }

    /** Soft delete: kept 30 days (restore possible), then purged by the scheduler. */
    public function destroy(int $restaurant): Response
    {
        $tenant = Tenant::findOrFail($restaurant);
        MenuPageCache::forget($tenant);
        $tenant->delete();
        QrMap::touch();
        $this->activity->log('platform.restaurant_deleted', $tenant, null, null);

        return response()->noContent();
    }

    public function restore(int $restaurant): JsonResponse
    {
        $tenant = Tenant::onlyTrashed()->findOrFail($restaurant);
        $tenant->restore();
        QrMap::touch();
        $this->activity->log('platform.restaurant_restored', $tenant, null, null);

        return response()->json(['data' => $this->row($tenant)]);
    }

    /** Feature switches for one restaurant: true/false wins over the plan, null = as the plan. */
    public function features(Request $request, int $restaurant): JsonResponse
    {
        $tenant = Tenant::findOrFail($restaurant);
        $data = $request->validate(['features' => ['required', 'array:'.implode(',', array_keys(Features::DEFAULTS))], 'features.*' => ['nullable', 'boolean']]);
        foreach ($data['features'] as $key => $enabled) {
            if ($enabled === null) {
                DB::table('tenant_feature_overrides')->where('tenant_id', $tenant->id)->where('feature_key', $key)->delete();
            } else {
                DB::table('tenant_feature_overrides')->updateOrInsert(['tenant_id' => $tenant->id, 'feature_key' => $key], ['enabled' => (bool) $enabled, 'updated_at' => now(), 'created_at' => now()]);
            }
        }
        Features::forget($tenant->id);
        $tenant->bumpCacheVersion();
        $this->activity->log('platform.features_changed', $tenant, ['features' => ['to' => $data['features']]], null);

        return $this->show($tenant->id);
    }

    /** "Log in as" the restaurant's owner (PLAN.md §5). */
    public function impersonate(Request $request, int $restaurant, ImpersonationManager $manager): JsonResponse
    {
        $tenant = Tenant::findOrFail($restaurant);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        $owner = $tenant->memberships()->where('role', Role::Owner)->with('user')->first()?->user;
        if ($owner === null) {
            throw ValidationException::withMessages(['reason' => __('platform.no_owner')]);
        }
        $admin = $request->user();
        $this->activity->log('platform.impersonation_started', $tenant, ['reason' => ['to' => $data['reason']], 'user' => ['to' => $owner->email]], $tenant->id);
        $manager->start($request, $admin, $tenant, $owner, $data['reason']);

        return response()->json(['data' => ['tenant_id' => $tenant->id, 'redirect' => '/admin']]);
    }

    /** @return array<string, mixed> */
    private function row(Tenant $t): array
    {
        $owner = $t->relationLoaded('memberships') ? $t->memberships->firstWhere('role', Role::Owner)?->user : null;

        return [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'state' => $t->subscriptionState()->value,
            'status' => $t->status,
            'suspended_reason' => $t->suspended_reason,
            'plan' => $t->plan ? ['id' => $t->plan->id, 'name' => $t->plan->name['en'] ?? $t->plan->slug] : null,
            'trial_ends_at' => $t->trial_ends_at?->toDateString(),
            'paid_until' => $t->paid_until?->toDateString(),
            'stage_ends_at' => $t->subscriptionStageEndsAt()?->toDateString(),
            'owner' => $owner ? ['name' => $owner->name, 'email' => $owner->email] : null,
            'timezone' => $t->timezone,
            'currency_code' => $t->currency_code,
            'created_at' => $t->created_at?->toDateString(),
            'deleted_at' => $t->deleted_at instanceof Carbon ? $t->deleted_at->toDateString() : null,
            'url' => url('/'.$t->slug),
        ];
    }
}
