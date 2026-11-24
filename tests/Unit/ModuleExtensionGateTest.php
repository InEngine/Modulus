<?php

use InEngine\Modulus\Capabilities\ModuleCapabilities;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Facades\Modulus;
use InEngine\Modulus\Support\ModuleExtensionGate;

it('is ready when tokens packages and config are satisfied', function () {
    Modulus::register('inengine/demo-hub', '1.0.0', ModuleCapabilities::normalize([
        'provides' => ['demo.hub', 'demo.hub.config'],
    ]));
    app(ModulusStateStore::class)->setEnabled('inengine/demo-hub', true);

    config()->set('demo.model', 'Demo\\Model');

    /** @var ModuleExtensionGate $gate */
    $gate = app(ModuleExtensionGate::class);

    expect($gate->isReady(
        tokens: ['demo.hub', 'demo.hub.config'],
        packages: ['inengine/demo-hub'],
        configKeys: ['demo.model'],
    ))->toBeTrue();

    $ran = false;
    $gate->whenReady(
        callback: function () use (&$ran): void {
            $ran = true;
        },
        tokens: ['demo.hub'],
        packages: ['inengine/demo-hub'],
        configKeys: ['demo.model'],
    );

    expect($ran)->toBeTrue();
});

it('is not ready when a required token is missing', function () {
    /** @var ModuleExtensionGate $gate */
    $gate = app(ModuleExtensionGate::class);

    expect($gate->isReady(
        tokens: ['missing.token'],
        packages: [],
        configKeys: [],
    ))->toBeFalse();
});
