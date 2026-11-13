<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the logo and name sit with a banner: "over" (on the photo) or
 * "under" (below it); null = the menu style's default (owner, 2026-11-11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->string('banner_text', 5)->nullable()->after('style');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->dropColumn('banner_text');
        });
    }
};
