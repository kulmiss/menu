<?php

use App\Support\Legal\LegalText;
use App\Support\PlatformSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Last sign-in (owner, 2026-11-13, QUESTIONS #187): the dashboard summary keeps
 * each restaurant's latest team sign-in, and the Privacy Policy default gains a
 * line about it. The stored Privacy text is replaced ONLY when it is still
 * exactly the previous default (database/legal-history); edited texts stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_health', function (Blueprint $table) {
            $table->timestamp('last_sign_in_at')->nullable()->after('last_edit_at');
        });

        $stored = DB::table('platform_settings')->where('key', 'privacy')->value('value');
        $current = $stored === null ? null : json_decode((string) $stored, true);
        $old = (string) file_get_contents(database_path('legal-history/privacy-2026-11-13-v2.md'));
        $norm = fn (string $s) => trim(str_replace("\r\n", "\n", $s));
        if (is_string($current) && $norm($current) === $norm($old)) {
            PlatformSettings::save(['privacy' => LegalText::default('privacy')]);
        }
    }

    public function down(): void
    {
        Schema::table('tenant_health', fn (Blueprint $table) => $table->dropColumn('last_sign_in_at'));
    }
};
