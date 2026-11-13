<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu look (PLAN.md §4.4): theme presets offered by the platform and each
 * restaurant's own settings. Fonts are system font stacks only (§15.2.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('preset'); // primary, font, layout
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('tenant_appearance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->char('primary_color', 7)->default('#b4432a');
            $table->string('font', 20)->default('system');
            $table->string('layout', 10)->default('list');
            $table->boolean('show_prices')->default(true);
            $table->boolean('show_photos')->default(true);
            $table->boolean('show_descriptions')->default(true);
            $table->boolean('show_calories')->default(true);
            $table->foreignId('theme_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
        });

        $now = now();
        DB::table('themes')->insert(array_map(fn (array $t) => [
            'name' => json_encode(['en' => $t[0]]),
            'preset' => json_encode(['primary' => $t[1], 'font' => $t[2], 'layout' => $t[3]]),
            'sort' => $t[4],
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            ['Classic', '#b4432a', 'system', 'list', 0],
            ['Bistro', '#2f5d50', 'serif', 'large', 1],
            ['Fresh', '#1d4ed8', 'rounded', 'grid', 2],
            ['Espresso', '#6b3e26', 'serif', 'list', 3],
            ['Berry', '#9d174d', 'rounded', 'grid', 4],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_appearance');
        Schema::dropIfExists('themes');
    }
};
