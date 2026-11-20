<?php

namespace InEngine\Modulus\Publish;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModulePublisher;
use InEngine\Modulus\ModuleRepository;

/**
 * Convention-based publisher matching Spatie laravel-package-tools layouts.
 *
 * Called from {@see ModuleRepository::publish()} (install /
 * Composer update via modulus-composer), not from enable/disable.
 *
 * Defaults:
 * - config/*.php → config/ (skip existing unless force_config)
 * - resources/dist → public/vendor/{short-name} (browser assets; force by default)
 * - views / lang / inertia → opt-in host overrides only (vendor load is enough for runtime)
 */
final class PathModulePublisher implements ModulePublisher
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        protected InstalledPackageLocator $packages,
        protected Filesystem $files = new Filesystem,
        protected array $options = [],
    ) {}

    /**
     * Build from `modulus.publish` config.
     */
    public static function fromConfig(
        InstalledPackageLocator $packages,
        Filesystem $files,
        ConfigRepository $config,
    ): self {
        $publish = $config->get('modulus.publish', []);

        if (! is_array($publish)) {
            $publish = [];
        }

        return new self($packages, $files, $publish);
    }

    /**
     * {@inheritdoc}
     */
    public function publish(string $package): void
    {
        $root = $this->packages->installPath($package);

        if ($root === null || $root === '') {
            return;
        }

        $shortName = $this->shortName($package);

        if ($this->enabled('config', true)) {
            $this->publishConfig($root, force: $this->enabled('force_config', false));
        }

        // Public assets must live under public/ for HTTP — primary UI publish concern.
        if ($this->enabled('assets', true)) {
            $this->publishDirectory(
                $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'dist',
                public_path('vendor'.DIRECTORY_SEPARATOR.$shortName),
                force: $this->enabled('force_assets', true),
            );
        }

        // Blade / lang / Inertia stay vendor-loaded unless the host wants overrides.
        if ($this->enabled('views', false)) {
            $this->publishDirectory(
                $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views',
                resource_path('views'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.$shortName),
                force: $this->enabled('force_views', false),
            );
        }

        if ($this->enabled('lang', false)) {
            $this->publishLang($root, $shortName, force: $this->enabled('force_lang', false));
        }

        if ($this->enabled('inertia', false)) {
            $directoryName = Str::of($shortName)->studly()->remove('-')->value();
            $this->publishDirectory(
                $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'Pages',
                base_path('resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'Pages'.DIRECTORY_SEPARATOR.$directoryName),
                force: $this->enabled('force_inertia', false),
            );
        }
    }

    /**
     * Copy each config/*.php into the host config path when missing (or forced).
     */
    protected function publishConfig(string $root, bool $force): void
    {
        $configDir = $root.DIRECTORY_SEPARATOR.'config';

        if (! $this->files->isDirectory($configDir)) {
            return;
        }

        foreach ($this->files->files($configDir) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $target = config_path($file->getFilename());

            if ($this->files->exists($target) && ! $force) {
                continue;
            }

            $this->files->ensureDirectoryExists(dirname($target));
            $this->files->copy($file->getPathname(), $target);
        }
    }

    /**
     * Prefer Spatie `resources/lang`, fall back to package-root `lang/`.
     */
    protected function publishLang(string $root, string $shortName, bool $force): void
    {
        $candidates = [
            $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'lang',
            $root.DIRECTORY_SEPARATOR.'lang',
        ];

        $target = function_exists('lang_path')
            ? lang_path('vendor'.DIRECTORY_SEPARATOR.$shortName)
            : resource_path('lang'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.$shortName);

        foreach ($candidates as $source) {
            if ($this->files->isDirectory($source)) {
                $this->publishDirectory($source, $target, $force);

                return;
            }
        }
    }

    /**
     * Copy a vendor directory tree into the host when the source exists.
     */
    protected function publishDirectory(string $source, string $target, bool $force): void
    {
        if (! $this->files->isDirectory($source)) {
            return;
        }

        if ($this->files->isDirectory($target) && ! $force) {
            return;
        }

        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->copyDirectory($source, $target);
    }

    /**
     * Last segment of the Composer package name (`inengine/alert` → `alert`).
     */
    protected function shortName(string $package): string
    {
        $position = strrpos($package, '/');

        if ($position === false) {
            return $package;
        }

        return substr($package, $position + 1);
    }

    /** Whether a publish option is on, falling back to $default when unset. */
    protected function enabled(string $key, bool $default): bool
    {
        $value = $this->options[$key] ?? $default;

        return (bool) $value;
    }
}
