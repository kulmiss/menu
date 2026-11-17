<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Writes docs/api-reference.md from the routes, so the list of endpoints can
 * never fall behind: tests/Feature/Docs/ApiDocsTest.php fails when the file
 * differs from what this command would write.
 */
class ApiReferenceCommand extends Command
{
    protected $signature = 'docs:api-reference {--check : Exit 1 when docs/api-reference.md is out of date}';

    protected $description = 'Write docs/api-reference.md from the API routes';

    /** First path part after /api/v1 → section title. */
    private const AREAS = [
        'auth' => 'Sign-in and account (web session)',
        'mobile' => 'Mobile app (token)',
        'me' => 'Signed-in person',
        'signup' => 'Self sign-up',
        'invitations' => 'Team invitations',
        'impersonation' => '"Log in as"',
        'restaurant' => 'Restaurant (`restaurant/{tenant}/…`)',
        'super' => 'Super Admin (`super/…`)',
        'beacon' => 'Guest menu (public)',
    ];

    /** `60,1` → `60 a minute`; a named limiter → its name. */
    private static function limit(string $throttle): string
    {
        if ($throttle === '') {
            return '';
        }
        if (preg_match('/^(\d+),(\d+)$/', $throttle, $m)) {
            return $m[1].($m[2] === '1' ? ' a minute' : ' per '.$m[2].' minutes');
        }

        return '`'.$throttle.'` limiter';
    }

    public static function path(): string
    {
        return dirname(base_path()).'/docs/api-reference.md';
    }

    public function handle(Router $router): int
    {
        $text = self::render($router);
        if ($this->option('check')) {
            $same = is_file(self::path()) && file_get_contents(self::path()) === $text;
            $this->line($same ? 'docs/api-reference.md is up to date.' : 'docs/api-reference.md is out of date: run `php artisan docs:api-reference`.');

            return $same ? self::SUCCESS : self::FAILURE;
        }
        file_put_contents(self::path(), $text);
        $this->info('Wrote docs/api-reference.md');

        return self::SUCCESS;
    }

    /** @return list<array{method: string, uri: string, who: string, feature: string, limit: string, handler: string, area: string}> */
    public static function rows(Router $router): array
    {
        $rows = [];
        foreach ($router->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            $uri = substr($route->uri(), strlen('api/v1/'));
            $mw = $route->gatherMiddleware();
            $has = fn (string $name) => (bool) array_filter($mw, fn ($m) => is_string($m) && ($m === $name || str_starts_with($m, $name.':')));
            $param = fn (string $name) => implode(', ', array_map(
                fn ($m) => substr($m, strlen($name) + 1),
                array_values(array_filter($mw, fn ($m) => is_string($m) && str_starts_with($m, $name.':'))),
            ));
            $who = match (true) {
                $has('super') => 'Super Admin',
                $has('tenant') => 'restaurant team',
                $has('auth') => 'signed in',
                $has('guest') => 'signed out',
                default => 'anyone',
            };
            if ($has('mobile.token')) {
                $who .= ', app token';
            }
            if ($has('password.confirm')) {
                $who .= ', password confirmed';
            }
            $limit = $param('throttle');
            $action = $route->getActionName();
            $rows[] = [
                'method' => implode('|', array_diff($route->methods(), ['HEAD'])),
                'uri' => $uri,
                'who' => $who,
                'feature' => $param('feature'),
                'limit' => self::limit($limit),
                'handler' => $action === 'Closure' ? 'closure' : str_replace(['App\\Http\\Controllers\\', 'Laravel\\Fortify\\Http\\Controllers\\'], ['', 'Fortify\\'], $action),
                'area' => explode('/', $uri)[0],
            ];
        }
        usort($rows, fn ($a, $b) => [$a['uri'], $a['method']] <=> [$b['uri'], $b['method']]);

        return $rows;
    }

    public static function render(Router $router): string
    {
        $rows = self::rows($router);
        $out = [
            '# API reference (generated)',
            '',
            '<!-- Written by `php artisan docs:api-reference`. Do not edit by hand: a test fails when routes change and this file is not written again. -->',
            '',
            'Every `/api/v1` endpoint, from the routes. **Who**: who may call it (roles inside a restaurant are checked by each screen\'s policy). **Feature**: the feature switch it needs (`402 features.<key>` when off). **Limit**: how many requests are allowed. Create/change requests need an `Idempotency-Key` header. Request and answer examples for the app: [mobile-api.md](mobile-api.md).',
            '',
            '**'.count($rows).' endpoints.**',
        ];
        $areas = array_unique(array_column($rows, 'area'));
        $rank = fn (string $a) => array_search($a, array_keys(self::AREAS), true);
        usort($areas, fn ($a, $b) => [$rank($a) === false ? 99 : $rank($a), $a] <=> [$rank($b) === false ? 99 : $rank($b), $b]);
        foreach ($areas as $area) {
            $out[] = '';
            $out[] = '## '.(self::AREAS[$area] ?? 'Other (`'.$area.'`)');
            $out[] = '';
            $out[] = '| Method | Path | Who | Feature | Limit | Handler |';
            $out[] = '|---|---|---|---|---|---|';
            foreach ($rows as $r) {
                if ($r['area'] !== $area) {
                    continue;
                }
                $out[] = sprintf('| %s | `%s` | %s | %s | %s | %s |', $r['method'], $r['uri'], $r['who'], $r['feature'] === '' ? '' : '`'.$r['feature'].'`', $r['limit'], str_replace('|', '\\|', $r['handler']));
            }
        }

        return implode("\n", $out)."\n";
    }
}
