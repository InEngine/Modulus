<?php

use Illuminate\Filesystem\Filesystem;
use InEngine\Modulus\Contracts\InstalledPackageLocator;
use InEngine\Modulus\Contracts\ModuleDependencyGuard;
use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;
use InEngine\Modulus\DataTransferObjects\DoctorFinding;
use InEngine\Modulus\Diagnostics\ModulusDoctor;
use InEngine\Modulus\Exceptions\UnmetModuleDependencyException;
use InEngine\Modulus\State\FileModulusStateStore;
use InEngine\Modulus\Updates\ArrayModuleUpdateChecker;
use InEngine\Modulus\Upgrades\UpgradeConstraintPolicy;

it('reports missing enabled packages as errors and disabled as warnings', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-doctor-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    $store = new FileModulusStateStore($path, new Filesystem, false);
    $store->put('inengine/alert', ['enabled' => true, 'version' => '1.0.0']);
    $store->put('inengine/users', ['enabled' => false, 'version' => '1.0.0']);

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->with('inengine/alert')->andReturn(false);
    $locator->shouldReceive('isInstalled')->with('inengine/users')->andReturn(false);
    $locator->shouldReceive('rootConstraint')->andReturn(null)->byDefault();
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();

    $guard = Mockery::mock(ModuleDependencyGuard::class);
    $guard->shouldReceive('assertCanEnable')->never();

    $doctor = new ModulusDoctor(
        $store,
        $locator,
        $guard,
        new UpgradeConstraintPolicy,
        updateChecker: null,
        includeUpdates: false,
        deny: ['inengine/modulus'],
    );
    $report = $doctor->run();

    expect($report->hasErrors())->toBeTrue();

    $codes = array_map(fn (DoctorFinding $f) => $f->code.'|'.$f->severity, $report->findings);

    expect($codes)->toContain('missing_package|error')
        ->and($codes)->toContain('missing_package|warning');

    foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($directory);
});

it('warns when host constraints are looser than patch policy', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-doctor-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    $store = new FileModulusStateStore($path, new Filesystem, false);
    $store->put('inengine/alert', ['enabled' => true, 'version' => '1.2.0']);

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->andReturn(true);
    $locator->shouldReceive('rootConstraint')->with('inengine/alert')->andReturn('^1.2.0');
    $locator->shouldReceive('version')->with('inengine/alert')->andReturn('1.2.0');
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();

    $guard = Mockery::mock(ModuleDependencyGuard::class);
    $guard->shouldReceive('assertCanEnable')->once()->with('inengine/alert');

    $doctor = new ModulusDoctor(
        $store,
        $locator,
        $guard,
        new UpgradeConstraintPolicy('patch'),
        updateChecker: null,
        includeUpdates: false,
        deny: ['inengine/modulus'],
    );
    $report = $doctor->run();

    expect($report->hasErrors())->toBeFalse()
        ->and(collect($report->findings)->pluck('code')->all())->toContain('constraint_too_loose');

    foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($directory);
});

it('includes update_available findings from ModuleUpdateChecker', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-doctor-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    $store = new FileModulusStateStore($path, new Filesystem, false);

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $guard = Mockery::mock(ModuleDependencyGuard::class);

    $update = new AvailableModuleUpdate(
        package: 'inengine/alert',
        installedVersion: '1.0.0',
        latestVersion: '1.0.1',
        bump: AvailableModuleUpdate::BUMP_PATCH,
        withinConstraint: true,
        autoEligible: true,
        rootConstraint: '~1.0.0',
    );

    $doctor = new ModulusDoctor(
        $store,
        $locator,
        $guard,
        new UpgradeConstraintPolicy,
        updateChecker: new ArrayModuleUpdateChecker([$update]),
        includeUpdates: true,
        deny: ['inengine/modulus'],
    );
    $report = $doctor->run();

    expect(collect($report->findings)->pluck('code')->all())->toContain('update_available');

    foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($directory);
});

it('reports dependency violations for enabled modules', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-doctor-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    $store = new FileModulusStateStore($path, new Filesystem, false);
    $store->put('inengine/alert', ['enabled' => true, 'version' => '1.0.0']);

    $locator = Mockery::mock(InstalledPackageLocator::class);
    $locator->shouldReceive('isInstalled')->andReturn(true);
    $locator->shouldReceive('rootConstraint')->andReturn('~1.0.0');
    $locator->shouldReceive('laravelProviderClasses')->andReturn([])->byDefault();

    $guard = Mockery::mock(ModuleDependencyGuard::class);
    $guard->shouldReceive('assertCanEnable')
        ->once()
        ->andThrow(new UnmetModuleDependencyException('inengine/alert', disabledModules: ['inengine/users']));

    $doctor = new ModulusDoctor(
        $store,
        $locator,
        $guard,
        new UpgradeConstraintPolicy,
        updateChecker: null,
        includeUpdates: false,
        deny: ['inengine/modulus'],
    );
    $report = $doctor->run();

    expect($report->hasErrors())->toBeTrue()
        ->and(collect($report->findings)->pluck('code')->all())->toContain('dependency_violation');

    foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($directory);
});
