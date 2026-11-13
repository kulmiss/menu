<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Monthly Insights report by e-mail (owner, 2026-11-14, QUESTIONS #180): on/off and the last month sent. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('insights_report')->default(false);
            $table->string('insights_report_sent', 7)->nullable(); // YYYY-MM of the last report sent
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['insights_report', 'insights_report_sent']);
        });
    }
};
