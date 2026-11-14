<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\PushToken;
use App\Support\Push\PushTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Push registration, switches and the inbox for the app (QUESTIONS #189). */
class PushController extends Controller
{
    public function register(Request $request): Response
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
        ]);
        // A phone that changed hands moves its token to the new user.
        PushToken::updateOrCreate(['token' => $data['token']], [
            'user_id' => $request->user()->id,
            'personal_access_token_id' => $request->user()->currentAccessToken()->getKey(),
            'platform' => $data['platform'],
        ]);

        return response()->noContent();
    }

    public function unregister(Request $request): Response
    {
        $request->validate(['token' => ['required', 'string', 'max:255']]);
        $request->user()->pushTokens()->where('token', $request->input('token'))->delete();

        return response()->noContent();
    }

    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => collect(PushTypes::for($user))->map(fn (string $type) => [
            'type' => $type,
            'label' => __('push.types.'.$type),
            'group' => in_array($type, PushTypes::PLATFORM, true) ? 'platform' : 'restaurant',
            'enabled' => PushTypes::enabled($user, $type),
        ])->values()]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $types = PushTypes::for($user);
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['boolean']]);
        $settings = is_array($user->push_settings) ? $user->push_settings : [];
        foreach ($data['settings'] as $type => $on) {
            abort_unless(in_array($type, $types, true), 422, 'Unknown notification type.');
            if ($on) {
                unset($settings[$type]);
            } else {
                $settings[$type] = false;
            }
        }
        $user->forceFill(['push_settings' => $settings === [] ? null : $settings])->save();

        return $this->settings($request);
    }

    public function inbox(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = AppNotification::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(AppNotification::KEEP_DAYS))
            ->orderByDesc('id')->cursorPaginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (AppNotification $n) => $n->toApi())->values(),
            'meta' => [
                'next_cursor' => $page->nextCursor()?->encode(),
                'unread' => AppNotification::where('user_id', $user->id)->whereNull('read_at')->where('created_at', '>=', now()->subDays(AppNotification::KEEP_DAYS))->count(),
            ],
        ]);
    }

    public function read(Request $request): Response
    {
        $data = $request->validate(['ids' => ['required_without:all', 'array', 'max:200'], 'ids.*' => ['integer'], 'all' => ['sometimes', 'boolean']]);
        AppNotification::where('user_id', $request->user()->id)->whereNull('read_at')
            ->when(! ($data['all'] ?? false), fn ($q) => $q->whereIn('id', $data['ids'] ?? []))
            ->update(['read_at' => now()]);

        return response()->noContent();
    }
}
