<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu styles M–Q (owner, 2026-11-19, QUESTIONS #232) come with their own
 * artwork: a header picture, textures and category icons. The restaurant can
 * turn it off; on by default. Used only by those five styles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->boolean('show_decoration')->default(true)->after('mono_accent');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->dropColumn('show_decoration');
        });
    }
};
