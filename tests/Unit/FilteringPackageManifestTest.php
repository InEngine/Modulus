<?php

/**
 * Unit coverage for {@see FilteringPackageManifest} filtering providers by module state.
 */

use Illuminate\Filesystem\Filesystem;
use InEngine\Modulus\Discovery\FilteringPackageManifest;
use InEngine\Modulus\State\FileModulusStateStore;

/**
 * Write a temp `packages.php` (and optional `modules.json`) and build a {@see FilteringPackageManifest}.
 *
 * Pair with `filteringManifestCleanup()`.
 *
 * @param  array<string, mixed>  $manifest  Package manifest contents written to `packages.php`.
 * @param  array<string, array{enabled: bool, version: string|null}>  $state  Module state written to `modules.json`; omitted when empty.
 * @return array{0: string, 1: string, 2: string, 3: FileModulusStateStore, 4: FilteringPackageManifest}
 *                                                                                                       Temp directory, manifest path, state path, store, filtering manifest.
 */
function filteringManifestFixture(array $manifest, array $state = []): array
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-manifest-'.uniqid('', true);
    mkdir($directory, 0755, true);

    $manifestPath = $directory.DIRECTORY_SEPARATOR.'packages.php';
    $statePath = $directory.DIRECTORY_SEPARATOR.'modules.json';

    file_put_contents(
        $manifestPath,
        '<?php return '.var_export($manifest, true).';'
    );

    if ($state !== []) {
        file_put_contents(
            $statePath,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT)
        );
    }

    $store = new FileModulusStateStore($statePath);
    $filtering = new FilteringPackageManifest(
        new Filesystem,
        $directory,
        $manifestPath,
        $store,
        'modules',
        ['inengine/modulus'],
    );

    return [$directory, $manifestPath, $statePath, $store, $filtering];
}

/**
 * Remove the temp directory and files created by `filteringManifestFixture()`.
 *
 * @param  string  $directory  Temp directory returned by the fixture.
 */
function filteringManifestCleanup(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $remaining = glob($directory.DIRECTORY_SEPARATOR.'*') ?: [];

    foreach ($remaining as $file) {
        @unlink($file);
    }

    @rmdir($directory);
}

it('omits providers for disabled tracked packages', function () {
    [$directory, , , , $manifest] = filteringManifestFixture(
        [
            'inengine/alert' => [
                'providers' => ['InEngine\\Alert\\AlertServiceProvider'],
            ],
            'livewire/livewire' => [
                'providers' => ['Livewire\\LivewireServiceProvider'],
            ],
        ],
        [
            'inengine/alert' => [
                'enabled' => false,
                'version' => '1.0.0',
            ],
        ],
    );

    try {
        expect($manifest->providers())
            ->not->toContain('InEngine\\Alert\\AlertServiceProvider')
            ->toContain('Livewire\\LivewireServiceProvider');
    } finally {
        filteringManifestCleanup($directory);
    }
});

it('keeps providers for enabled tracked packages', function () {
    [$directory, , , , $manifest] = filteringManifestFixture(
        [
            'inengine/alert' => [
                'providers' => ['InEngine\\Alert\\AlertServiceProvider'],
            ],
        ],
        [
            'inengine/alert' => [
                'enabled' => true,
                'version' => '1.0.0',
            ],
        ],
    );

    try {
        expect($manifest->providers())->toContain('InEngine\\Alert\\AlertServiceProvider');
    } finally {
        filteringManifestCleanup($directory);
    }
});

it('passes through packages that are not in the state store', function () {
    [$directory, , , , $manifest] = filteringManifestFixture([
        'spatie/laravel-ignition' => [
            'providers' => ['Spatie\\LaravelIgnition\\IgnitionServiceProvider'],
        ],
    ]);

    try {
        expect($manifest->providers())->toContain('Spatie\\LaravelIgnition\\IgnitionServiceProvider')
            ->and($manifest->shouldOmit('spatie/laravel-ignition'))->toBeFalse();
    } finally {
        filteringManifestCleanup($directory);
    }
});

it('never omits inengine/modulus even when state says disabled', function () {
    [$directory, , , , $manifest] = filteringManifestFixture(
        [
            'inengine/modulus' => [
                'providers' => ['InEngine\\Modulus\\ModulusServiceProvider'],
            ],
        ],
        [
            'inengine/modulus' => [
                'enabled' => false,
                'version' => '1.0.0',
            ],
        ],
    );

    try {
        expect($manifest->shouldOmit('inengine/modulus'))->toBeFalse()
            ->and($manifest->providers())->toContain('InEngine\\Modulus\\ModulusServiceProvider');
    } finally {
        filteringManifestCleanup($directory);
    }
});

it('never omits library modules even when state says disabled', function () {
    [$directory, , , , $manifest] = filteringManifestFixture(
        [
            'inengine/identity-contracts' => [
                'providers' => ['InEngine\\IdentityContracts\\IdentityContractsServiceProvider'],
            ],
        ],
        [
            'inengine/identity-contracts' => [
                'enabled' => false,
                'version' => '1.0.0',
                'capabilities' => [
                    'kind' => 'library',
                    'provides' => ['identity.contracts'],
                ],
            ],
        ],
    );

    try {
        expect($manifest->shouldOmit('inengine/identity-contracts'))->toBeFalse()
            ->and($manifest->providers())->toContain('InEngine\\IdentityContracts\\IdentityContractsServiceProvider');
    } finally {
        filteringManifestCleanup($directory);
    }
});

it('also filters aliases for disabled tracked packages', function () {
    [$directory, , , , $manifest] = filteringManifestFixture(
        [
            'inengine/alert' => [
                'providers' => ['InEngine\\Alert\\AlertServiceProvider'],
                'aliases' => [
                    'Alert' => 'InEngine\\Alert\\Facades\\Alert',
                ],
            ],
        ],
        [
            'inengine/alert' => [
                'enabled' => false,
                'version' => null,
            ],
        ],
    );

    try {
        expect($manifest->aliases())->toBe([]);
    } finally {
        filteringManifestCleanup($directory);
    }
});
