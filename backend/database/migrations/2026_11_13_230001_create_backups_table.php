<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Backups of the database and uploaded media (owner, 2026-11-13). Files live in storage/app/backups. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('kind', 10)->default('scheduled'); // scheduled | manual
            $table->string('status', 10)->default('running'); // queued | running | ok | failed
            $table->string('offsite', 10)->nullable(); // null | uploaded | failed
            $table->string('error', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
