<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clicks on the landing page's main buttons, counted per day (owner,
 * 2026-11-17). Only a number per button and day: no cookie, no address,
 * nothing about the visitor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_clicks', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('button', 16);
            $table->unsignedInteger('count')->default(0);
            $table->unique(['day', 'button']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_clicks');
    }
};
