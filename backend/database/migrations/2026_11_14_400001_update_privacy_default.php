<?php

use App\Support\Legal\LegalText;
use App\Support\PlatformSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The admin fonts are self-hosted now (QUESTIONS #182), so the default Privacy
 * Policy no longer mentions Google Fonts. Replaces the stored text ONLY when it
 * is still exactly the first default (database/legal-history); a text the
 * Super Admin edited is left alone. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stored = DB::table('platform_settings')->where('key', 'privacy')->value('value');
        $current = $stored === null ? null : json_decode((string) $stored, true);
        $old = (string) file_get_contents(database_path('legal-history/privacy-2026-11-13-v1.md'));
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
