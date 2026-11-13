<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile app sign-in (QUESTIONS #187): one Sanctum token per device, shown in
 * Profile → Devices with its platform and app version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('platform', 16)->nullable()->after('abilities');
            $table->string('app_version', 20)->nullable()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['platform', 'app_version']);
        });
    }
};
