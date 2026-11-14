<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
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

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['message' => __('security.reset_link_generic')], 200);
    }
}
