<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Time-based prices (owner, 2026-11-14): "Happy hour", lunch prices…
 * A period has weekdays and a time window in the restaurant's time zone,
 * optionally one branch, and special prices for chosen dishes or sizes
 * (a fixed new price or a % off).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->json('weekdays'); // 0 = Sunday … 6 = Saturday
            $table->time('starts_at');
            $table->time('ends_at'); // earlier than starts_at = ends the next day
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
        });
        Schema::create('price_period_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('mode', 8); // fixed | percent
            $table->unsignedInteger('value'); // new price in minor units, or % off (1–90)
            $table->unique(['price_period_id', 'item_id', 'item_variant_id'], 'ppi_unique');
        });

        // The Free plan doesn't include it; paid plans do (Super Admin can switch it per plan).
        $free = DB::table('plans')->where('slug', 'free')->value('id');
        if ($free !== null) {
            DB::table('plan_features')->updateOrInsert(['plan_id' => $free, 'feature_key' => 'price_periods'], ['enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('price_period_items');
        Schema::dropIfExists('price_periods');
        DB::table('plan_features')->where('feature_key', 'price_periods')->delete();
    }
};
