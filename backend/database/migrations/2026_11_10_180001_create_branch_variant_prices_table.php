<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A branch's own price for one size of an item (QUESTIONS #78, owner
 * 2026-11-10). No row = the main menu's price for that size.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_variant_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('item_variant_id');
            $table->unsignedBigInteger('price_minor');
            $table->timestamps();
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'item_variant_id'])->references(['tenant_id', 'id'])->on('item_variants')->cascadeOnDelete();
            $table->unique(['branch_id', 'item_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_variant_prices');
    }
};
