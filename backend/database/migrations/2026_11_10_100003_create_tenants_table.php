<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 120);
            $table->string('status', 20)->default('active');
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('paid_until')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspended_reason', 500)->nullable();
            $table->string('timezone', 64)->default('Africa/Addis_Ababa');
            $table->string('default_locale', 10)->default('en');
            $table->char('currency_code', 3)->default('ETB');
            $table->json('currency_format')->nullable();
            $table->unsignedBigInteger('theme_id')->nullable();
            $table->unsignedInteger('cache_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_slug_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('old_slug', 50)->unique();
            $table->timestamps();
        });

        Schema::create('tenant_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 50);
            $table->boolean('enabled');
            $table->timestamps();
            $table->unique(['tenant_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_overrides');
        Schema::dropIfExists('tenant_slug_history');
        Schema::dropIfExists('tenants');
    }
};
