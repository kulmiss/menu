<?php

use App\Models\QrCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branches, tables, QR codes, menu ↔ branch and per-branch item overrides
 * (PLAN.md §4.5, §4.6). Every restaurant gets a default branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 40);
            $table->json('name');
            $table->json('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('maps_url', 500)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('branch_id');
            $table->string('label', 30);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->cascadeOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['branch_id', 'label']);
        });

        // Codes are never reused: rows are soft-deleted, the code stays taken.
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->char('code', 8)->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 60)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'id']);
        });

        // No rows for a menu = shown at every branch (QUESTIONS #59).
        Schema::create('menu_branch', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('branch_id');
            $table->primary(['menu_id', 'branch_id']);
            $table->foreign(['tenant_id', 'menu_id'])->references(['tenant_id', 'id'])->on('menus')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->cascadeOnDelete();
        });

        // null = same as the main menu.
        Schema::create('branch_item_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->boolean('is_sold_out')->nullable();
            $table->boolean('is_hidden')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'item_id'])->references(['tenant_id', 'id'])->on('items')->cascadeOnDelete();
            $table->unique(['branch_id', 'item_id']);
            $table->index(['tenant_id', 'branch_id']);
        });

        // Existing restaurants: a default branch.
        $now = now();
        foreach (DB::table('tenants')->pluck('id') as $id) {
            DB::table('branches')->insert([
                'tenant_id' => $id, 'slug' => 'main', 'name' => json_encode(['en' => 'Main']),
                'is_default' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('qr_codes')->insert([
                'tenant_id' => $id, 'code' => QrCode::newCode(), 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_item_overrides');
        Schema::dropIfExists('menu_branch');
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('branches');
    }
};
