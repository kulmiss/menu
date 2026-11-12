<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Logo and banner can be switched off without deleting them (owner, 2026-11-12, style D). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->boolean('show_logo')->default(true)->after('show_other_branches');
            $table->boolean('show_banner')->default(true)->after('show_logo');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->dropColumn(['show_logo', 'show_banner']);
        });
    }
};
