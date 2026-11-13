<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices for recorded payments (owner, 2026-11-13). Numbers come from
 * invoice_sequences, locked inside the issuing transaction, so they are
 * sequential and gap-free. Invoices keep a copy of the seller and customer
 * details: they never change after issue (only "void" and re-issue).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->string('key', 40)->primary(); // e.g. "INV-2026"
            $table->unsignedInteger('last')->default(0);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->json('seller');
            $table->json('customer');
            $table->string('plan_name', 120)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->date('paid_at');
            $table->string('status', 10)->default('paid'); // paid | void
            $table->string('note', 500)->nullable();
            $table->string('void_reason', 300)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replaces_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'issued_at']);
            $table->index(['issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
    }
};
