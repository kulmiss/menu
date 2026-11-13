<?php

use App\Support\Legal\LegalText;
use App\Support\PlatformSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Default Terms of Service and Privacy Policy (owner, 2026-11-13, QUESTIONS #181).
 * Fills each text ONLY when it is missing or empty, so texts already written
 * in Super Admin → Settings are never overwritten. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (LegalText::PAGES as $key) {
            $stored = DB::table('platform_settings')->where('key', $key)->value('value');
            $current = $stored === null ? '' : json_decode((string) $stored, true);
            if (is_string($current) && trim($current) !== '') {
                continue;
            }
            PlatformSettings::save([$key => LegalText::default($key)]);
        }
    }

    public function down(): void
    {
        // Texts may have been edited since; leave them.
    }
};
