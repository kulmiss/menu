<?php

namespace App\Support\Help;

use App\Models\HelpArticle;
use App\Models\Tenant;
use App\Support\Features;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Help articles (owner, 2026-11-16, QUESTIONS #194).
 *
 * Defaults ship as Markdown in resources/help/{audience}/{slug}.md with a
 * small header (see resources/help/README.md). The Super Admin can change any
 * article or add new ones; those are rows in `help_articles` and win over the
 * file with the same slug. Articles about a switched-off feature are hidden.
 */
class HelpLibrary
{
    public const AUDIENCES = ['restaurant', 'platform'];

    public const ROLES = ['owner', 'manager', 'staff'];

    /** Header keys an article may have. */
    public const FIELDS = ['title', 'topic', 'summary', 'order', 'features', 'roles', 'pages', 'app', 'keywords', 'public'];

    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $files = [];

    public static function dir(string $audience): string
    {
        return resource_path('help/'.$audience);
    }

    /**
     * Topics in their order (key → title key in lang/en/help.php).
     *
     * @return list<string>
     */
    public static function topics(string $audience): array
    {
        return array_keys((array) __('help.topics.'.$audience));
    }

    /**
     * Default articles from the files.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(string $audience): array
    {
        self::audience($audience);
        if (! isset(self::$files[$audience])) {
            $out = [];
            foreach (glob(self::dir($audience).'/*.md') ?: [] as $file) {
                $slug = basename($file, '.md');
                $out[$slug] = ['slug' => $slug, ...self::parse((string) file_get_contents($file)), 'source' => 'default'];
            }
            uasort($out, fn ($a, $b) => self::sortKey($a, $audience) <=> self::sortKey($b, $audience));
            self::$files[$audience] = $out;
        }

        return self::$files[$audience];
    }

    /**
     * Every article, defaults with the Super Admin's changes, hidden ones included.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(string $audience): array
    {
        $out = self::defaults($audience);
        $rows = Schema::hasTable('help_articles') ? HelpArticle::where('audience', $audience)->get() : collect();
        foreach ($rows as $row) {
            $base = $out[$row->slug] ?? null;
            $out[$row->slug] = [
                'slug' => $row->slug,
                'title' => $row->title,
                'topic' => $row->topic,
                'summary' => (string) $row->summary,
                'order' => (int) $row->sort,
                'features' => (array) ($row->features ?? []),
                'roles' => (array) ($row->roles ?? []),
                'pages' => (array) ($row->pages ?? []),
                'app' => (array) ($row->app_pages ?? []),
                'keywords' => (array) ($row->keywords ?? []),
                'public' => false,
                'body' => (string) $row->body,
                'hidden' => (bool) $row->is_hidden,
                'source' => $base === null ? 'custom' : 'changed',
                'updated_at' => $row->updated_at?->toIso8601String(),
            ];
        }
        uasort($out, fn ($a, $b) => self::sortKey($a, $audience) <=> self::sortKey($b, $audience));

        return $out;
    }

    /**
     * What this person may read: not hidden, every feature on, their role allowed.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function visible(string $audience, ?Tenant $tenant = null, ?string $role = null, bool $publicOnly = false): array
    {
        return array_filter(self::all($audience), fn (array $a) => empty($a['hidden'])
            && (! $publicOnly || ! empty($a['public']))
            && self::featuresOn((array) $a['features'], $tenant)
            && ($role === null || $a['roles'] === [] || in_array($role, (array) $a['roles'], true)));
    }

    /** @param  list<string>  $features  `sold_out`, or `!sold_out` for "only when off" */
    public static function featuresOn(array $features, ?Tenant $tenant): bool
    {
        foreach ($features as $f) {
            $not = str_starts_with($f, '!');
            $key = ltrim($f, '!');
            $on = $tenant !== null && array_key_exists($key, Features::DEFAULTS) ? Features::enabled($tenant, $key) : Features::globallyOn($key);
            if ($on === $not) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse a file: `---` header lines `key: value` (lists comma-separated), then the body.
     *
     * @return array<string, mixed>
     */
    public static function parse(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);
        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $text, $m)) {
            throw new InvalidArgumentException('A help article must start with a --- header.');
        }
        $meta = ['features' => [], 'roles' => [], 'pages' => [], 'app' => [], 'keywords' => [], 'summary' => '', 'order' => 50, 'public' => false];
        foreach (explode("\n", $m[1]) as $line) {
            if (trim($line) === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            if (! in_array($key, self::FIELDS, true)) {
                throw new InvalidArgumentException("Unknown help header \"{$key}\".");
            }
            $meta[$key] = match ($key) {
                'features', 'roles', 'pages', 'app', 'keywords' => array_values(array_filter(array_map('trim', explode(',', $value)))),
                'order' => (int) $value,
                'public' => $value === 'true',
                default => $value,
            };
        }

        return [...$meta, 'body' => trim($m[2])."\n", 'hidden' => false, 'updated_at' => null];
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array{0: int, 1: int, 2: string}
     */
    private static function sortKey(array $a, string $audience): array
    {
        $topic = array_search($a['topic'] ?? '', self::topics($audience), true);

        return [$topic === false ? 999 : $topic, (int) ($a['order'] ?? 50), (string) ($a['title'] ?? '')];
    }

    public static function audience(string $audience): void
    {
        if (! in_array($audience, self::AUDIENCES, true)) {
            throw new InvalidArgumentException("Unknown help audience {$audience}.");
        }
    }

    public static function forget(): void
    {
        self::$files = [];
    }
}
