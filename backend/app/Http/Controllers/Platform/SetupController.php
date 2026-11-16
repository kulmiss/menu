<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\ActivityLogger;
use App\Support\PlatformSettings;
use App\Support\PublicMenu\MenuPageCache;
use App\Support\Qr\QrMap;
use App\Support\Setup\SetupStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Super Admin setup wizard (owner, 2026-11-16, QUESTIONS #195): opens on the
 * first sign-in of a new install, at most 5 pages, reopened from Settings;
 * afterwards a checklist card on the dashboard shows what is not done.
 */
class SetupController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->state($request)]);
    }

    /** `{done: true}` finishes the wizard; `{checked: "plans"}` ticks a step that has nothing to save; `{card_hidden}` closes the checklist. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'done' => ['sometimes', 'boolean'],
            'checked' => ['sometimes', Rule::in(['plans', 'features'])],
            'card_hidden' => ['sometimes', 'boolean'],
        ]);
        $setup = [...PlatformSettings::DEFAULTS['setup'], ...(array) PlatformSettings::get('setup')];
        if (array_key_exists('done', $data)) {
            $setup['done_at'] = $data['done'] ? now()->toIso8601String() : null;
            if ($data['done']) {
                $this->activity->log('platform.setup_finished');
            }
        }
        if (isset($data['checked'])) {
            $setup[$data['checked'].'_checked'] = true;
        }
        if (array_key_exists('card_hidden', $data)) {
            $setup['card_hidden'] = (bool) $data['card_hidden'];
        }
        PlatformSettings::save(['setup' => $setup]);

        return response()->json(['data' => $this->state($request)]);
    }

    /** Remove the demo restaurants (deleted for 30 days, like any restaurant). */
    public function deleteDemo(Request $request): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'accepted']]);
        $count = 0;
        SetupStatus::demo()->get()->each(function (Tenant $tenant) use (&$count) {
            MenuPageCache::forget($tenant);
            $tenant->delete();
            $count++;
        });
        QrMap::touch();
        $this->activity->log('platform.demo_deleted', null, ['restaurants' => $count]);

        return response()->json(['data' => [...$this->state($request), 'deleted' => $count]]);
    }

    /** @return array<string, mixed> */
    private function state(Request $request): array
    {
        $setup = [...PlatformSettings::DEFAULTS['setup'], ...(array) PlatformSettings::get('setup')];
        $checks = SetupStatus::checks($request->user());

        return [
            'done' => ! empty($setup['done_at']),
            'done_at' => $setup['done_at'],
            'card_hidden' => (bool) $setup['card_hidden'],
            'checks' => $checks,
            'missing' => count(array_filter($checks, fn ($c) => ! $c['done'])),
            'demo_restaurants' => SetupStatus::demo()->count(),
            'production' => app()->isProduction(),
        ];
    }
}
