<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;

/**
 * Artisan `modulus:list` — list Modulus-tracked modules with enabled state and version.
 *
 * Reads {@see \InEngine\Modulus\ModuleRepository::all()}.
 */
class ListModulesCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:list';

    protected $description = 'List Modulus-tracked modules and their enabled state';

    /** List tracked modules in a table; always returns success. */
    public function handle(): int
    {
        $rows = [];

        foreach ($this->modulus()->all() as $package => $state) {
            $rows[] = [
                $package,
                $state['enabled'] ? 'enabled' : 'disabled',
                $state['version'] ?? '—',
            ];
        }

        if ($rows === []) {
            $this->info('No modules tracked yet. Run modulus:discover.');

            return self::SUCCESS;
        }

        $this->table(['Package', 'Status', 'Version'], $rows);

        return self::SUCCESS;
    }
}
