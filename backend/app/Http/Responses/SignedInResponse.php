<?php

namespace App\Http\Responses;

use App\Support\Security\SuperAdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a sign-in (password, or the code after it): the page they came for,
 * else their own area (QUESTIONS #215) — /admin, or the Super Admin's address.
 */
class SignedInResponse implements LoginResponse, TwoFactorLoginResponse
{
    /** @param  bool  $afterCode  the two-factor step (its JSON answer is an empty 204, as Fortify's) */
    public function __construct(private readonly bool $afterCode = false) {}

    /** @param  Request  $request */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return $this->afterCode ? new JsonResponse('', 204) : new JsonResponse(['two_factor' => false]);
        }

        return redirect()->intended($request->user()?->is_super_admin ? SuperAdminAccess::url() : '/admin');
    }
}
