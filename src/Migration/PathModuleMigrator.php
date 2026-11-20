<?php

namespace InEngine\Modulus\Migration;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Migrations\Migrator;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleMigrator;

/**
 * Runs Laravel migrations from a conventional path under the package install root.
 *
 * Default relative path: `database/migrations` (Spatie / Skeleton layout).
 * Readable without booting the module provider — required because disabled modules
 * are omitted from PackageManifest before enable.
 *
 * The Laravel {@see Migrator} is resolved lazily so Modulus can boot during
 * package:discover (e.g. skeleton Composer prepare) before `migrator` is bound.
 */
final class PathModuleMigrator implements ModuleMigrator
{
    /**
     * @param  Migrator|Closure(): Migrator  $migrator
     */
    public function __construct(
        protected InstalledPackageLocator $packages,
        protected Migrator|Closure $migrator,
        protected string $relativePath = 'database/migrations',
    ) {}

    /**
     * Build from container config (`modulus.migrations.path`).
     */
    public static function fromConfig(
        InstalledPackageLocator $packages,
        Application $app,
        ConfigRepository $config,
    ): self {
        $relative = $config->get('modulus.migrations.path', 'database/migrations');

        if (! is_string($relative) || $relative === '') {
            $relative = 'database/migrations';
        }

        return new self(
            $packages,
            static fn (): Migrator => $app->make('migrator'),
            $relative,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function migrate(string $package): void
    {
        $root = $this->packages->installPath($package);

        if ($root === null || $root === '') {
            return;
        }

        $path = $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($this->relativePath, '/\\'));

        if (! is_dir($path)) {
            return;
        }

        // Pending-only: safe to call on every enable / upgrade.
        $this->resolveMigrator()->run([$path]);
    }

    /** Return the Migrator, invoking the lazy closure when one was supplied. */
    protected function resolveMigrator(): Migrator
    {
        if ($this->migrator instanceof Closure) {
            return ($this->migrator)();
        }

        return $this->migrator;
    }
}
