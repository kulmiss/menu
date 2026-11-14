<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for the Super Admin search (owner, 2026-11-13). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->index('name'));
        Schema::table('users', fn (Blueprint $t) => $t->index('name'));
        Schema::table('payments', fn (Blueprint $t) => $t->index('reference'));
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropIndex(['name']));
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex(['name']));
        Schema::table('payments', fn (Blueprint $t) => $t->dropIndex(['reference']));
    }
};
