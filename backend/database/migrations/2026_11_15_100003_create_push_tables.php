<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push notifications for the mobile app (QUESTIONS #189): the phones' FCM
 * tokens, each user's switches and an inbox of the last 90 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Signing out on the phone (token deleted) removes its push token too.
            $table->foreignId('personal_access_token_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('platform', 16);
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('type', 40);
            $table->string('title', 200);
            $table->string('body', 500);
            $table->string('link', 255);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'id']);
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            // Only switches turned off are stored: {"monthly_report": false}.
            $table->json('push_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('push_settings'));
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('push_tokens');
    }
};
