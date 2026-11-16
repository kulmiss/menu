<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Help centre (QUESTIONS #194): the Super Admin's changes to the default articles, and "Was this helpful?". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 20);
            $table->string('slug', 80);
            $table->string('topic', 40);
            $table->string('title', 160);
            $table->string('summary', 300)->nullable();
            $table->mediumText('body');
            $table->json('features')->nullable();
            $table->json('roles')->nullable();
            $table->json('pages')->nullable();
            $table->json('app_pages')->nullable();
            $table->json('keywords')->nullable();
            $table->unsignedSmallInteger('sort')->default(50);
            $table->boolean('is_hidden')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['audience', 'slug']);
        });

        Schema::create('help_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 20);
            $table->string('slug', 80);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('helpful');
            $table->timestamps();
            $table->unique(['audience', 'slug', 'user_id']);
            $table->index(['audience', 'slug', 'helpful']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_feedback');
        Schema::dropIfExists('help_articles');
    }
};
