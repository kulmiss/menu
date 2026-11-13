<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dashboard setup checklist: "QR codes downloaded" and "Hide" (owner, 2026-11-11). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('qr_downloaded_at')->nullable();
            $table->timestamp('setup_hidden_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['qr_downloaded_at', 'setup_hidden_at']);
        });
    }
};
