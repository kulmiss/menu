<?php

use App\Support\Plans\PlanLadder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The new plan ladder: Free → Plus → Pro → Business (owner, 2026-11-13,
 * QUESTIONS #184). Safe for live data, and safe to run on any install:
 *
 * - Free is only ever improved (higher limits, more features).
 * - A Plus/Pro/Business plan that already exists gets the new definition
 *   when that takes nothing away, or when nobody is paying or on trial on it.
 * - Otherwise the old plan is kept unchanged for its restaurants (renamed
 *   "… (old)", hidden) and a new plan takes its name; when a restaurant's paid
 *   time ends, `plans:move-replaced` moves it to the new plan.
 * - Other public plans are hidden from the website; their restaurants keep them.
 * - Prices of plans that already exist are not changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedBigInteger('price_year_minor')->nullable()->after('price_minor');
            $table->foreignId('replaced_by_plan_id')->nullable()->after('sort')->constrained('plans')->nullOnDelete();
        });

        PlanLadder::apply();
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaced_by_plan_id');
            $table->dropColumn('price_year_minor');
        });
    }
};
