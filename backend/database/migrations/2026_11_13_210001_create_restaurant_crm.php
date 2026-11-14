<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admin CRM (owner, 2026-11-13): tags, notes and a follow-up date per
 * restaurant. Platform-only data: restaurants never see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->char('color', 7)->default('#64748b');
            $table->timestamps();
        });
        Schema::create('platform_tag_tenant', function (Blueprint $table) {
            $table->foreignId('platform_tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->primary(['platform_tag_id', 'tenant_id']);
            $table->index('tenant_id');
        });
        Schema::create('restaurant_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->date('follow_up_on')->nullable()->index();
            $table->string('follow_up_reason', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['follow_up_on', 'follow_up_reason']));
        Schema::dropIfExists('restaurant_notes');
        Schema::dropIfExists('platform_tag_tenant');
        Schema::dropIfExists('platform_tags');
    }
};
