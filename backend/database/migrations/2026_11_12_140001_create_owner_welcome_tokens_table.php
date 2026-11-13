<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Set-password tokens from the new-owner welcome e-mail (7 days; config/auth.php "welcome"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_welcome_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_welcome_tokens');
    }
};
