<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Existing recovery codes become hashes (SECURITY_REPORT.md M5). The codes people
 * saved keep working; they just can't be shown again. One-way: down() can't undo it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('two_factor_recovery_codes')->orderBy('id')->each(function ($row) {
            try {
                $plain = Crypt::decryptString($row->two_factor_recovery_codes);
            } catch (Throwable) {
                return; // other APP_KEY: leave it, the user can make new codes
            }
            $codes = json_decode($plain, true);
            if ($codes === null && str_starts_with($plain, 's:')) {
                $codes = json_decode((string) @unserialize($plain, ['allowed_classes' => false]), true);
            }
            if (! is_array($codes) || isset($codes['sha256'])) {
                return;
            }
            $hashes = array_map(fn ($c) => hash('sha256', (string) $c), array_values($codes));
            DB::table('users')->where('id', $row->id)->update([
                'two_factor_recovery_codes' => Crypt::encryptString((string) json_encode(['sha256' => $hashes])),
            ]);
        });
    }

    public function down(): void
    {
        // Hashes can't be turned back into codes.
    }
};
