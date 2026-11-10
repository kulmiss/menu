<?php

namespace App\Support\Qr;

use Illuminate\Support\Facades\DB;

/**
 * The file `public/qr.php` reads to answer /q/{code} without starting
 * Laravel or the database (PLAN.md §15.2.11): code → guest page path.
 * Rebuilt (once per request, after the commit) whenever a code, branch or
 * restaurant address changes.
 */
class QrMap
{
    private static bool $pending = false;

    public static function path(): string
    {
        return storage_path('app/qr-map.php');
    }

    /** Ask for a rebuild once the current change is saved. */
    public static function touch(): void
    {
        if (self::$pending) {
            return;
        }
        self::$pending = true;
        DB::afterCommit(function () {
            self::$pending = false;
            self::rebuild();
        });
    }

    /** @return array<string, string> code → path */
    public static function entries(): array
    {
        $rows = DB::table('qr_codes as q')
            ->join('tenants as t', 't.id', '=', 'q.tenant_id')
            ->leftJoin('branches as b', 'b.id', '=', 'q.branch_id')
            ->leftJoin('dining_tables as d', 'd.id', '=', 'q.dining_table_id')
            ->leftJoin('branches as db', 'db.id', '=', 'd.branch_id')
            ->whereNull('q.deleted_at')
            ->whereNull('t.deleted_at')
            ->get(['q.code', 't.slug', 'b.slug as branch', 'b.is_default as branch_default', 'db.slug as table_branch', 'db.is_default as table_branch_default']);

        $map = [];
        foreach ($rows as $row) {
            [$branch, $default] = $row->table_branch !== null
                ? [$row->table_branch, (bool) $row->table_branch_default]
                : [$row->branch, (bool) $row->branch_default];
            $map[(string) $row->code] = '/'.$row->slug.($branch !== null && ! $default ? '/'.$branch : '').'#q='.$row->code;
        }

        return $map;
    }

    public static function rebuild(): void
    {
        $file = self::path();
        $tmp = $file.'.'.bin2hex(random_bytes(4));
        $lines = [];
        foreach (self::entries() as $code => $path) {
            // Codes and slugs are [A-Za-z0-9-]; escaping keeps the file valid regardless.
            $lines[] = "'".addcslashes($code, "'\\")."'=>'".addcslashes($path, "'\\")."',";
        }
        file_put_contents($tmp, "<?php return [\n".implode("\n", $lines)."\n];\n");
        rename($tmp, $file);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }
}
