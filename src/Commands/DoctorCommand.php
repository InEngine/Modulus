<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\DataTransferObjects\DoctorFinding;
use InEngine\Modulus\Diagnostics\ModulusDoctor;

class DoctorCommand extends Command
{
    protected $signature = 'modulus:doctor';

    protected $description = 'Diagnose Modulus state, missing packages, deps, and upgrade-policy issues';

    public function handle(ModulusDoctor $doctor): int
    {
        $report = $doctor->run();

        $this->line('PHP: '.$report->phpVersion);
        $this->line('Upgrade constraint policy: '.$report->defaultConstraintPolicy.' (patch ≈ ~x.y.z until approved)');
        $this->line('State: '.($report->stateHealthy ? 'ok' : 'unhealthy'));
        $this->newLine();

        if ($report->findings === []) {
            $this->info('No issues found.');

            return self::SUCCESS;
        }

        foreach ($report->findings as $finding) {
            $prefix = match ($finding->severity) {
                DoctorFinding::SEVERITY_ERROR => 'ERROR',
                DoctorFinding::SEVERITY_WARNING => 'WARN',
                default => 'INFO',
            };

            $package = $finding->package ? " [{$finding->package}]" : '';
            $this->line("<fg={$this->color($finding->severity)}>{$prefix}</>{$package} {$finding->message}");

            foreach ($finding->suggestions as $suggestion) {
                $this->line('  → '.$suggestion);
            }
        }

        if ($report->hasErrors()) {
            $this->newLine();
            $this->error('Doctor failed: enabled missing packages or hard errors present.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function color(string $severity): string
    {
        return match ($severity) {
            DoctorFinding::SEVERITY_ERROR => 'red',
            DoctorFinding::SEVERITY_WARNING => 'yellow',
            default => 'cyan',
        };
    }
}
