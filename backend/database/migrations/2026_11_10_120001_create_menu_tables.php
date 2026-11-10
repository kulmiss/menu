<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menus, categories, items, variants, add-ons, tags and allergens
 * (PLAN.md §4.6). Every child points at its parent through
 * (tenant_id, parent_id), so the database itself refuses a row that links
 * to another restaurant's data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('slug', 60);
            $table->string('schedule_mode', 10)->default('always');
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'sort']);
        });

        Schema::create('menu_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('menu_id');
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday … 6 = Saturday
            $table->time('starts_at');
            $table->time('ends_at'); // earlier than starts_at = past midnight
            $table->timestamps();
            $table->foreign(['tenant_id', 'menu_id'])->references(['tenant_id', 'id'])->on('menus')->cascadeOnDelete();
            $table->index(['tenant_id', 'menu_id', 'weekday']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('menu_id');
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('icon', 40)->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'menu_id'])->references(['tenant_id', 'id'])->on('menus')->cascadeOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'menu_id', 'sort']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('category_id');
            $table->json('name');
            $table->json('summary')->nullable();
            $table->json('description')->nullable();
            $table->unsignedBigInteger('price_minor')->nullable(); // null when variants carry the prices
            $table->unsignedBigInteger('media_id')->nullable();
            $table->unsignedSmallInteger('calories')->nullable();
            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->boolean('is_sold_out')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_new')->default(false);
            $table->date('new_until')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'category_id'])->references(['tenant_id', 'id'])->on('categories')->cascadeOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'category_id', 'sort']);
        });

        Schema::create('item_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('item_id');
            $table->json('name');
            $table->unsignedBigInteger('price_minor');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'item_id'])->references(['tenant_id', 'id'])->on('items')->cascadeOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'item_id', 'sort']);
        });

        Schema::create('addon_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->unsignedTinyInteger('min_choices')->default(0);
            $table->unsignedTinyInteger('max_choices')->nullable(); // null = no limit
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('addon_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('addon_group_id');
            $table->json('name');
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->boolean('is_sold_out')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'addon_group_id'])->references(['tenant_id', 'id'])->on('addon_groups')->cascadeOnDelete();
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('item_addon_group', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('addon_group_id');
            $table->unsignedInteger('sort')->default(0);
            $table->primary(['item_id', 'addon_group_id']);
            $table->foreign(['tenant_id', 'item_id'])->references(['tenant_id', 'id'])->on('items')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'addon_group_id'])->references(['tenant_id', 'id'])->on('addon_groups')->cascadeOnDelete();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 30)->nullable(); // system tag (translated by the app) or null for a custom one
            $table->json('name')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('item_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('tag_id');
            $table->primary(['item_id', 'tag_id']);
            $table->foreign(['tenant_id', 'item_id'])->references(['tenant_id', 'id'])->on('items')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'tag_id'])->references(['tenant_id', 'id'])->on('tags')->cascadeOnDelete();
        });

        Schema::create('item_allergens', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('item_id');
            $table->string('allergen', 20); // EU-14 key, see config('menu.allergens')
            $table->primary(['item_id', 'allergen']);
            $table->foreign(['tenant_id', 'item_id'])->references(['tenant_id', 'id'])->on('items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_allergens');
        Schema::dropIfExists('item_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('item_addon_group');
        Schema::dropIfExists('addon_options');
        Schema::dropIfExists('addon_groups');
        Schema::dropIfExists('item_variants');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('menu_schedules');
        Schema::dropIfExists('menus');
    }
};
