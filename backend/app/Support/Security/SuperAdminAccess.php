<?php

namespace App\Support\Security;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Where the Super Admin area lives and who may reach it (QUESTIONS #215–#217):
 * `SUPER_ADMIN_PATH` and the optional `SUPER_ADMIN_IPS` allow-list in .env.
 */
class SuperAdminAccess
{
    /** The area's web path without slashes, e.g. "super". */
    public static function path(): string
    {
        return (string) config('menu.super_admin.path');
    }

    /** "/super", "/super/restaurants" … */
    public static function url(string $tail = ''): string
    {
        $tail = ltrim($tail, '/');

        return '/'.self::path().($tail === '' ? '' : '/'.$tail);
    }

    /** @return list<string> */
    public static function ips(): array
    {
        return config('menu.super_admin.ips');
    }

    /** True when the allow-list is off or the request comes from a listed address. */
    public static function ipAllowed(Request $request): bool
    {
        $ips = self::ips();

        return $ips === [] || ($request->ip() !== null && IpUtils::checkIp($request->ip(), $ips));
    }
}
