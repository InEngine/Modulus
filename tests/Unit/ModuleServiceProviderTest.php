<?php

use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\ModuleRepository;
use InEngine\Modulus\ModuleServiceProvider;
use Spatie\LaravelPackageTools\Package;

it('registers with Modulus and skips boot when disabled', function () {
    $provider = new class(app()) extends ModuleServiceProvider
    {
        public bool $bootedPackage = false;

        public function configurePackage(Package $package): void
        {
            $package->name('alert-test');
        }

        protected function modulusPackageName(): string
        {
            return 'inengine/alert';
        }

        public function boot(): void
        {
            if (! $this->shouldBootModule()) {
                return;
            }

            $this->bootedPackage = true;
        }
    };

    config()->set('modulus.state_path', sys_get_temp_dir().'/modulus-msp-'.uniqid('', true).'.json');
    app()->forgetInstance(ModuleRepository::class);
    app()->forgetInstance(ModulusStateStore::class);

    $provider->register();

    expect(app(ModuleRepository::class)->get('inengine/alert'))->not->toBeNull()
        ->and(app(ModuleRepository::class)->isEnabled('inengine/alert'))->toBeFalse();

    $provider->boot();
    expect($provider->bootedPackage)->toBeFalse();

    app(ModuleRepository::class)->enable('inengine/alert');
    $provider->boot();
    expect($provider->bootedPackage)->toBeTrue();
});

it('boots standalone when ModuleRepository is not bound', function () {
    $provider = new class(app()) extends ModuleServiceProvider
    {
        public bool $bootedPackage = false;

        public function configurePackage(Package $package): void
        {
            $package->name('alert-standalone');
        }

        protected function modulusPackageName(): string
        {
            return 'inengine/alert';
        }

        public function boot(): void
        {
            if (! $this->shouldBootModule()) {
                return;
            }

            $this->bootedPackage = true;
        }
    };

    app()->offsetUnset(ModuleRepository::class);

    expect($provider->shouldBootModule())->toBeTrue();

    $provider->boot();
    expect($provider->bootedPackage)->toBeTrue();
});
