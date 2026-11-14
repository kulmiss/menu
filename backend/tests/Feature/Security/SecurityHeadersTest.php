<?php

use App\Enums\Role;
use App\Support\PublicMenu\InlineAssets;
use App\Support\Security\Csp;

/** SECURITY_REPORT.md H1 / M8: security headers on every kind of answer. */
it('sends strict headers and a locked-down CSP on API answers', function () {
    [$owner, $tenant] = member(Role::Owner);
    $res = $this->actingAs($owner)->getJson(t($tenant, 'items'))->assertOk();

    expect($res->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($res->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($res->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($res->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; frame-ancestors 'none'")
        ->and($res->headers->get('Cross-Origin-Resource-Policy'))->toBe('same-origin')
        ->and($res->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('gives site pages a nonce CSP that matches their inline scripts and forbids framing', function () {
    $res = $this->get('/')->assertOk();
    $csp = (string) $res->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([A-Za-z0-9]+)'/", $csp, $m);

    expect($m[1] ?? null)->not->toBeNull()
        ->and($csp)->toContain("frame-ancestors 'none'")->toContain("object-src 'none'")->not->toContain("'unsafe-inline' 'self'")
        ->and($csp)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+' https:\\/\\/challenges\\.cloudflare\\.com;/")
        ->and($res->headers->get('X-Frame-Options'))->toBe('DENY');
    // Every executable inline script carries the nonce.
    preg_match_all('/<script(?![^>]*type="application\/(?:ld\+)?json")([^>]*)>/', (string) $res->getContent(), $tags);
    expect($tags[1])->not->toBeEmpty();
    foreach ($tags[1] as $attrs) {
        expect($attrs)->toContain('nonce="'.$m[1].'"');
    }
    // A new nonce on every request.
    preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $this->get('/')->headers->get('Content-Security-Policy'), $m2);
    expect($m2[1])->not->toBe($m[1]);
});

it('lets the guest page run only its own inline script, by hash, and stay embeddable', function () {
    [, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $res = $this->get('/'.$tenant->slug)->assertOk();
    $html = (string) $res->getContent();

    preg_match('/<meta http-equiv="Content-Security-Policy" content="([^"]+)">/', $html, $meta);
    $policy = html_entity_decode($meta[1] ?? '', ENT_QUOTES);
    $hash = 'sha256-'.base64_encode(hash('sha256', InlineAssets::js(), true));
    expect($policy)->toContain("script-src '".$hash."'")->toContain("object-src 'none'")->toContain("base-uri 'none'")
        ->and(Csp::menuScriptHash())->toBe($hash)
        // The meta tag comes before any script or style, so it covers them.
        ->and(strpos($html, 'Content-Security-Policy'))->toBeLessThan(strpos($html, '<style>'))
        ->and(substr_count($html, '<script>'))->toBe(1)
        ->and($html)->toContain('<script>'.InlineAssets::js().'</script>')
        ->and($res->headers->get('X-Frame-Options'))->toBeNull()
        ->and($res->headers->get('Content-Security-Policy'))->toBe("object-src 'none'; base-uri 'none'")
        ->and($res->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('lets only the admin itself frame the appearance preview', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $res = $this->actingAs($owner)->get(t($tenant, 'appearance/preview'))->assertOk();

    expect($res->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($res->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");
});

it('sends HSTS on https only, and the session cookie is Secure on an https site', function () {
    expect($this->get('/')->headers->get('Strict-Transport-Security'))->toBeNull();
    expect($this->get('https://localhost/')->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000');
    config(['security.hsts_subdomains' => true]);
    expect($this->get('https://localhost/')->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');

    $config = fn (string $url) => (function () use ($url) {
        putenv('APP_URL='.$url);
        $_ENV['APP_URL'] = $_SERVER['APP_URL'] = $url;
        unset($_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']);
        putenv('SESSION_SECURE_COOKIE');

        return (require config_path('session.php'))['secure'];
    })();
    $old = env('APP_URL');
    try {
        expect($config('https://menu.example.com'))->toBeTrue()
            ->and($config('http://localhost'))->toBeFalse();
    } finally {
        putenv('APP_URL='.$old);
        $_ENV['APP_URL'] = $_SERVER['APP_URL'] = $old;
    }
});

it('ships the same static-file headers in .htaccess, the Nginx example and the CI speed server', function () {
    $htaccess = (string) file_get_contents(public_path('.htaccess'));
    $nginx = (string) file_get_contents(base_path('../docker/nginx/security-headers.conf')).file_get_contents(base_path('../docker/nginx/default.conf'));
    $ci = (string) file_get_contents(base_path('../ci/lighthouse/nginx.conf'));

    foreach (['X-Content-Type-Options "nosniff"', 'Referrer-Policy "strict-origin-when-cross-origin"', 'Permissions-Policy "camera=()'] as $h) {
        expect($htaccess)->toContain($h)->and($nginx)->toContain($h)->and($ci)->toContain($h);
    }
    foreach ([$htaccess, $nginx, $ci] as $conf) {
        expect($conf)->toContain("Content-Security-Policy \"object-src 'none'; base-uri 'none'\"");
    }
    expect($htaccess)->toContain('Strict-Transport-Security "max-age=31536000" "expr=%{HTTPS} == \'on\'"');
});
