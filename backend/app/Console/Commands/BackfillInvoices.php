<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Support\Billing\Invoices;
use Illuminate\Console\Command;

/**
 * Issues invoices for payments recorded before invoices existed (oldest
 * first). Nothing is e-mailed. Safe to run again: payments that already
 * have an invoice are skipped.
 */
class BackfillInvoices extends Command
{
    protected $signature = 'invoices:backfill {--dry-run : Only count the payments without an invoice}';

    protected $description = 'Issue invoices for older payments that have none (no e-mails).';

    public function handle(Invoices $invoices): int
    {
        $query = Payment::with(['tenant', 'plan'])->whereDoesntHave('invoices')->orderBy('paid_at')->orderBy('id');
        $count = (clone $query)->count();
        if ($this->option('dry-run')) {
            $this->info("{$count} payment(s) without an invoice.");

            return self::SUCCESS;
        }
        $query->each(fn (Payment $p) => $invoices->issue($p));
        $this->info("{$count} invoice(s) issued.");

        return self::SUCCESS;
    }
}
