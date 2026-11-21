<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells the sign-in check which page the password was typed on (QUESTIONS
 * #216): `super` for the Super Admin's own page, `restaurant` otherwise.
 * Each page only signs in its own kind of account (see FortifyServiceProvider).
 */
class SignInArea
{
    public const ATTRIBUTE = 'signin.area';

    public function handle(Request $request, Closure $next, string $area = 'restaurant'): Response
    {
        $request->attributes->set(self::ATTRIBUTE, $area === 'super' ? 'super' : 'restaurant');

        return $next($request);
    }

    /** The area of this request: every sign-in that isn't the Super Admin page is a restaurant one. */
    public static function of(Request $request): string
    {
        return $request->attributes->get(self::ATTRIBUTE) === 'super' ? 'super' : 'restaurant';
    }
}
