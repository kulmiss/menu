<?php

namespace App\Support\Security;

/**
 * Guards the addresses the Super Admin can type that the server then connects
 * to: the SMTP host and the S3 backup endpoint (SECURITY_REPORT.md M7, SSRF).
 *
 * - Private, link-local (cloud metadata 169.254.169.254), reserved and
 *   multicast addresses are refused, for names and for the IPs they resolve to.
 * - Loopback is allowed only for SMTP (cPanel's own mail server is "localhost"),
 *   and SMTP only on mail ports, so the "test e-mail" can't scan other services.
 * - Checked when saved and again right before connecting (a name can change).
 */
final class OutboundHost
{
    public const SMTP_PORTS = [25, 465, 587, 2525];

    /** A reason to refuse, or null when the host may be used. */
    public static function problem(string $host, bool $allowLoopback = false): ?string
    {
        $host = strtolower(trim($host, " \t\n\r\0\x0B[]."));
        if ($host === '') {
            return 'empty';
        }
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return $allowLoopback ? null : 'loopback';
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : self::resolve($host);
        foreach ($ips as $ip) {
            // ::ffff:10.0.0.1 is 10.0.0.1.
            if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m) === 1) {
                $ip = $m[1];
            }
            if (self::isLoopback($ip)) {
                if (! $allowLoopback) {
                    return 'loopback';
                }

                continue;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
                || self::inRange($ip, ['100.64.0.0/10', '169.254.0.0/16', '0.0.0.0/8', '224.0.0.0/4', '240.0.0.0/4'])) {
                return 'private';
            }
        }

        // A name that doesn't resolve yet is allowed: there is nothing to reach.
        return null;
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        $v4 = @gethostbynamel($host) ?: [];
        $v6 = [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $r) {
            if (isset($r['ipv6'])) {
                $v6[] = (string) $r['ipv6'];
            }
        }

        return array_values(array_unique([...$v4, ...$v6]));
    }

    private static function isLoopback(string $ip): bool
    {
        return $ip === '::1' || self::inRange($ip, ['127.0.0.0/8']);
    }

    /** @param  list<string>  $cidrs */
    private static function inRange(string $ip, array $cidrs): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv6: unique-local fc00::/7 and link-local fe80::/10 are caught by NO_PRIV / NO_RES.
            return false;
        }
        $n = ip2long($ip);
        foreach ($cidrs as $cidr) {
            [$base, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int) $bits);
            if (($n & $mask) === (ip2long($base) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
