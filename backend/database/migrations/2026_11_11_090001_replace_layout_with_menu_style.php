<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three guest-menu styles replace the old layout switch (owner,
 * 2026-11-11): list → bistro (C), grid → grid (B), large → editorial (A).
 */
return new class extends Migration
{
    private const MAP = ['list' => 'bistro', 'grid' => 'grid', 'large' => 'editorial'];

    public function up(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->string('style', 10)->default('grid')->after('font');
        });
        foreach (self::MAP as $old => $new) {
            DB::table('tenant_appearance')->where('layout', $old)->update(['style' => $new]);
        }
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
        $this->presets(self::MAP, 'layout', 'style');
    }

    public function down(): void
    {
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->string('layout', 10)->default('list')->after('font');
        });
        foreach (array_flip(self::MAP) as $new => $old) {
            DB::table('tenant_appearance')->where('style', $new)->update(['layout' => $old]);
        }
        Schema::table('tenant_appearance', function (Blueprint $table) {
            $table->dropColumn('style');
        });
        $this->presets(array_flip(self::MAP), 'style', 'layout');
    }

    /** @param array<string, string> $map */
    private function presets(array $map, string $from, string $to): void
    {
        foreach (DB::table('themes')->get(['id', 'preset']) as $theme) {
            $preset = json_decode((string) $theme->preset, true) ?: [];
            $preset[$to] = $map[$preset[$from] ?? ''] ?? ($to === 'style' ? 'grid' : 'grid');
            unset($preset[$from]);
            DB::table('themes')->where('id', $theme->id)->update(['preset' => json_encode($preset)]);
        }
    }
};
