<?php

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Migration\PathModuleMigrator;

it('runs the migrator when the package migrations directory exists', function () {
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-mig-'.uniqid('', true);
    $migrations = $root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
    (new Filesystem)->makeDirectory($migrations, 0755, true);

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('installPath')->with('inengine/alert')->andReturn($root);

    $migrator = Mockery::mock(Migrator::class);
    $migrator->shouldReceive('run')->once()->with([$migrations]);

    try {
        (new PathModuleMigrator($locator, $migrator))->migrate('inengine/alert');
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});

it('no-ops when the package has no install path', function () {
    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('installPath')->with('inengine/alert')->andReturn(null);

    $migrator = Mockery::mock(Migrator::class);
    $migrator->shouldReceive('run')->never();

    (new PathModuleMigrator($locator, $migrator))->migrate('inengine/alert');
});

it('no-ops when the migrations directory is missing', function () {
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-mig-'.uniqid('', true);
    mkdir($root, 0755, true);

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('installPath')->with('inengine/alert')->andReturn($root);

    $migrator = Mockery::mock(Migrator::class);
    $migrator->shouldReceive('run')->never();

    try {
        (new PathModuleMigrator($locator, $migrator))->migrate('inengine/alert');
    } finally {
        rmdir($root);
    }
});

it('honors a custom relative migrations path from config', function () {
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-mig-'.uniqid('', true);
    $custom = $root.DIRECTORY_SEPARATOR.'schema';
    mkdir($custom, 0755, true);

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('installPath')->with('inengine/alert')->andReturn($root);

    $migrator = Mockery::mock(Migrator::class);
    $migrator->shouldReceive('run')->once()->with([$custom]);
    app()->instance('migrator', $migrator);

    config()->set('modulus.migrations.path', 'schema');

    try {
        PathModuleMigrator::fromConfig($locator, app(), config())->migrate('inengine/alert');
    } finally {
        rmdir($custom);
        rmdir($root);
    }
});
