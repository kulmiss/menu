<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Log in as" in the mobile app (QUESTIONS #188): a 2-hour token for the
 * owner that remembers which Super Admin opened it and for which restaurant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('impersonator_id')->nullable()->after('app_version')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('impersonation_tenant_id')->nullable()->after('impersonator_id');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('impersonator_id');
            $table->dropColumn('impersonation_tenant_id');
        });
    }
};
