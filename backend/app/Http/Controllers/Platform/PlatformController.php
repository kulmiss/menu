<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SubscriptionState;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\PlatformAnnouncement;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\Theme;
use App\Rules\Translations;
use App\Support\ActivityLogger;
use App\Support\Features;
use App\Support\MailSettings;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Super Admin screens that are mostly lists (PLAN.md §7.3): dashboard,
 * plans, themes, announcements, activity log and platform settings.
 */
class PlatformController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function dashboard(): JsonResponse
    {
        $tenants = Tenant::with('plan')->get();
        $states = array_fill_keys(array_column(SubscriptionState::cases(), 'value'), 0);
        foreach ($tenants as $t) {
            $states[$t->subscriptionState()->value]++;
        }
        $since = now()->subDays(30)->toDateString();
        $soon = $tenants->filter(function (Tenant $t) {
            $end = $t->subscriptionStageEndsAt();
            $state = $t->subscriptionState();

            return in_array($state, [SubscriptionState::Trial, SubscriptionState::Active], true) && $end !== null && $end->lte(now()->addDays(7));
        });

        return response()->json(['data' => [
            'states' => $states,
            'restaurants' => $tenants->count(),
            'signups_30d' => $tenants->where('created_at', '>=', now()->subDays(30))->count(),
            'views_30d' => (int) DB::table('stats_daily')->where('metric', 'menu_view')->where('date', '>=', $since)->sum('count'),
            'scans_30d' => (int) DB::table('stats_daily')->where('metric', 'qr_scan')->where('date', '>=', $since)->sum('count'),
            'per_plan' => $tenants->groupBy(fn (Tenant $t) => $t->plan ? ($t->plan->name['en'] ?? $t->plan->slug) : '—')->map->count(),
            'expiring' => $soon->sortBy(fn (Tenant $t) => $t->subscriptionStageEndsAt())->take(10)->map(fn (Tenant $t) => [
                'id' => $t->id, 'name' => $t->name, 'state' => $t->subscriptionState()->value, 'ends_at' => $t->subscriptionStageEndsAt()?->toDateString(),
            ])->values(),
            'revenue_30d' => DB::table('payments')->where('paid_at', '>=', $since)->groupBy('currency')->pluck(DB::raw('SUM(amount_minor)'), 'currency')->map(fn ($v) => (int) $v),
        ]]);
    }

    // ---- Plans -----------------------------------------------------------

    public function plans(): JsonResponse
    {
        $features = DB::table('plan_features')->get()->groupBy('plan_id');

        return response()->json(['data' => Plan::orderBy('sort')->orderBy('id')->get()->map(fn (Plan $p) => [
            'id' => $p->id,
            'name' => (object) $p->name,
            'slug' => $p->slug,
            'price_minor' => $p->price_minor,
            'currency' => $p->currency,
            'interval' => $p->interval,
            'trial_days' => $p->trial_days,
            'limits' => (object) ($p->limits ?? []),
            'features' => array_merge(Features::DEFAULTS, ($features->get($p->id) ?? collect())->mapWithKeys(fn ($f) => [$f->feature_key => (bool) $f->enabled])->all()),
            'is_public' => $p->is_public,
            'is_active' => $p->is_active,
            'restaurants' => Tenant::where('plan_id', $p->id)->count(),
        ])]);
    }

    public function savePlan(Request $request, ?int $plan = null): JsonResponse
    {
        $model = $plan ? Plan::findOrFail($plan) : new Plan;
        $limitKeys = config('menu.limit_keys');
        $data = $request->validate([
            'name' => [$plan ? 'sometimes' : 'required', Translations::required(60)],
            'slug' => [$plan ? 'sometimes' : 'required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', Rule::unique('plans', 'slug')->ignore($model->id)],
            'price_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'interval' => ['sometimes', Rule::in(['month', 'year'])],
            'trial_days' => ['sometimes', 'integer', 'between:0,365'],
            'limits' => ['sometimes', 'array:'.implode(',', $limitKeys)],
            'limits.*' => ['nullable', 'integer', 'min:0'],
            'features' => ['sometimes', 'array:'.implode(',', array_keys(Features::DEFAULTS))],
            'features.*' => ['boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($model, $data) {
            $model->fill(array_diff_key($data, ['features' => true]));
            $model->limits ??= [];
            $model->save();
            foreach ($data['features'] ?? [] as $key => $enabled) {
                DB::table('plan_features')->updateOrInsert(['plan_id' => $model->id, 'feature_key' => $key], ['enabled' => (bool) $enabled, 'updated_at' => now(), 'created_at' => now()]);
            }
        });
        Features::forget();
        // Restaurants on this plan may show or hide things on their menu now.
        Tenant::where('plan_id', $model->id)->get()->each->bumpCacheVersion();
        $this->activity->log($plan ? 'platform.plan_updated' : 'platform.plan_created', $model, null, null);

        return response()->json(['data' => ['id' => $model->id]], $plan ? 200 : 201);
    }

    // ---- Themes ----------------------------------------------------------

    public function themes(): JsonResponse
    {
        return response()->json(['data' => Theme::orderBy('sort')->orderBy('id')->get()->map(fn (Theme $t) => [
            'id' => $t->id, 'name' => (object) $t->name, ...$t->preset, 'is_active' => $t->is_active, 'sort' => $t->sort,
        ])]);
    }

    public function saveTheme(Request $request, ?int $theme = null): JsonResponse
    {
        $model = $theme ? Theme::findOrFail($theme) : new Theme(['sort' => (int) Theme::max('sort') + 1]);
        $data = $request->validate([
            'name' => [$theme ? 'sometimes' : 'required', Translations::required(40)],
            'primary' => [$theme ? 'sometimes' : 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font' => [$theme ? 'sometimes' : 'required', Rule::in(['system', 'serif', 'rounded'])],
            'style' => [$theme ? 'sometimes' : 'required', Rule::in(TenantAppearance::STYLES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $preset = array_merge($model->preset ?? [], array_intersect_key($data, array_flip(['primary', 'font', 'style'])));
        $model->fill([...array_intersect_key($data, ['name' => true, 'is_active' => true]), 'preset' => $preset])->save();
        $this->activity->log($theme ? 'platform.theme_updated' : 'platform.theme_created', $model, null, null);

        return response()->json(['data' => ['id' => $model->id]], $theme ? 200 : 201);
    }

    // ---- Announcements ---------------------------------------------------

    public function announcements(): JsonResponse
    {
        return response()->json(['data' => PlatformAnnouncement::latest('id')->get()->map(fn (PlatformAnnouncement $a) => self::announcementRow($a))]);
    }

    public function saveAnnouncement(Request $request, ?int $announcement = null): JsonResponse
    {
        $model = $announcement ? PlatformAnnouncement::findOrFail($announcement) : new PlatformAnnouncement;
        $data = $request->validate([
            'title' => [$announcement ? 'sometimes' : 'required', Translations::required(120)],
            'body' => ['sometimes', Translations::optional(2000)],
            'level' => ['sometimes', Rule::in(['info', 'warning'])],
            'plan_ids' => ['sometimes', 'nullable', 'array'],
            'plan_ids.*' => ['integer', Rule::exists('plans', 'id')],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
        ]);
        if (array_key_exists('plan_ids', $data) && $data['plan_ids'] === []) {
            $data['plan_ids'] = null;
        }
        $model->fill([...$data, 'created_by' => $model->created_by ?? $request->user()?->getAuthIdentifier()])->save();
        $this->activity->log('platform.announcement_saved', $model, null, null);

        return response()->json(['data' => self::announcementRow($model)], $announcement ? 200 : 201);
    }

    public function deleteAnnouncement(int $announcement): Response
    {
        PlatformAnnouncement::findOrFail($announcement)->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    public static function announcementRow(PlatformAnnouncement $a): array
    {
        return [
            'id' => $a->id, 'title' => (object) $a->title, 'body' => (object) $a->body, 'level' => $a->level,
            'plan_ids' => $a->plan_ids, 'starts_at' => $a->starts_at?->toIso8601String(), 'ends_at' => $a->ends_at?->toIso8601String(),
        ];
    }

    // ---- Activity log ----------------------------------------------------

    public function activity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['sometimes', 'nullable', 'integer'],
            'platform_only' => ['sometimes', 'boolean'],
            'action' => ['sometimes', 'nullable', 'string', 'max:80'],
            'before_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        $rows = ActivityLog::query()->with(['actor:id,name,email', 'impersonator:id,name'])
            ->when($data['tenant_id'] ?? null, fn ($q, $t) => $q->where('tenant_id', $t))
            ->when($request->boolean('platform_only'), fn ($q) => $q->whereNull('tenant_id'))
            ->when($data['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($data['before_id'] ?? null, fn ($q, $id) => $q->where('id', '<', $id))
            ->latest('id')->limit(100)->get();
        $names = Tenant::withTrashed()->whereIn('id', $rows->pluck('tenant_id')->filter()->unique())->pluck('name', 'id');

        return response()->json(['data' => $rows->map(fn (ActivityLog $l) => [
            'id' => $l->id,
            'at' => $l->created_at->toIso8601String(),
            'tenant' => $l->tenant_id ? ['id' => $l->tenant_id, 'name' => $names[$l->tenant_id] ?? null] : null,
            'actor' => $l->actor?->name,
            'impersonator' => $l->impersonator?->name,
            'action' => $l->action,
            'subject' => $l->subject_type ? $l->subject_type.' #'.$l->subject_id : null,
            'changes' => $l->changes,
        ])]);
    }

    // ---- Settings --------------------------------------------------------

    public function settings(): JsonResponse
    {
        return response()->json(['data' => PlatformSettings::all()]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:60'],
            'support_email' => ['sometimes', 'nullable', 'email'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[a-z0-9.-]+$/i'],
            'default_currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'terms' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'privacy' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ]);
        PlatformSettings::save($data);
        $this->activity->log('platform.settings_saved', null, ['keys' => ['to' => array_keys($data)]], null);

        return $this->settings();
    }

    public function mailSettings(): JsonResponse
    {
        return response()->json(['data' => MailSettings::forScreen()]);
    }

    public function saveMailSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+$/i'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(MailSettings::ENCRYPTIONS)],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'clear_password' => ['sometimes', 'boolean'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:100'],
        ]);
        MailSettings::save($data, $data['password'] ?? null, (bool) ($data['clear_password'] ?? false));
        // Never log the password; only which fields were sent.
        $this->activity->log('platform.mail_settings_saved', null, ['host' => ['to' => $data['host']], 'password_changed' => ['to' => ($data['password'] ?? '') !== '']], null);

        return $this->mailSettings();
    }

    public function clearMailSettings(): JsonResponse
    {
        MailSettings::clear();
        $this->activity->log('platform.mail_settings_cleared', null, [], null);

        return $this->mailSettings();
    }

    /** Sends a short e-mail with the saved settings and reports what happened. */
    public function testMail(Request $request): JsonResponse
    {
        $data = $request->validate(['to' => ['nullable', 'email']]);
        $to = $data['to'] ?? (string) $request->user()?->email;
        try {
            Mail::raw(__('platform.mail_test.body', ['app' => PlatformSettings::get('name')]), function ($m) use ($to) {
                $m->to($to)->subject(__('platform.mail_test.subject', ['app' => PlatformSettings::get('name')]));
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => __('platform.mail_test.failed', ['reason' => Str::limit($e->getMessage(), 300)]),
                'code' => 'mail.failed',
            ], 422);
        }

        return response()->json(['data' => ['sent_to' => $to], 'message' => __('platform.mail_test.sent', ['email' => $to])]);
    }
}
