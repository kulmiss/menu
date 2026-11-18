<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Options of the menu styles H–L (owner, 2026-11-18, QUESTIONS #229): the menu
 * direction (H · Arabic), the Halal badge and Ramadan mode (I · Islamic), and
 * greyscale photos and the brand-colour accent (K · Black & White). Each one is
 * used only in its own style; off (auto) by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->string('menu_direction', 4)->default('auto')->after('show_banner');
            $table->boolean('show_halal')->default(false)->after('menu_direction');
            $table->boolean('ramadan_mode')->default(false)->after('show_halal');
            $table->boolean('photos_greyscale')->default(false)->after('ramadan_mode');
            $table->boolean('mono_accent')->default(false)->after('photos_greyscale');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->dropColumn(['menu_direction', 'show_halal', 'ramadan_mode', 'photos_greyscale', 'mono_accent']);
        });
    }
};
