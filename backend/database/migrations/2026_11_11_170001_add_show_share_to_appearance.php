<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Share menu" button on the guest page (owner, 2026-11-11: default on). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->boolean('show_share')->default(true)->after('show_calories');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->dropColumn('show_share');
        });
    }
};
