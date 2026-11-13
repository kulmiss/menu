<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Up to 5 photos per dish (owner, 2026-11-11). The first one is the main
 * photo and stays in items.media_id too, so lists and the guest page keep
 * reading one column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_photos', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('media_id');
            $table->unsignedTinyInteger('sort')->default(0);
            $table->primary(['item_id', 'media_id']);
            $table->index(['tenant_id', 'media_id']);
            $table->foreign(['tenant_id', 'item_id'])->references(['tenant_id', 'id'])->on('items')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'media_id'])->references(['tenant_id', 'id'])->on('media')->cascadeOnDelete();
        });

        // Existing main photos become photo 1.
        DB::table('items')->whereNotNull('media_id')->orderBy('id')->select(['id', 'tenant_id', 'media_id'])
            ->chunk(500, fn ($rows) => DB::table('item_photos')->insert($rows->map(fn ($r) => [
                'tenant_id' => $r->tenant_id, 'item_id' => $r->id, 'media_id' => $r->media_id, 'sort' => 0,
            ])->all()));
    }

    public function down(): void
    {
        Schema::dropIfExists('item_photos');
    }
};
