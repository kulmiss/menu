<?php

namespace App\Console\Commands;

use App\Support\SignUp\SignUp;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Deletes self sign-ups whose e-mail was never confirmed (owner, 2026-11-13), freeing the slug. Logged. */
#[Signature('signup:prune {--days=7 : Delete sign-ups unconfirmed for this many days}')]
#[Description('Delete self sign-ups that never confirmed their e-mail')]
class PruneUnverifiedSignups extends Command
{
    public function handle(SignUp $signUp): int
    {
        $count = $signUp->prune(max(1, (int) $this->option('days')));
        $this->info("Deleted {$count} unconfirmed sign-up(s).");

        return self::SUCCESS;
    }
}
