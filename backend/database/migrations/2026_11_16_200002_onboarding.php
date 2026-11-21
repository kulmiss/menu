<?php

use App\Support\PlatformSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * First-time guidance (QUESTIONS #195): which tours a person has seen, and
 * the Super Admin setup wizard. A platform that already has restaurants is
 * set up, so the wizard only opens on a new install.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('onboarding')->nullable()->after('locale');
        });
        if (DB::table('tenants')->exists()) {
            PlatformSettings::save(['setup' => ['done_at' => now()->toIso8601String(), 'card_hidden' => false]]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('onboarding'));
    }
};
