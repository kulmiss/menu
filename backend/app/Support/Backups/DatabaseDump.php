<?php

namespace App\Support\Backups;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * A plain SQL dump of the MySQL/MariaDB database, written in PHP (no
 * mysqldump needed on shared hosting). Restore with the mysql client or
 * phpMyAdmin (see docs/deploy.md). Caches, sessions and queued jobs are
 * dumped as empty tables.
 */
class DatabaseDump
{
    private const SKIP_DATA = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches'];

    /** @param  resource  $out */
    public static function write($out): int
    {
        $pdo = DB::connection()->getPdo();
        $rows = 0;
        fwrite($out, '-- Backup of database `'.DB::connection()->getDatabaseName().'` made '.now()->toIso8601String()."\n");
        fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = array_map(fn ($r) => array_values((array) $r)[0], DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
        foreach ($tables as $table) {
            $create = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
            fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n".array_values($create)[1].";\n\n");
            if (in_array($table, self::SKIP_DATA, true)) {
                continue;
            }
            $stmt = $pdo->query("SELECT * FROM `{$table}`", PDO::FETCH_ASSOC);
            if ($stmt === false) {
                continue;
            }
            $batch = [];
            $columns = null;
            foreach ($stmt as $row) {
                $columns ??= '(`'.implode('`, `', array_keys($row)).'`)';
                $batch[] = '('.implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)).')';
                $rows++;
                if (count($batch) === 200) {
                    fwrite($out, "INSERT INTO `{$table}` {$columns} VALUES\n".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }
            if ($batch !== []) {
                fwrite($out, "INSERT INTO `{$table}` {$columns} VALUES\n".implode(",\n", $batch).";\n");
            }
            fwrite($out, "\n");
        }
        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");

        return $rows;
    }
}
