<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** The welcome tour was seen (or skipped), so it doesn't open again by itself (QUESTIONS #195). */
class OnboardingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'tour' => ['required', Rule::in(['web_tour', 'app_tour'])],
            'seen' => ['required', 'boolean'],
        ]);
        $user = $request->user();
        $onboarding = (array) ($user->onboarding ?? []);
        $onboarding[$data['tour']] = $data['seen'] ? now()->toIso8601String() : null;
        $user->forceFill(['onboarding' => array_filter($onboarding)])->save();

        return response()->noContent();
    }
}
