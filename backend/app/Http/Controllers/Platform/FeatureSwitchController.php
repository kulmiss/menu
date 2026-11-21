<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Jobs\RebuildAllMenuPages;
use App\Models\TenantAppearance;
use App\Support\ActivityLogger;
use App\Support\Features;
use App\Support\MyOrder;
use App\Support\PlatformSettings;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

/**
 * Settings → Features (owner, 2026-11-14, QUESTIONS #192): one platform-wide
 * switch per feature. OFF wins over every plan and restaurant switch, in the
 * web admin, the app, the guest menu and the API (Features::for).
 */
class FeatureSwitchController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->rows()]);
    }

    public function update(Request $request): JsonResponse
    {
        $keys = [...array_keys(Features::DEFAULTS), ...array_keys(Features::PLATFORM_ONLY)];
        $data = $request->validate([
            'features' => ['required', 'array:'.implode(',', $keys)],
            'features.*' => ['boolean'],
        ]);
        $next = [...Features::global(), ...array_map('boolval', $data['features'])];
        $styles = array_filter(TenantAppearance::STYLES, fn (string $s) => $next['style_'.$s] ?? true);
        if ($styles === []) {
            throw ValidationException::withMessages(['features' => __('platform.features_need_style')]);
        }
        $before = Features::global();

        $stored = [];
        foreach (array_keys(Features::DEFAULTS) as $key) {
            if (! $next[$key]) {
                $stored[$key] = false; // only the OFF ones are stored; missing = on
            }
        }
        $save = ['features_global' => $stored];
        foreach (Features::PLATFORM_ONLY as $key => $setting) {
            $save[$setting] = $next[$key];
        }
        PlatformSettings::save($save);
        Features::forget();

        $changed = array_keys(array_diff_assoc($next, $before));
        if (array_diff($changed, array_keys(Features::PLATFORM_ONLY)) !== []) {
            // Every guest page may show or hide something now: the saved copies go at once (so no guest
            // gets an old page, e.g. with "+" buttons), the versions move on in a queued job (QUESTIONS #236).
            File::deleteDirectory(public_path('menu-cache'), preserve: true);
            RebuildAllMenuPages::dispatch();
        }
        $this->activity->log('platform.features_global_changed', null, ['features' => ['from' => array_intersect_key($before, array_flip($changed)), 'to' => array_intersect_key($next, array_flip($changed))]], null);

        return response()->json(['data' => $this->rows()]);
    }

    /**
     * Before a switch is saved: how many restaurants have the feature now (plan or override on),
     * and for "My order" how many show it to guests (their own switch on too). QUESTIONS #236.
     */
    public function impact(string $key): JsonResponse
    {
        abort_unless(array_key_exists($key, Features::DEFAULTS), 404);
        $q = self::onFor($key);
        $data = ['key' => $key, 'enabled' => Features::globallyOn($key), 'restaurants' => (clone $q)->count()];
        if ($key === MyOrder::FEATURE) {
            $data['guests_see'] = (clone $q)->leftJoin('tenant_appearance as a', 'a.tenant_id', '=', 't.id')
                ->where(fn ($w) => $w->whereNull('a.id')->orWhere('a.show_order_list', true))->count();
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Restaurants (not deleted) whose plan or own override has the feature on; the platform switch aside.
     *
     * @return Builder
     */
    public static function onFor(string $key)
    {
        $default = Features::DEFAULTS[$key] ? 1 : 0;

        return DB::table('tenants as t')->whereNull('t.deleted_at')
            ->leftJoin('tenant_feature_overrides as o', fn ($j) => $j->on('o.tenant_id', '=', 't.id')->where('o.feature_key', '=', $key))
            ->leftJoin('plan_features as pf', fn ($j) => $j->on('pf.plan_id', '=', 't.plan_id')->where('pf.feature_key', '=', $key))
            ->whereRaw('COALESCE(o.enabled, pf.enabled, ?) = 1', [$default]);
    }

    /** @return list<array{key: string, enabled: bool, label: string, plan_feature: bool, plans_off: int, restaurants_off: int}> */
    private function rows(): array
    {
        $global = Features::global();
        $plansOff = DB::table('plan_features')->where('enabled', false)->groupBy('feature_key')->selectRaw('feature_key, COUNT(*) AS n')->pluck('n', 'feature_key');
        $ownOff = DB::table('tenant_feature_overrides')->where('enabled', false)->groupBy('feature_key')->selectRaw('feature_key, COUNT(*) AS n')->pluck('n', 'feature_key');

        return array_map(fn (string $key) => [
            'key' => $key,
            'enabled' => $global[$key],
            'label' => __('platform.features.'.$key),
            'plan_feature' => array_key_exists($key, Features::DEFAULTS),
            'plans_off' => (int) ($plansOff[$key] ?? 0),
            'restaurants_off' => (int) ($ownOff[$key] ?? 0),
        ], array_keys($global));
    }
}
