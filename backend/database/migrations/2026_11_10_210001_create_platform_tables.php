<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admin (PLAN.md §4.1–4.2): payments, platform settings and
 * announcements, and the "log in as" record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('method', 20); // cash | bank | mobile_money | other
            $table->string('reference', 100)->nullable();
            $table->date('paid_at');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['paid_at']);
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_announcements', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('body')->nullable();
            $table->string('level', 10)->default('info'); // info | warning
            $table->json('plan_ids')->nullable(); // null = every restaurant
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->foreignId('platform_announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();
            $table->primary(['platform_announcement_id', 'user_id']);
        });

        Schema::create('impersonations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('super_admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 300);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->char('ip_hash', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonations');
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('platform_announcements');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('payments');
    }
};
