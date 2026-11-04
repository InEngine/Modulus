<?php

use Illuminate\Filesystem\Filesystem;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\State\FileModulusStateStore;
use InEngine\Modulus\State\FileModulusStateStoreFactory;
use InEngine\Modulus\State\ModulusStateStoreResolver;

it('resolves the default file store from modulus.store config', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-resolver-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    mkdir($directory, 0755, true);

    try {
        config()->set('modulus.store', FileModulusStateStore::class);
        config()->set('modulus.state_path', $path);
        config()->set('modulus.state_fsync', false);

        $store = ModulusStateStoreResolver::resolve(app());

        expect($store)->toBeInstanceOf(FileModulusStateStore::class);
    } finally {
        @unlink($path);
        @rmdir($directory);
    }
});

it('rejects a non-string modulus.store value', function () {
    config()->set('modulus.store', ['not-a-class']);

    ModulusStateStoreResolver::resolve(app());
})->throws(InvalidArgumentException::class, 'Config [modulus.store] must be a concrete class name');

it('rejects an empty modulus.store value', function () {
    config()->set('modulus.store', '');

    ModulusStateStoreResolver::resolve(app());
})->throws(InvalidArgumentException::class, 'Config [modulus.store] must be a concrete class name');

it('rejects a missing modulus.store class', function () {
    config()->set('modulus.store', 'InEngine\\Modulus\\MissingStateStore');

    ModulusStateStoreResolver::resolve(app());
})->throws(InvalidArgumentException::class, 'Config [modulus.store] must be a concrete class name');

it('rejects a modulus.store class that does not implement the contract', function () {
    config()->set('modulus.store', stdClass::class);

    ModulusStateStoreResolver::resolve(app());
})->throws(InvalidArgumentException::class, 'must implement');

it('rejects when the container returns a non-contract instance for modulus.store', function () {
    $customClass = new class implements ModulusStateStore
    {
        public function all(): array
        {
            return [];
        }

        public function get(string $package): ?array
        {
            return null;
        }

        public function put(string $package, array $data): void {}

        public function isEnabled(string $package): bool
        {
            return false;
        }

        public function setEnabled(string $package, bool $enabled): void {}

        public function remove(string $package): void {}
    }::class;

    app()->bind($customClass, fn () => new stdClass);
    config()->set('modulus.store', $customClass);

    ModulusStateStoreResolver::resolve(app());
})->throws(InvalidArgumentException::class, 'Resolved [modulus.store] instance must implement');

it('resolves a custom store class bound in the container', function () {
    $custom = new class implements ModulusStateStore
    {
        /** @var array<string, array{enabled: bool, version: string|null}> */
        private array $modules = [];

        public function all(): array
        {
            return $this->modules;
        }

        public function get(string $package): ?array
        {
            return $this->modules[$package] ?? null;
        }

        public function put(string $package, array $data): void
        {
            $this->modules[$package] = [
                'enabled' => (bool) ($data['enabled'] ?? false),
                'version' => is_string($data['version'] ?? null) ? $data['version'] : null,
            ];
        }

        public function isEnabled(string $package): bool
        {
            return ($this->modules[$package]['enabled'] ?? false) === true;
        }

        public function setEnabled(string $package, bool $enabled): void
        {
            $entry = $this->get($package) ?? ['enabled' => false, 'version' => null];
            $entry['enabled'] = $enabled;
            $this->put($package, $entry);
        }

        public function remove(string $package): void
        {
            unset($this->modules[$package]);
        }
    };

    $customClass = $custom::class;
    app()->instance($customClass, $custom);
    config()->set('modulus.store', $customClass);

    expect(ModulusStateStoreResolver::resolve(app()))->toBe($custom);
});

it('rejects an empty modulus.state_path for the file store', function () {
    config()->set('modulus.store', FileModulusStateStore::class);
    config()->set('modulus.state_path', '');

    FileModulusStateStoreFactory::make(config(), new Filesystem);
})->throws(InvalidArgumentException::class, 'Config [modulus.state_path] must be a non-empty string path');
