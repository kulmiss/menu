<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\MobileApp;
use App\Support\Push\Push;
use App\Support\Security\SecurityLog;
use App\Support\Tables\TableQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Super Admin → Mobile app (owner, 2026-11-14, QUESTIONS #193): settings the
 * app reads from `mobile/config`, pushes to app users, the signed-in devices
 * and a few numbers.
 */
class MobileAppController extends Controller
{
    private const VERSION = 'regex:/^\d{1,3}\.\d{1,3}\.\d{1,3}$/';

    public function __construct(private readonly ActivityLogger $activity) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => MobileApp::settings()]);
    }

    public function update(Request $request): JsonResponse
    {
        $link = ['nullable', 'string', 'max:255', 'regex:#^(/[A-Za-z0-9/_\-?=&.%]*|https://\S+)$#'];
        $url = ['nullable', 'string', 'max:255', 'url:https'];
        $data = $request->validate([
            'restaurant_sign_in' => ['sometimes', 'boolean'],
            'super_admin_sign_in' => ['sometimes', 'boolean'],
            'sign_in_message' => ['sometimes', 'nullable', 'string', 'max:300'],
            'maintenance' => ['sometimes', 'boolean'],
            'maintenance_message' => ['sometimes', 'nullable', 'string', 'max:300'],
            'latest_version' => ['sometimes', 'string', self::VERSION],
            'release_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'min_version' => ['sometimes', 'string', self::VERSION],
            'recommended_version' => ['sometimes', 'nullable', 'string', self::VERSION],
            'banner' => ['sometimes', 'array:text,link,starts_at,ends_at,audience'],
            'banner.text' => ['nullable', 'string', 'max:200'],
            'banner.link' => $link,
            'banner.starts_at' => ['nullable', 'date'],
            'banner.ends_at' => ['nullable', 'date', 'after:banner.starts_at'],
            'banner.audience' => ['sometimes', Rule::in(MobileApp::AUDIENCES)],
            'app_features' => ['sometimes', 'array:'.implode(',', MobileApp::APP_FEATURES)],
            'app_features.*' => ['boolean'],
            'links' => ['sometimes', 'array:play_store,app_store,privacy,terms,support_whatsapp'],
            'links.play_store' => $url,
            'links.app_store' => $url,
            'links.privacy' => $url,
            'links.terms' => $url,
            'links.support_whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s\-]{6,20}$/'],
        ]);
        $next = [...MobileApp::settings(), ...$data];
        if (MobileApp::older((string) $next['latest_version'], (string) $next['min_version'])) {
            return response()->json(['message' => __('mobile.min_above_latest'), 'errors' => ['min_version' => [__('mobile.min_above_latest')]]], 422);
        }
        $nulls = fn (array $a) => array_map(fn ($v) => $v ?? '', $a);
        foreach (['sign_in_message', 'maintenance_message', 'release_notes', 'recommended_version'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = (string) ($data[$k] ?? '');
            }
        }
        foreach (['links'] as $k) {
            if (isset($data[$k])) {
                $data[$k] = $nulls($data[$k]);
            }
        }
        $before = MobileApp::settings();
        MobileApp::save($data);
        $after = MobileApp::settings();
        $changed = array_keys(array_filter($after, fn ($v, $k) => $before[$k] != $v, ARRAY_FILTER_USE_BOTH));
        $this->activity->log('platform.mobile_settings_changed', null, ['changed' => ['to' => $changed]], null);

        return response()->json(['data' => $after]);
    }

    // ---- Pushes -----------------------------------------------------------

    public function pushes(): JsonResponse
    {
        $rows = DB::table('mobile_pushes')->leftJoin('users', 'users.id', '=', 'mobile_pushes.sent_by')
            ->orderByDesc('mobile_pushes.id')->limit(100)
            ->get(['mobile_pushes.*', 'users.name as sent_by_name']);

        return response()->json(['data' => $rows->map(fn ($r) => [
            'id' => $r->id, 'title' => $r->title, 'body' => $r->body, 'link' => $r->link,
            'audience' => json_decode((string) $r->audience, true), 'recipients' => (int) $r->recipients, 'with_app' => (int) $r->with_app,
            'sent_by' => $r->sent_by_name, 'created_at' => CarbonImmutable::parse($r->created_at)->toIso8601String(),
        ])]);
    }

    /** Count first (`dry_run`), then send: inbox for everyone chosen, a phone push where the app has pushes on. */
    public function sendPush(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'link' => ['nullable', 'string', 'max:255', 'regex:#^(/[A-Za-z0-9/_\-?=&.%]*|https://\S+)$#'],
            'audience' => ['required', 'array:to,roles,plan_ids,tenant_ids'],
            'audience.to' => ['required', Rule::in(MobileApp::AUDIENCES)],
            'audience.roles' => ['sometimes', 'array'],
            'audience.roles.*' => [Rule::in(['owner', 'manager', 'staff'])],
            'audience.plan_ids' => ['sometimes', 'array', 'max:50'],
            'audience.plan_ids.*' => ['integer'],
            'audience.tenant_ids' => ['sometimes', 'array', 'max:500'],
            'audience.tenant_ids.*' => ['integer'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);
        $users = $this->audience($data['audience'])->get();
        $withApp = $users->filter(fn (User $u) => $u->pushTokens()->exists())->count();
        if ($request->boolean('dry_run')) {
            return response()->json(['data' => ['recipients' => $users->count(), 'with_app' => $withApp]]);
        }
        abort_if($users->isEmpty(), 422, __('mobile.push_nobody'));

        Push::send($users, 'platform_announcement', $data['title'], $data['body'], (string) ($data['link'] ?? '/inbox'));
        $id = DB::table('mobile_pushes')->insertGetId([
            'sent_by' => $request->user()->id, 'title' => $data['title'], 'body' => $data['body'], 'link' => $data['link'] ?? null,
            'audience' => json_encode($data['audience']), 'recipients' => $users->count(), 'with_app' => $withApp, 'created_at' => now(),
        ]);
        $this->activity->log('platform.mobile_push_sent', null, ['push' => ['to' => ['id' => $id, 'recipients' => $users->count()]]], null);

        return response()->json(['data' => ['id' => $id, 'recipients' => $users->count(), 'with_app' => $withApp]], 201);
    }

    /**
     * People signed in to the app who match the filters.
     *
     * @param  array<string, mixed>  $a
     * @return Builder<User>
     */
    private function audience(array $a): Builder
    {
        $mobileUsers = DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())
            ->where('abilities', 'like', '%mobile%')->whereNull('impersonator_id')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->select('tokenable_id');
        $q = User::query()->whereIn('id', $mobileUsers);
        if ($a['to'] === 'super_admin') {
            return $q->where('is_super_admin', true);
        }
        if ($a['to'] === 'restaurant' || ! empty($a['roles']) || ! empty($a['plan_ids']) || ! empty($a['tenant_ids'])) {
            $members = DB::table('memberships')->join('tenants', 'tenants.id', '=', 'memberships.tenant_id')->whereNull('tenants.deleted_at')
                ->when(! empty($a['roles']), fn ($m) => $m->whereIn('memberships.role', $a['roles']))
                ->when(! empty($a['plan_ids']), fn ($m) => $m->whereIn('tenants.plan_id', $a['plan_ids']))
                ->when(! empty($a['tenant_ids']), fn ($m) => $m->whereIn('tenants.id', $a['tenant_ids']))
                ->select('memberships.user_id');
            $q->whereIn('id', $members);
        }

        return $q;
    }

    // ---- Devices ----------------------------------------------------------

    public function devices(Request $request): JsonResponse|StreamedResponse
    {
        $query = PersonalAccessToken::query()
            ->join('users', 'users.id', '=', 'personal_access_tokens.tokenable_id')
            ->where('personal_access_tokens.tokenable_type', (new User)->getMorphClass())
            ->where('personal_access_tokens.abilities', 'like', '%mobile%')
            ->whereNull('personal_access_tokens.impersonator_id')
            ->where(fn ($q) => $q->whereNull('personal_access_tokens.expires_at')->orWhere('personal_access_tokens.expires_at', '>', now()))
            ->select('personal_access_tokens.*', 'users.name as user_name', 'users.email as user_email', 'users.is_super_admin')
            ->addSelect(['restaurants' => DB::table('memberships')->join('tenants', 'tenants.id', '=', 'memberships.tenant_id')
                ->whereColumn('memberships.user_id', 'users.id')->whereNull('tenants.deleted_at')
                ->selectRaw("SUBSTRING(GROUP_CONCAT(tenants.name ORDER BY tenants.name SEPARATOR ', '), 1, 200)")])
            ->addSelect(['push_on' => DB::table('push_tokens')->whereColumn('push_tokens.personal_access_token_id', 'personal_access_tokens.id')->selectRaw('COUNT(*) > 0')]);

        return TableQuery::respond($query, $request, [
            'search' => fn ($q, string $s) => $q->where(fn ($w) => $w->where('users.name', 'like', "%{$s}%")->orWhere('users.email', 'like', "%{$s}%")->orWhere('personal_access_tokens.name', 'like', "%{$s}%")),
            'filters' => [
                'platform' => fn ($q, array $v) => $q->whereIn('personal_access_tokens.platform', $v),
                'version' => fn ($q, array $v) => $q->whereIn('personal_access_tokens.app_version', $v),
                'kind' => fn ($q, array $v) => $q->where(fn ($w) => $w->when(in_array('super_admin', $v, true), fn ($x) => $x->orWhere('users.is_super_admin', true))
                    ->when(in_array('restaurant', $v, true), fn ($x) => $x->orWhere('users.is_super_admin', false))),
            ],
            'date' => 'personal_access_tokens.last_used_at',
            'sorts' => [
                'last_used_at' => 'personal_access_tokens.last_used_at',
                'user' => 'users.name',
                'app_version' => 'personal_access_tokens.app_version',
                'created_at' => 'personal_access_tokens.created_at',
            ],
            'default_sort' => '-last_used_at',
        ], fn (PersonalAccessToken $t) => [
            'id' => $t->id,
            'device' => $t->name,
            'platform' => $t->platform,
            'app_version' => $t->app_version,
            'user' => ['id' => (int) $t->tokenable_id, 'name' => $t->getAttribute('user_name'), 'email' => $t->getAttribute('user_email'), 'is_super_admin' => (bool) $t->getAttribute('is_super_admin')],
            'restaurants' => $t->getAttribute('restaurants'),
            'push_on' => (bool) $t->getAttribute('push_on'),
            'last_used_at' => $t->last_used_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ]);
    }

    public function signOutDevice(Request $request, int $device): JsonResponse
    {
        $token = PersonalAccessToken::where('abilities', 'like', '%mobile%')->findOrFail($device);
        $token->delete(); // its push tokens go with it (foreign key)
        SecurityLog::event('tokens.revoked', (int) $token->tokenable_id, null, ['count' => 1, 'why' => 'super_admin']);
        $this->activity->log('platform.mobile_device_signed_out', null, ['device' => ['to' => $token->name]], null);

        return response()->json(['data' => ['signed_out' => 1]]);
    }

    /** Every app device of everyone, except the Super Admin's own current one. */
    public function signOutAll(Request $request): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'accepted']]);
        $mine = $request->user()->currentAccessToken();
        $count = PersonalAccessToken::where('abilities', 'like', '%mobile%')
            ->when($mine instanceof PersonalAccessToken, fn ($q) => $q->whereKeyNot($mine->id))
            ->delete();
        SecurityLog::event('tokens.revoked', $request->user()->id, null, ['count' => $count, 'why' => 'super_admin_all']);
        $this->activity->log('platform.mobile_all_signed_out', null, ['count' => ['to' => $count]], null);

        return response()->json(['data' => ['signed_out' => $count]]);
    }

    // ---- Stats ------------------------------------------------------------

    public function stats(): JsonResponse
    {
        $base = fn () => DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())
            ->where('abilities', 'like', '%mobile%')->whereNull('impersonator_id')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        $active = fn () => $base()->where('last_used_at', '>=', now()->subDays(30));
        $devices = $base()->count();
        $withPush = $base()->whereExists(fn ($q) => $q->from('push_tokens')->whereColumn('push_tokens.personal_access_token_id', 'personal_access_tokens.id'))->count();
        $group = fn (string $col) => $active()->groupBy($col)->selectRaw("COALESCE({$col}, '') AS k, COUNT(*) AS n")->orderByDesc('n')->limit(20)
            ->get()->map(fn ($r) => ['key' => $r->k === '' ? null : $r->k, 'count' => (int) $r->n])->values();

        return response()->json(['data' => [
            'devices' => $devices,
            'active_7d' => $base()->where('last_used_at', '>=', now()->subDays(7))->count(),
            'active_30d' => $active()->count(),
            'people' => $base()->distinct()->count('tokenable_id'),
            'platforms' => $group('platform'),
            'versions' => $group('app_version'),
            'push_opt_in' => $devices > 0 ? (int) round($withPush * 100 / $devices) : null,
            'below_min' => $active()->get(['app_version'])->filter(fn ($r) => $r->app_version && MobileApp::older((string) $r->app_version, (string) MobileApp::settings()['min_version']))->count(),
        ]]);
    }
}
