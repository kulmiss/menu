<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos (PLAN.md §4.8): one row per uploaded image; the resized files live
 * on the public disk under media/{tenant}/{key}/.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 20)->default('public');
            $table->string('base_path', 120);
            $table->string('mime', 40);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->json('variants');
            $table->char('dominant_color', 7)->nullable();
            $table->json('alt')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
        });

        // Photos of categories and items. Single-column keys so deleting a
        // photo can empty the field; the API only accepts the restaurant's own.
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
        });
        Schema::table('items', function (Blueprint $table) {
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $table) => $table->dropForeign(['media_id']));
        Schema::table('categories', fn (Blueprint $table) => $table->dropForeign(['media_id']));
        Schema::dropIfExists('media');
    }
};
