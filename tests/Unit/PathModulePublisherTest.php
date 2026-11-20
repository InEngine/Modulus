<?php

/**
 * Unit coverage for {@see \InEngine\Modulus\Publish\PathModulePublisher} publishing config and assets from a package install path.
 */

use Illuminate\Filesystem\Filesystem;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Publish\PathModulePublisher;

/**
 * Create a temp package root with `config/alert.php` and `resources/dist/app.js`
 * for {@see PathModulePublisher} tests.
 *
 * @return string Absolute path to the temp package root.
 */
function publisherPackageRoot(): string
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-pub-'.uniqid('', true);
    (new Filesystem)->makeDirectory($root.DIRECTORY_SEPARATOR.'config', 0755, true);
    (new Filesystem)->makeDirectory($root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'dist', 0755, true);
    (new Filesystem)->put($root.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'alert.php', "<?php\n\nreturn ['x' => 1];\n");
    (new Filesystem)->put($root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'app.js', 'console.log(1)');

    return $root;
}

it('publishes missing config and assets from the package install path', function () {
    $root = publisherPackageRoot();
    $files = new Filesystem;
    $configTarget = config_path('alert.php');
    $assetsTarget = public_path('vendor/alert');

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('installPath')->with('inengine/alert')->andReturn($root);

    try {
        if ($files->exists($configTarget)) {
            $files->delete($configTarget);
        }
        if ($files->isDirectory($assetsTarget)) {
            $files->deleteDirectory($assetsTarget);
        }

        (new PathModulePublisher($locator, $files, [
            'config' => true,
            'assets' => true,
            'views' => false,
            'force_config' => false,
            'force_assets' => true,
        ]))->publish('inengine/alert');

        expect($files->exists($configTarget))->toBeTrue()
            ->and($files->get($configTarget))->toContain("'x' => 1")
            ->and($files->exists($assetsTarget.DIRECTORY_SEPARATOR.'app.js'))->toBeTrue();
    } finally {
        $files->deleteDirectory($root);
        if ($files->exists($configTarget)) {
            $files->delete($configTarget);
        }
        if ($files->isDirectory($assetsTarget)) {
            $files->deleteDirectory($assetsTarget);
        }
    }
});

it('does not overwrite existing config unless force_config is true', function () {
    $root = publisherPackageRoot();
    $files = new Filesystem;
    $configTarget = config_path('alert.php');

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('installPath')->with('inengine/alert')->andReturn($root);

    try {
        $files->put($configTarget, "<?php\n\nreturn ['host' => true];\n");

        (new PathModulePublisher($locator, $files, [
            'config' => true,
            'assets' => false,
            'force_config' => false,
        ]))->publish('inengine/alert');

        expect($files->get($configTarget))->toContain("'host' => true");
    } finally {
        $files->deleteDirectory($root);
        if ($files->exists($configTarget)) {
            $files->delete($configTarget);
        }
    }
});

it('no-ops when the package has no install path', function () {
    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('installPath')->with('inengine/alert')->andReturn(null);

    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('isDirectory')->never();
    $files->shouldReceive('copy')->never();

    (new PathModulePublisher($locator, $files))->publish('inengine/alert');
});
