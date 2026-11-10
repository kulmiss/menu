<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily counters for Insights (PLAN.md §4.9). One row per restaurant, day,
 * branch, metric and subject; a visit adds 1 with a single upsert.
 * branch_id / subject_id use 0 for "none" so the unique key works (NULLs
 * would never collide). No IPs, no cookies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('branch_id')->default(0);
            $table->string('metric', 20); // menu_view | qr_scan | item_view
            $table->unsignedBigInteger('subject_id')->default(0); // item or QR code id
            $table->unsignedInteger('count')->default(0);
            $table->unique(['tenant_id', 'date', 'branch_id', 'metric', 'subject_id'], 'stats_daily_key');
            $table->index(['tenant_id', 'metric', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats_daily');
    }
};
