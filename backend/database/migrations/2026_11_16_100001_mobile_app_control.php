<?php

use App\Support\PlatformSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admin → Mobile app (QUESTIONS #193): pushes sent from the screen, and
 * the old app version settings moved into the one `mobile` setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_pushes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->string('body', 500);
            $table->string('link', 255)->nullable();
            $table->json('audience');
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('with_app')->default(0); // of them, with the app and pushes on
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });

        $min = PlatformSettings::get('app_min_version');
        $latest = PlatformSettings::get('app_latest_version');
        $mobile = PlatformSettings::get('mobile');
        if (! is_array($mobile) || $mobile === []) {
            PlatformSettings::save(['mobile' => [
                'min_version' => is_string($min) && $min !== '' ? $min : '1.0.0',
                'latest_version' => is_string($latest) && $latest !== '' ? $latest : '1.0.0',
            ]]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_pushes');
    }
};
