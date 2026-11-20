<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;
use InEngine\Modulus\Updates\ModuleUpdateNotifier;

/**
 * Artisan `modulus:outdated` — list available Composer updates for Modulus modules.
 *
 * Delegates to {@see \InEngine\Modulus\Updates\ModuleUpdateNotifier::check()} and dispatches update events
 * for UI listeners.
 */
class OutdatedModulesCommand extends Command
{
    protected $signature = 'modulus:outdated';

    protected $description = 'List available Composer updates for Modulus modules (notifies UI listeners)';

    /** Print a table of available updates; always returns success. */
    public function handle(ModuleUpdateNotifier $notifier): int
    {
        $updates = $notifier->check(dispatch: true);

        if ($updates === []) {
            $this->info('All Modulus modules are up to date (or no update source available).');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($updates as $update) {
            $rows[] = [
                $update->package,
                $update->installedVersion,
                $update->latestVersion,
                $update->bump,
                $update->withinConstraint ? 'yes' : 'no',
                $update->autoEligible ? 'yes' : 'no',
            ];
        }

        $this->table(
            ['Package', 'Installed', 'Latest', 'Bump', 'In constraint', 'Auto-patch'],
            $rows,
        );

        $patches = count(array_filter($updates, fn (AvailableModuleUpdate $u) => $u->isNonBreaking()));
        $this->comment("Non-breaking (patch) updates: {$patches}. Run modulus:update --auto to apply when auto_patch + modulus-composer applier are enabled.");
        $this->comment('Minor/major bumps need approval (modulus:upgrade --approve= / modulus-ui), then constraint rewrite.');

        return self::SUCCESS;
    }
}
