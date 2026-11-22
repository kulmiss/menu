<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Landing 2 (owner, 2026-11-22): real restaurants on the website, managed in Super Admin →
 * Landing 2. Shown only with "permission given", and only once at least 3 have it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('city', 60)->nullable();
            $table->string('quote_en', 300)->nullable();
            $table->string('quote_am', 300)->nullable();
            $table->string('logo', 200)->nullable(); // folder on the public disk (64.webp, 128.webp)
            $table->boolean('permission')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_restaurants');
    }
};
