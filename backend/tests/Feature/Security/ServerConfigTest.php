<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Log;

/** SECURITY_REPORT.md M9, L1, L2, L3, L5: server-side defaults. */
it('sends no CORS headers, so other sites cannot read the API from a browser', function () {
    $res = $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'GET'])->options('/api/v1/me');
    expect($res->headers->get('Access-Control-Allow-Origin'))->toBeNull();
    $get = $this->withHeaders(['Origin' => 'https://evil.example'])->getJson('/api/v1/mobile/config');
    expect($get->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});

it('publishes a valid security.txt that has not expired', function () {
    $txt = (string) file_get_contents(public_path('.well-known/security.txt'));
    preg_match('/^Expires: (.+)$/m', $txt, $m);

    expect($txt)->toContain('Contact: mailto:info@kulmiss.com')
        // RFC 9116: no more than a year ahead. When this fails, move the date forward.
        ->and(strtotime($m[1]))->toBeGreaterThan(time())->toBeLessThan(time() + 366 * 86400);
});

it('turns error details off on a live site even when APP_DEBUG=true', function () {
    Log::spy();
    config(['app.debug' => true]);
    AppServiceProvider::guardDebug(production: false);
    expect(config('app.debug'))->toBeTrue();

    AppServiceProvider::guardDebug(production: true);
    AppServiceProvider::guardDebug(production: true);
    expect(config('app.debug'))->toBeFalse();
    Log::shouldHaveReceived('critical')->once();
});

it('trusts the proxy only when TRUSTED_PROXIES is set', function () {
    expect((string) file_get_contents(base_path('bootstrap/app.php')))->toContain("env('TRUSTED_PROXIES', '')");

    // Without it, X-Forwarded-For is ignored: a client can't fake its address for the rate limits.
    $ip = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->withHeaders(['X-Forwarded-For' => '1.2.3.4'])
        ->get('/up')->baseRequest->ip();
    expect($ip)->toBe('203.0.113.9');
});

it('never runs PHP from uploaded or generated folders (.htaccess and Nginx)', function () {
    $htaccess = (string) file_get_contents(public_path('.htaccess'));
    $nginx = (string) file_get_contents(base_path('../docker/nginx/default.conf'));

    expect($htaccess)->toContain('RewriteRule ^(media|menu-cache|menu-assets|app|images|landing)/.*\.(php\d?|phtml|phar|pht|inc|cgi|pl|py|sh)$ - [F,L,NC]')
        // The guard comes before the front-controller rules.
        ->and(strpos($htaccess, '(media|menu-cache'))->toBeLessThan(strpos($htaccess, 'RewriteRule ^ index.php'))
        ->and($nginx)->toContain('location ~ ^/(index|qr)\.php$')->not->toMatch('/location ~ \\\\\.php\$ \{\s*fastcgi/');
});
