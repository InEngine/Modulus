<?php

use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Exceptions\CorruptModulusStateException;
use InEngine\Modulus\State\FileModulusStateStore;

/**
 * @return array{0: string, 1: string, 2: FileModulusStateStore}
 */
function modulusStateStoreFixture(): array
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-state-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';

    return [$directory, $path, new FileModulusStateStore($path)];
}

function modulusStateStoreCleanup(string $directory, string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }

    if (is_dir($directory)) {
        $remaining = glob($directory.DIRECTORY_SEPARATOR.'*') ?: [];

        foreach ($remaining as $file) {
            @unlink($file);
        }

        @rmdir($directory);
    }
}

it('returns an empty map when the state file is missing', function () {
    [$directory, $path, $store] = modulusStateStoreFixture();

    try {
        expect($store->all())->toBe([])
            ->and($store->get('inengine/alert'))->toBeNull()
            ->and($store->isEnabled('inengine/alert'))->toBeFalse();
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('creates the state file atomically when putting a module', function () {
    [$directory, $path, $store] = modulusStateStoreFixture();

    try {
        $store->put('inengine/alert', [
            'enabled' => false,
            'version' => '1.0.0',
        ]);

        expect(is_file($path))->toBeTrue()
            ->and($store->get('inengine/alert'))->toEqual([
                'enabled' => false,
                'version' => '1.0.0',
            ]);

        $decoded = json_decode((string) file_get_contents($path), true);

        expect($decoded)->toHaveKey('inengine/alert')
            ->and($decoded['inengine/alert']['enabled'])->toBeFalse()
            ->and($decoded['inengine/alert']['version'])->toBe('1.0.0');
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('treats newly set packages as disabled until enabled', function () {
    [$directory, $path, $store] = modulusStateStoreFixture();

    try {
        $store->setEnabled('inengine/calendar', true);

        expect($store->isEnabled('inengine/calendar'))->toBeTrue()
            ->and($store->get('inengine/calendar'))->toEqual([
                'enabled' => true,
                'version' => null,
            ]);

        $store->setEnabled('inengine/calendar', false);

        expect($store->isEnabled('inengine/calendar'))->toBeFalse();
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('defaults missing enabled flags to false when normalizing entries', function () {
    [$directory, $path, $store] = modulusStateStoreFixture();

    try {
        $store->put('inengine/table-ui', [
            'version' => '2.0.0',
        ]);

        expect($store->isEnabled('inengine/table-ui'))->toBeFalse()
            ->and($store->get('inengine/table-ui'))->toEqual([
                'enabled' => false,
                'version' => '2.0.0',
            ]);
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('removes a tracked package from the state file', function () {
    [$directory, $path, $store] = modulusStateStoreFixture();

    try {
        $store->put('inengine/alert', [
            'enabled' => true,
            'version' => '1.0.0',
        ]);

        $store->remove('inengine/alert');

        expect($store->all())->toBe([])
            ->and($store->get('inengine/alert'))->toBeNull();
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('throws when the state file contains invalid json', function () {
    [$directory, $path, $store] = modulusStateStoreFixture();

    try {
        mkdir($directory, 0755, true);
        file_put_contents($path, '{not-json');

        expect(fn () => $store->all())->toThrow(CorruptModulusStateException::class);
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('throws when the state file is a json list instead of an object', function () {
    [$directory, $path, $store] = modulusStateStoreFixture();

    try {
        mkdir($directory, 0755, true);
        // Non-empty list uses integer keys after json_decode — invalid package names.
        file_put_contents($path, '[{"enabled":true,"version":null}]');

        expect(fn () => $store->all())->toThrow(CorruptModulusStateException::class);
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('treats an empty json object as an empty module map', function () {
    [$directory, $path, $store] = modulusStateStoreFixture();

    try {
        mkdir($directory, 0755, true);
        file_put_contents($path, '{}');

        expect($store->all())->toBe([]);
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('resolves the default file store from the container using config', function () {
    [$directory, $path] = modulusStateStoreFixture();

    try {
        config()->set('modulus.store', FileModulusStateStore::class);
        config()->set('modulus.state_path', $path);

        app()->forgetInstance(ModulusStateStore::class);

        $resolved = app(ModulusStateStore::class);

        expect($resolved)->toBeInstanceOf(FileModulusStateStore::class);

        $resolved->put('inengine/alert', [
            'enabled' => true,
            'version' => '0.1.0',
        ]);

        expect($resolved->isEnabled('inengine/alert'))->toBeTrue()
            ->and(is_file($path))->toBeTrue();
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('resolves a custom store class from modulus.store config', function () {
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
    app()->forgetInstance(ModulusStateStore::class);

    $resolved = app(ModulusStateStore::class);

    expect($resolved)->toBe($custom);

    $resolved->setEnabled('inengine/alert', true);

    expect($resolved->isEnabled('inengine/alert'))->toBeTrue();
});

it('rejects a modulus.store class that does not implement the contract', function () {
    config()->set('modulus.store', stdClass::class);
    app()->forgetInstance(ModulusStateStore::class);

    app(ModulusStateStore::class);
})->throws(InvalidArgumentException::class);
