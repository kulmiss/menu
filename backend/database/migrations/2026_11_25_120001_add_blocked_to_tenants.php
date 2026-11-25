<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * "Block" is a stronger, owner-facing version of "suspend" (owner, 2026-11-25):
 * the owner can still sign in but the whole admin is replaced by a card that
 * shows the reason, and the public menu returns 404. Kept separate from suspend
 * so suspend's behaviour is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('suspended_reason');
            $table->string('blocked_reason', 500)->nullable()->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['blocked_at', 'blocked_reason']);
        });
    }
};
