<?php

use Illuminate\Filesystem\Filesystem;
use InEngine\Modulus\Contracts\ModulusStateStore;
use InEngine\Modulus\Exceptions\CorruptModulusStateException;
use InEngine\Modulus\Exceptions\InvalidModulusStateStoreException;
use InEngine\Modulus\State\FileModulusStateStore;
use InEngine\Modulus\State\FileModulusStateStoreFactory;

/**
 * @return array{0: string, 1: string, 2: FileModulusStateStore}
 */
function modulusStateStoreFixture(bool $fsync = false): array
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-state-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';

    return [$directory, $path, new FileModulusStateStore($path, new Filesystem, $fsync)];
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

/**
 * @param  array<string, array{enabled: bool, version: string|null}>  $state
 */
function modulusStateStoreSeed(string $directory, string $path, array $state): void
{
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents(
        $path,
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR)
    );
}

/**
 * @return array<string, array{enabled: bool, version: string|null}>
 */
function modulusStateStoreReadDisk(string $path): array
{
    /** @var array<string, array{enabled: bool, version: string|null}> $decoded */
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
}

dataset('modulus_write_modes', [
    'filesystem replace' => [false],
    'fsync' => [true],
]);

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

it('isFile+get: missing file before yields empty, seed after yields content', function (bool $fsync) {
    [$directory, $path, $store] = modulusStateStoreFixture($fsync);

    $after = [
        'inengine/alert' => [
            'enabled' => true,
            'version' => '1.0.0',
        ],
    ];

    try {
        expect(is_file($path))->toBeFalse()
            ->and($store->all())->toBe([])
            ->and($store->get('inengine/alert'))->toBeNull();

        modulusStateStoreSeed($directory, $path, $after);

        expect(is_file($path))->toBeTrue()
            ->and($store->all())->toEqual($after)
            ->and($store->get('inengine/alert'))->toEqual($after['inengine/alert']);
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
})->with('modulus_write_modes');

it('isFile+get: empty file before yields empty map for both write modes', function (bool $fsync) {
    [$directory, $path, $store] = modulusStateStoreFixture($fsync);

    try {
        mkdir($directory, 0755, true);
        file_put_contents($path, '   ');

        expect(is_file($path))->toBeTrue()
            ->and($store->all())->toBe([]);
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
})->with('modulus_write_modes');

it('ensureDirectoryExists: missing directory before write exists after', function (bool $fsync) {
    [$directory, $path, $store] = modulusStateStoreFixture($fsync);

    try {
        expect(is_dir($directory))->toBeFalse()
            ->and(is_file($path))->toBeFalse();

        $store->put('inengine/alert', [
            'enabled' => false,
            'version' => null,
        ]);

        expect(is_dir($directory))->toBeTrue()
            ->and(is_file($path))->toBeTrue()
            ->and(modulusStateStoreReadDisk($path))->toEqual([
                'inengine/alert' => [
                    'enabled' => false,
                    'version' => null,
                ],
            ]);
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
})->with('modulus_write_modes');

it('replace/move write: transitions enable map before to after for both modes', function (bool $fsync) {
    [$directory, $path, $store] = modulusStateStoreFixture($fsync);

    $before = [
        'inengine/alert' => [
            'enabled' => false,
            'version' => '1.0.0',
        ],
        'inengine/calendar' => [
            'enabled' => true,
            'version' => '2.0.0',
        ],
    ];

    $after = [
        'inengine/alert' => [
            'enabled' => true,
            'version' => '1.0.0',
        ],
        'inengine/calendar' => [
            'enabled' => false,
            'version' => '2.0.0',
        ],
        'inengine/table-ui' => [
            'enabled' => false,
            'version' => '3.0.0',
        ],
    ];

    try {
        modulusStateStoreSeed($directory, $path, $before);

        expect($store->all())->toEqual($before)
            ->and(modulusStateStoreReadDisk($path))->toEqual($before);

        $store->setEnabled('inengine/alert', true);
        $store->setEnabled('inengine/calendar', false);
        $store->put('inengine/table-ui', [
            'enabled' => false,
            'version' => '3.0.0',
        ]);

        expect($store->all())->toEqual($after)
            ->and(modulusStateStoreReadDisk($path))->toEqual($after);
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
})->with('modulus_write_modes');

it('replace/move write: remove one package before to after for both modes', function (bool $fsync) {
    [$directory, $path, $store] = modulusStateStoreFixture($fsync);

    $before = [
        'inengine/alert' => [
            'enabled' => true,
            'version' => '1.0.0',
        ],
        'inengine/calendar' => [
            'enabled' => false,
            'version' => '2.0.0',
        ],
    ];

    $after = [
        'inengine/calendar' => [
            'enabled' => false,
            'version' => '2.0.0',
        ],
    ];

    try {
        modulusStateStoreSeed($directory, $path, $before);

        expect(modulusStateStoreReadDisk($path))->toEqual($before);

        $store->remove('inengine/alert');

        expect($store->all())->toEqual($after)
            ->and(modulusStateStoreReadDisk($path))->toEqual($after)
            ->and($store->get('inengine/alert'))->toBeNull();
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
})->with('modulus_write_modes');

it('replace/move write: remove last package leaves empty object on disk', function (bool $fsync) {
    [$directory, $path, $store] = modulusStateStoreFixture($fsync);

    $before = [
        'inengine/alert' => [
            'enabled' => true,
            'version' => '1.0.0',
        ],
    ];

    try {
        modulusStateStoreSeed($directory, $path, $before);

        expect(modulusStateStoreReadDisk($path))->toEqual($before);

        $store->remove('inengine/alert');

        expect($store->all())->toBe([])
            ->and(file_get_contents($path))->toBe('{}')
            ->and(modulusStateStoreReadDisk($path))->toBe([]);
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
})->with('modulus_write_modes');

it('fsync move leaves no temp files after a successful write', function () {
    [$directory, $path, $store] = modulusStateStoreFixture(fsync: true);

    try {
        $store->put('inengine/alert', [
            'enabled' => true,
            'version' => '1.0.0',
        ]);

        $temps = glob($directory.DIRECTORY_SEPARATOR.'modules.json.*') ?: [];

        expect(is_file($path))->toBeTrue()
            ->and($temps)->toBe([])
            ->and(modulusStateStoreReadDisk($path))->toEqual([
                'inengine/alert' => [
                    'enabled' => true,
                    'version' => '1.0.0',
                ],
            ]);
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('uses Filesystem replace when state_fsync is false', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-state-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';

    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('isFile')->with($path)->andReturn(false);
    $files->shouldReceive('ensureDirectoryExists')->once()->with($directory);
    $files->shouldReceive('replace')
        ->once()
        ->withArgs(function (string $writePath, string $payload) use ($path): bool {
            return $writePath === $path
                && str_contains($payload, 'inengine/alert')
                && str_contains($payload, '"enabled": true');
        });

    $store = new FileModulusStateStore($path, $files, fsync: false);

    $store->put('inengine/alert', [
        'enabled' => true,
        'version' => '1.0.0',
    ]);
});

it('uses fsync write path and never calls Filesystem replace when state_fsync is true', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-state-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    mkdir($directory, 0755, true);

    $files = Mockery::mock(Filesystem::class)->makePartial();
    $files->shouldReceive('replace')->never();

    $store = new FileModulusStateStore($path, $files, fsync: true);

    try {
        $store->put('inengine/calendar', [
            'enabled' => false,
            'version' => '2.0.0',
        ]);

        expect(is_file($path))->toBeTrue()
            ->and($store->get('inengine/calendar'))->toEqual([
                'enabled' => false,
                'version' => '2.0.0',
            ]);
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('delete: cleans up temp file when fsync move fails', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-state-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    mkdir($directory, 0755, true);

    $files = Mockery::mock(Filesystem::class)->makePartial();
    $files->shouldReceive('replace')->never();
    $files->shouldReceive('move')->once()->andReturn(false);
    $files->shouldReceive('delete')
        ->once()
        ->withArgs(fn (string $tempPath): bool => str_starts_with($tempPath, $path.'.'));

    $store = new FileModulusStateStore($path, $files, fsync: true);

    try {
        expect(fn () => $store->put('inengine/alert', [
            'enabled' => true,
            'version' => null,
        ]))->toThrow(RuntimeException::class);

        expect(is_file($path))->toBeFalse();
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('factory honors modulus.state_fsync config', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-state-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';

    config()->set('modulus.state_path', $path);
    config()->set('modulus.state_fsync', true);

    $store = FileModulusStateStoreFactory::make(config(), new Filesystem);

    $reflection = new ReflectionClass($store);
    $fsync = $reflection->getProperty('fsync');

    expect($fsync->getValue($store))->toBeTrue();

    try {
        $store->put('inengine/alert', [
            'enabled' => true,
            'version' => null,
        ]);

        expect(is_file($path))->toBeTrue()
            ->and($store->isEnabled('inengine/alert'))->toBeTrue();
    } finally {
        modulusStateStoreCleanup($directory, $path);
    }
});

it('resolves the default file store from the container using config', function () {
    [$directory, $path] = modulusStateStoreFixture();

    try {
        config()->set('modulus.store', FileModulusStateStore::class);
        config()->set('modulus.state_path', $path);
        config()->set('modulus.state_fsync', true);

        app()->forgetInstance(ModulusStateStore::class);

        $resolved = app(ModulusStateStore::class);

        expect($resolved)->toBeInstanceOf(FileModulusStateStore::class);

        $resolved->put('inengine/alert', [
            'enabled' => true,
            'version' => '0.1.0',
        ]);

        expect($resolved->isEnabled('inengine/alert'))->toBeTrue()
            ->and(is_file($path))->toBeTrue();

        $reflection = new ReflectionClass($resolved);
        $fsync = $reflection->getProperty('fsync');

        expect($fsync->getValue($resolved))->toBeTrue();
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

it('throws InvalidModulusStateStoreException when modulus.store does not implement the contract', function () {
    config()->set('modulus.store', stdClass::class);
    app()->forgetInstance(ModulusStateStore::class);

    expect(fn () => app(ModulusStateStore::class))
        ->toThrow(InvalidModulusStateStoreException::class);
});
