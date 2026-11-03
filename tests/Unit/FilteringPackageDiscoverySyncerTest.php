<?php

use Illuminate\Foundation\PackageManifest;
use InEngine\Modulus\Discovery\FilteringPackageDiscoverySyncer;
use InEngine\Modulus\Discovery\FilteringPackageManifest;
use InEngine\Modulus\State\FileModulusStateStore;

it('sync forgets the bound PackageManifest instance', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulus-sync-'.uniqid('', true);
    $path = $directory.DIRECTORY_SEPARATOR.'modules.json';
    mkdir($directory, 0755, true);

    $store = new FileModulusStateStore($path);
    $manifest = new FilteringPackageManifest(
        app('files'),
        $directory,
        $directory.DIRECTORY_SEPARATOR.'packages.php',
        $store,
    );

    file_put_contents($directory.DIRECTORY_SEPARATOR.'packages.php', '<?php return [];');

    app()->instance(PackageManifest::class, $manifest);

    expect(app()->bound(PackageManifest::class))->toBeTrue()
        ->and(app(PackageManifest::class))->toBe($manifest);

    (new FilteringPackageDiscoverySyncer(app()))->sync();

    expect(app()->bound(PackageManifest::class))->toBeTrue();

    // Instance was forgotten; resolving again should not be the same object.
    $resolved = app(PackageManifest::class);

    expect($resolved)->not->toBe($manifest);

    foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($directory);
});
