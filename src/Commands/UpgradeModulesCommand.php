<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;
use InEngine\Modulus\Upgrades\UpgradeConstraintPolicy;
use Throwable;

/**
 * Artisan `modulus:upgrade {package?} {--publish} {--approve=}` — apply installed Composer versions (migrate/hooks) for one or all tracked modules.
 *
 * Delegates to {@see \InEngine\Modulus\ModuleRepository::upgrade()} / {@see \InEngine\Modulus\ModuleRepository::upgradeAll()}.
 * `--approve=` only prints suggested constraint guidance; it does not rewrite composer.json.
 */
class UpgradeModulesCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:upgrade
        {package? : Composer package name (omit for all tracked)}
        {--publish : Also republish config/assets}
        {--approve= : Target version to approve for a minor/major bump (requires modulus-composer to rewrite constraints)}';

    protected $description = 'Apply installed Composer versions (migrate/hooks); optional publish / approve messaging';

    /** Upgrade the given package or all tracked packages; returns failure on handled Modulus errors. */
    public function handle(): int
    {
        $package = $this->argument('package');
        $publish = (bool) $this->option('publish');
        $approve = $this->option('approve');

        if (is_string($approve) && $approve !== '') {
            $policy = UpgradeConstraintPolicy::fromConfig(config('modulus.upgrades.default_constraint'));
            $this->warn('Approving a minor/major bump rewrites the host Composer constraint.');
            $this->line('Suggested constraint under default policy ['.$policy->policy().']: '.$policy->suggestConstraint($approve));
            $this->comment('Rewrite constraints with: php artisan modulus-composer:approve {package} {version}');
            $this->comment('(Requires inengine/modulus-composer. Then re-run modulus:upgrade if needed.)');
            if (is_string($package) && $package !== '') {
                $this->line("Suggested: php artisan modulus-composer:approve {$package} {$approve}");
            }
        }

        try {
            if (is_string($package) && $package !== '') {
                $changed = $this->modulus()->upgrade($package, $publish);
                $this->info($changed
                    ? "Upgrade applied for [{$package}]."
                    : "No version change for [{$package}].");
            } else {
                $count = $this->modulus()->upgradeAll($publish);
                $this->info("Upgrade complete. {$count} package(s) changed.");
            }
        } catch (Throwable $e) {
            return $this->reportModulusException($e);
        }

        return self::SUCCESS;
    }
}
