<?php

/**
 * Unit coverage for {@see CapabilityRegistry} and capability persistence via {@see ModuleRepository::register()}.
 */

use Illuminate\Filesystem\Filesystem;
use InEngine\Modulus\Capabilities\CapabilityRegistry;
use InEngine\Modulus\Capabilities\ModuleCapabilities;
use InEngine\Modulus\State\FileModulusStateStore;

it('normalizes descriptor payloads and derives drivers from family:name tokens', function () {
    $normalized = ModuleCapabilities::normalize([
        'provides' => ['alerts.dispatch', 'person.user.driver:google', 'auth.method:local'],
        'requires' => ['identity.demographics'],
        'peers' => ['inengine/person'],
        'label' => 'Alert',
    ]);

    expect($normalized['provides'])->toBe(['alerts.dispatch', 'person.user.driver:google', 'auth.method:local'])
        ->and($normalized['requires'])->toBe(['identity.demographics'])
        ->and($normalized['peers'])->toBe(['inengine/person'])
        ->and($normalized['label'])->toBe('Alert')
        ->and($normalized['drivers']['person.user.driver'])->toBe(['google'])
        ->and($normalized['drivers']['auth.method'])->toBe(['local']);
});

it('queries providers and drivers from persisted capabilities', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-cap-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    mkdir($directory, 0755, true);

    $store = new FileModulusStateStore($path, new Filesystem, false);
    $store->put('inengine/alert', [
        'enabled' => true,
        'version' => '1.0.0',
        'capabilities' => ModuleCapabilities::normalize([
            'provides' => ['alerts.dispatch'],
        ]),
    ]);
    $store->put('inengine/google-user', [
        'enabled' => false,
        'version' => '1.0.0',
        'capabilities' => ModuleCapabilities::normalize([
            'provides' => ['auth.method:google', 'person.user.driver:google'],
        ]),
    ]);
    $store->put('inengine/user', [
        'enabled' => true,
        'version' => '1.0.0',
        'capabilities' => ModuleCapabilities::normalize([
            'provides' => ['auth.method:local', 'person.user.driver:local'],
            'requires' => ['alerts.dispatch'],
        ]),
    ]);

    $registry = new CapabilityRegistry($store);

    try {
        expect($registry->providersOf('alerts.dispatch'))->toBe(['inengine/alert'])
            ->and($registry->hasProvider('alerts.dispatch'))->toBeTrue()
            ->and($registry->firstProvider('auth.method:google'))->toBeNull()
            ->and($registry->firstProvider('auth.method:google', enabledOnly: false))->toBe('inengine/google-user')
            ->and($registry->driversOf('person.user.driver'))->toBe(['local'])
            ->and($registry->driversOf('person.user.driver', enabledOnly: false))->toBe(['google', 'local'])
            ->and($registry->missingRequirements('inengine/user'))->toBe([])
            ->and($registry->capabilitiesFor('inengine/alert')['provides'])->toBe(['alerts.dispatch']);

        $store->put('inengine/alert', [
            'enabled' => false,
            'version' => '1.0.0',
            'capabilities' => ModuleCapabilities::normalize([
                'provides' => ['alerts.dispatch'],
            ]),
        ]);

        expect($registry->missingRequirements('inengine/user'))->toBe(['alerts.dispatch']);
    } finally {
        @unlink($path);
        @rmdir($directory);
    }
});

it('persists capabilities through ModuleRepository::register', function () {
    [$directory, $path, $repo] = moduleRepositoryFixture();

    try {
        $repo->register('inengine/alert', '1.0.0', [
            'provides' => ['alerts.dispatch', 'alerts.livewire-bell'],
        ]);

        expect($repo->get('inengine/alert')['capabilities']['provides'] ?? null)
            ->toBe(['alerts.dispatch', 'alerts.livewire-bell']);

        $repo->register('inengine/alert', '1.0.0', [
            'provides' => ['alerts.dispatch'],
        ]);

        expect($repo->get('inengine/alert')['capabilities']['provides'] ?? null)
            ->toBe(['alerts.dispatch']);

        // Null capabilities leave the blob alone.
        $repo->register('inengine/alert', '1.0.0');

        expect($repo->get('inengine/alert')['capabilities']['provides'] ?? null)
            ->toBe(['alerts.dispatch']);
    } finally {
        moduleRepositoryCleanup($directory, $path);
    }
});
