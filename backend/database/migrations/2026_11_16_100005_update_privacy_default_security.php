<?php

use App\Support\Legal\LegalText;
use App\Support\PlatformSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Security hardening (SECURITY_REPORT.md): the default Privacy Policy gains the
 * security log (30 days, hashes only), security e-mails, hashed recovery codes,
 * the pending e-mail address and encrypted backups. Replaces the stored text
 * ONLY when it is still exactly the previous default (database/legal-history);
 * a text the Super Admin edited is left alone. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stored = DB::table('platform_settings')->where('key', 'privacy')->value('value');
        $current = $stored === null ? null : json_decode((string) $stored, true);
        $old = (string) file_get_contents(database_path('legal-history/privacy-2026-11-15-v3.md'));
        if (is_string($current) && self::same($current, $old)) {
            PlatformSettings::save(['privacy' => LegalText::default('privacy')]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: an unedited default stays a default.
    }

    private static function same(string $a, string $b): bool
    {
        $norm = fn (string $s) => trim(str_replace("\r\n", "\n", $s));

        return $norm($a) === $norm($b);
    }
};
