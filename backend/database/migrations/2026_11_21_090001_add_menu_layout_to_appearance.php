<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layouts (owner, 2026-11-21): photo cards in two columns (grid) or a list
 * with small photos (list), instead of the style's own layout. Null keeps the
 * style's own, so no restaurant's menu changes. The text layout is the
 * existing "show_photos" switch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->string('menu_layout', 8)->nullable()->after('show_photos');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->dropColumn('menu_layout');
        });
    }
};
