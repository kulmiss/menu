<?php

namespace App\Console\Commands;

use App\Models\PersonalAccessToken;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Deletes app sign-ins that expired or were unused too long (QUESTIONS #187). */
#[Signature('mobile:prune-tokens')]
#[Description('Delete expired and idle mobile app tokens')]
class PruneMobileTokens extends Command
{
    public function handle(): int
    {
        $deleted = 0;
        PersonalAccessToken::query()->with('tokenable')->chunkById(500, function ($tokens) use (&$deleted) {
            foreach ($tokens as $token) {
                if (($token->expires_at !== null && $token->expires_at->isPast()) || ! $token->isFresh() || $token->tokenable === null) {
                    $token->delete();
                    $deleted++;
                }
            }
        });
        $this->info("Deleted {$deleted} token(s).");

        return self::SUCCESS;
    }
}
