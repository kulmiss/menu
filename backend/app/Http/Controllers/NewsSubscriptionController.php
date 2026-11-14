<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Unsubscribe from news e-mails (signed link in every campaign e-mail, and
 * the one-click List-Unsubscribe header), plus the switch in the restaurant
 * admin's profile. Account e-mails are not affected.
 */
class NewsSubscriptionController extends Controller
{
    public function show(Request $request, User $user): View
    {
        return $this->page($request, $user, 'confirm');
    }

    public function unsubscribe(Request $request, User $user): View
    {
        $user->forceFill(['news_unsubscribed_at' => $user->news_unsubscribed_at ?? now()])->save();

        return $this->page($request, $user, 'done');
    }

    public function resubscribe(Request $request, User $user): View
    {
        $user->forceFill(['news_unsubscribed_at' => null])->save();

        return $this->page($request, $user, 'resubscribed');
    }

    /** PUT /api/v1/me/news {subscribed}: the profile switch. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['subscribed' => ['required', 'boolean']]);
        $user = $request->user();
        $user->forceFill(['news_unsubscribed_at' => $data['subscribed'] ? null : ($user->news_unsubscribed_at ?? now())])->save();

        return response()->json(['data' => ['news_subscribed' => $data['subscribed']]]);
    }

    private function page(Request $request, User $user, string $state): View
    {
        return view('site.news', [
            'platform' => (string) PlatformSettings::get('name'),
            'email' => $user->email,
            'state' => $state,
            'unsubscribeUrl' => URL::signedRoute('news.unsubscribe.do', ['user' => $user->id]),
            'resubscribeUrl' => URL::signedRoute('news.resubscribe', ['user' => $user->id]),
        ]);
    }
}
