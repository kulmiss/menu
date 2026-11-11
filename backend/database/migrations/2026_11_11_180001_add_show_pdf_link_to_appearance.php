<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Download menu (PDF)" link on the guest page (owner, 2026-11-11: default off). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->boolean('show_pdf_link')->default(false)->after('show_share');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->dropColumn('show_pdf_link');
        });
    }
};
