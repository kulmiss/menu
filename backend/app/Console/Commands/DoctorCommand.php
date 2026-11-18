<?php

namespace App\Console\Commands;

use App\Support\Ops\Doctor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Go-live checks (QUESTIONS #233). Red lines are unsafe or broken: fix them
 * before going live; the command then exits 1, so a deploy script can stop.
 */
#[Signature('menu:doctor {--production : Check as for a live server, whatever APP_ENV says} {--json : Machine-readable output}')]
#[Description('Check the server for anything unsafe or missing before (and after) going live')]
class DoctorCommand extends Command
{
    public function handle(Doctor $doctor): int
    {
        $checks = $doctor->run($this->option('production') ? true : null);
        $overall = Doctor::overall($checks);

        if ($this->option('json')) {
            $this->line((string) json_encode(['status' => $overall, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $overall === 'down' ? self::FAILURE : self::SUCCESS;
        }

        foreach ($checks as $c) {
            [$mark, $style] = match ($c['status']) {
                'ok' => ['✓', 'info'],
                'warn' => ['!', 'comment'],
                default => ['✗', 'error'],
            };
            $this->line("<{$style}>{$mark}</{$style}> {$c['message']}");
            if ($c['fix'] !== null) {
                $this->line("    Fix: {$c['fix']}");
            }
        }
        $fails = count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));
        $warns = count(array_filter($checks, fn ($c) => $c['status'] === 'warn'));
        $this->newLine();
        if ($fails > 0) {
            $this->error("{$fails} problem(s) to fix before going live, {$warns} warning(s).");

            return self::FAILURE;
        }
        $this->info($warns > 0 ? "Ready, with {$warns} warning(s)." : 'All good.');

        return self::SUCCESS;
    }
}
