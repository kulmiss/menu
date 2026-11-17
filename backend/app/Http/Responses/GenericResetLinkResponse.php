<?php

namespace App\Http\Responses;

use App\Support\Security\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * "Forgot password" gives the same answer for every address, so it can't be used
 * to find out who has an account (SECURITY_REPORT.md M1). Unknown address, link
 * sent, or link already sent a minute ago: all look alike.
 */
class GenericResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(protected string $status = '') {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        SecurityLog::event('password.reset_requested', null, (string) $request->input('email'), ['status' => basename(str_replace('.', '/', $this->status)) ?: null]);

        // The "Forgot password" page (QUESTIONS #222): the same answer, on the page.
        if (! $request->wantsJson()) {
            return back()->withInput($request->only('email'))->with('status', __('account.forgot.sent', ['email' => (string) $request->input('email')]));
        }

        return new JsonResponse(['message' => __('security.reset_link_generic')], 200);
    }
}
