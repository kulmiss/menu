<?php

use App\Support\Legal\LegalText;
use App\Support\PlatformSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The plan ladder (QUESTIONS #184) changes how the Terms describe the Free and
 * paid plans. Replaces the stored Terms ONLY when they are still exactly the
 * first default (database/legal-history); edited Terms are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stored = DB::table('platform_settings')->where('key', 'terms')->value('value');
        $current = $stored === null ? null : json_decode((string) $stored, true);
        $old = (string) file_get_contents(database_path('legal-history/terms-2026-11-13-v1.md'));
        $norm = fn (string $s) => trim(str_replace("\r\n", "\n", $s));
        if (is_string($current) && $norm($current) === $norm($old)) {
            PlatformSettings::save(['terms' => LegalText::default('terms')]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: an unedited default stays a default.
    }
};
