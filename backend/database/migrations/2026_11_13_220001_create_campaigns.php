<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admin → Messages: e-mails to restaurant owners (owner, 2026-11-13).
 * Recipients are fixed when a campaign is sent; a queued job sends a few per
 * minute (throttle) and retries failures. News e-mails respect the owner's
 * unsubscribe; account e-mails (verify, invoices, security) never do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 150);
            $table->text('body');
            $table->json('filters');
            $table->string('status', 12)->default('sending'); // sending | done
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('name')->default('');
            $table->json('vars'); // placeholder values, fixed at send time
            $table->string('status', 10)->default('pending'); // pending | sent | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error', 300)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
            $table->index(['campaign_id', 'status']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('news_unsubscribed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('news_unsubscribed_at'));
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
