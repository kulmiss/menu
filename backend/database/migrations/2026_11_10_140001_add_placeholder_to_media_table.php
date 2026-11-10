<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A tiny blurred preview (data URI, < 300 bytes) shown inline while the photo loads. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('placeholder', 400)->nullable()->after('dominant_color');
        });
    }

    public function down(): void
    {
        Schema::table('media', fn (Blueprint $table) => $table->dropColumn('placeholder'));
    }
};
