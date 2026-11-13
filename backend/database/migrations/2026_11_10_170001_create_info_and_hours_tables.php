<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant info, opening hours and holiday closures (PLAN.md §4.4–4.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('description')->nullable();
            $table->json('address')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->string('maps_url', 500)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('telegram', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->json('socials')->nullable();
            $table->string('wifi_name', 64)->nullable();
            $table->string('wifi_password', 64)->nullable();
            $table->boolean('show_wifi')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
        });

        // Several rows per day = breaks; closes_at <= opens_at = past midnight.
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday … 6 = Saturday
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'weekday']);
        });

        Schema::create('closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->json('note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closures');
        Schema::dropIfExists('opening_hours');
        Schema::dropIfExists('tenant_profiles');
    }
};
