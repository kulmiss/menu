<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for the Super Admin dashboard's platform-wide questions (owner, 2026-11-11). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('trial_ends_at');
            $table->index('paid_until');
        });
        // Views/scans of all restaurants in a date range (the per-restaurant index starts with tenant_id).
        Schema::table('stats_daily', function (Blueprint $table) {
            $table->index(['metric', 'date', 'tenant_id'], 'stats_daily_metric_date');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['trial_ends_at']);
            $table->dropIndex(['paid_until']);
        });
        Schema::table('stats_daily', function (Blueprint $table) {
            $table->dropIndex('stats_daily_metric_date');
        });
    }
};
