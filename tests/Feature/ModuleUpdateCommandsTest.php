<?php

/**
 * Feature coverage for `modulus:outdated` and `modulus:update`, including
 * {@see ModuleUpdateAvailable} dispatch and auto-apply via
 * {@see ModuleUpdateApplier}.
 */

use Illuminate\Support\Facades\Event;
use InEngine\Modulus\Contracts\ModuleUpdateApplier;
use InEngine\Modulus\Contracts\ModuleUpdateChecker;
use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;
use InEngine\Modulus\Events\ModuleUpdateAvailable;
use InEngine\Modulus\ModuleRepository;
use InEngine\Modulus\Updates\ArrayModuleUpdateChecker;
use InEngine\Modulus\Updates\ModuleUpdateNotifier;
use InEngine\Modulus\Updates\NullModuleUpdateApplier;

it('modulus:outdated lists updates and dispatches ModuleUpdateAvailable', function () {
    Event::fake([ModuleUpdateAvailable::class]);

    $update = new AvailableModuleUpdate(
        package: 'inengine/alert',
        installedVersion: '1.0.0',
        latestVersion: '1.0.1',
        bump: AvailableModuleUpdate::BUMP_PATCH,
        withinConstraint: true,
        autoEligible: true,
        rootConstraint: '~1.0.0',
    );

    app()->instance(ModuleUpdateChecker::class, new ArrayModuleUpdateChecker([$update]));
    app()->instance(ModuleUpdateNotifier::class, new ModuleUpdateNotifier(
        app(ModuleUpdateChecker::class),
        app(ModuleUpdateApplier::class),
        app(ModuleRepository::class),
        app('events'),
        false,
    ));

    $this->artisan('modulus:outdated')
        ->expectsOutputToContain('inengine/alert')
        ->assertSuccessful();

    Event::assertDispatched(ModuleUpdateAvailable::class);
});

it('modulus:update --auto requires auto_patch and a real applier', function () {
    $update = new AvailableModuleUpdate(
        package: 'inengine/alert',
        installedVersion: '1.0.0',
        latestVersion: '1.0.1',
        bump: AvailableModuleUpdate::BUMP_PATCH,
        withinConstraint: true,
        autoEligible: true,
        rootConstraint: '~1.0.0',
    );

    app()->instance(ModuleUpdateChecker::class, new ArrayModuleUpdateChecker([$update]));

    config()->set('modulus.updates.auto_patch', false);

    $this->artisan('modulus:update', ['--auto' => true])->assertFailed();

    config()->set('modulus.updates.auto_patch', true);
    app()->instance(ModuleUpdateApplier::class, new NullModuleUpdateApplier);

    $this->artisan('modulus:update', ['--auto' => true])->assertFailed();
});

it('modulus:update --auto applies patches through ModuleUpdateApplier', function () {
    config()->set('modulus.updates.auto_patch', true);

    $update = new AvailableModuleUpdate(
        package: 'inengine/alert',
        installedVersion: '1.0.0',
        latestVersion: '1.0.1',
        bump: AvailableModuleUpdate::BUMP_PATCH,
        withinConstraint: true,
        autoEligible: true,
        rootConstraint: '~1.0.0',
    );

    $applier = Mockery::mock(ModuleUpdateApplier::class);
    $applier->shouldReceive('apply')->once()->andReturn(['inengine/alert']);

    $modules = Mockery::mock(ModuleRepository::class);
    $modules->shouldReceive('get')->with('inengine/alert')->andReturn([
        'enabled' => true,
        'version' => '1.0.0',
    ]);
    $modules->shouldReceive('upgrade')->once()->with('inengine/alert', true)->andReturn(true);

    app()->instance(ModuleUpdateNotifier::class, new ModuleUpdateNotifier(
        new ArrayModuleUpdateChecker([$update]),
        $applier,
        $modules,
        app('events'),
        autoPatch: true,
    ));
    app()->instance(ModuleUpdateApplier::class, $applier);

    $this->artisan('modulus:update', ['--auto' => true])
        ->expectsOutputToContain('inengine/alert')
        ->assertSuccessful();
});
