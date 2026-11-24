<?php

namespace InEngine\Modulus\Discovery;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use InEngine\Modulus\Contracts\ModulusStateStore;

/**
 * PackageManifest decorator that omits disabled Modulus-tracked packages.
 *
 * Filtering happens at read time so `package:discover` can still write the
 * full Composer discovery cache; enable/disable only updates the state store.
 */
class FilteringPackageManifest extends PackageManifest
{
    /**
     * @param  list<string>  $deny
     */
    public function __construct(
        Filesystem $files,
        string $basePath,
        string $manifestPath,
        protected ModulusStateStore $stateStore,
        protected string $scope = 'modules',
        protected array $deny = ['inengine/modulus'],
    ) {
        parent::__construct($files, $basePath, $manifestPath);
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getManifest()
    {
        if (! is_null($this->manifest)) {
            return $this->manifest;
        }

        if (! is_file($this->manifestPath)) {
            $this->build();
        }

        $raw = is_file($this->manifestPath)
            ? $this->files->getRequire($this->manifestPath)
            : [];

        if (! is_array($raw)) {
            $raw = [];
        }

        /** @var array<array-key, mixed> $raw */
        return $this->manifest = $this->filterManifest($raw);
    }

    /**
     * Drop disabled tracked packages from the effective discovery map.
     *
     * @param  array<array-key, mixed>  $manifest
     * @return array<string, array<string, mixed>>
     */
    protected function filterManifest(array $manifest): array
    {
        $filtered = [];

        foreach ($manifest as $package => $configuration) {
            if (! is_string($package) || $package === '') {
                continue;
            }

            if ($this->shouldOmit($package)) {
                continue;
            }

            $filtered[$package] = is_array($configuration) ? $configuration : [];
        }

        return $filtered;
    }

    /**
     * Whether a Composer package should be excluded from auto-discovery.
     */
    public function shouldOmit(string $package): bool
    {
        if (in_array($package, $this->deny, true)) {
            return false;
        }

        $entry = $this->stateStore->get($package);

        // Untracked packages always pass through until discover registers them.
        if ($entry === null) {
            return false;
        }

        // Library modules (ports/tokens) stay discoverable like deny-list packages.
        if (($entry['capabilities']['kind'] ?? null) === 'library') {
            return false;
        }

        // Both scopes omit only packages present in state with enabled=false.
        return ! $this->stateStore->isEnabled($package);
    }
}
