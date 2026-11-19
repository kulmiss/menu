<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * First-customer experience (owner, 2026-11-19, QUESTIONS #235):
 * "Add my menu for me" requests (photos of the paper menu, kept privately),
 * and which welcome e-mail (day 1, 3, 7) a restaurant got last.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('files');
            $table->string('status', 16)->default('new'); // new | working | done
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedTinyInteger('onboarding_step')->default(0)->after('qr_downloaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_requests');
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('onboarding_step'));
    }
};
