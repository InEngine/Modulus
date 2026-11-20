<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Contracts\ModuleUpdateApplier;
use InEngine\Modulus\Updates\ModuleUpdateNotifier;
use InEngine\Modulus\Updates\NullModuleUpdateApplier;

/**
 * Artisan `modulus:update {--auto} {--check}` — check for module updates and optionally auto-apply non-breaking patches.
 *
 * Falls back to `modulus:outdated` unless `--auto` is set; auto-apply requires
 * `modulus.updates.auto_patch` and a bound {@see \InEngine\Modulus\Contracts\ModuleUpdateApplier}.
 */
class UpdateModulesCommand extends Command
{
    protected $signature = 'modulus:update
        {--auto : Apply auto-eligible patch updates when modulus.updates.auto_patch is true}
        {--check : Only check and notify (same as modulus:outdated)}';

    protected $description = 'Check for module updates and optionally auto-apply non-breaking patches';

    /** Check or auto-apply patch updates; returns failure when auto-apply is requested but disabled or unavailable. */
    public function handle(ModuleUpdateNotifier $notifier, ModuleUpdateApplier $applier): int
    {
        if ($this->option('check') || ! $this->option('auto')) {
            return $this->call('modulus:outdated');
        }

        if (! config('modulus.updates.auto_patch', false)) {
            $this->warn('auto_patch is disabled. Set modulus.updates.auto_patch=true to allow non-breaking auto updates.');
            $this->call('modulus:outdated');

            return self::FAILURE;
        }

        if ($applier instanceof NullModuleUpdateApplier) {
            $this->warn('No ModuleUpdateApplier bound (install inengine/modulus-composer). Listing updates only.');
            $this->call('modulus:outdated');

            return self::FAILURE;
        }

        $result = $notifier->autoApplyPatches();
        $applied = $result['applied'];

        if ($applied === []) {
            $this->info('No auto-eligible patch updates applied.');
            if ($result['found'] !== []) {
                $this->call('modulus:outdated');
            }

            return self::SUCCESS;
        }

        $this->info('Auto-applied patches: '.implode(', ', $applied));
        $this->comment('State upgraded + published for applied packages. Review modulus:doctor for manual attention.');

        return self::SUCCESS;
    }
}
