<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * New password saved from the reset page (QUESTIONS #222): the sign-in page
 * says so ("welcome" for a new owner's first password).
 */
class PasswordChangedResponse implements PasswordResetResponse
{
    public function __construct(protected string $status = '') {}

    /** @param  Request  $request */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => trans($this->status)], 200);
        }

        return redirect('/signin?password='.($request->boolean('welcome') ? 'welcome' : 'changed'));
    }
}
