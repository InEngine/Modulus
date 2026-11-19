# Installation

## Composer

```bash
composer require inengine/modulus
php artisan vendor:publish --tag="modulus-config"
```

Config defaults to a JSON state file under `storage/modulus/modules.json` (typically gitignored).

### Monorepo / path develop

InEngine Core resolves `inengine/modulus` `*@dev` through `composer.local.json` path repos (`../Modules/*`). CI checkouts Modulus beside Core the same way.

## Early boot

Call the installer after the application is created so Modulus can rebind `PackageManifest` before providers register:

```php
use InEngine\Modulus\Discovery\PackageManifestInstaller;

$app = Application::configure(basePath: dirname(__DIR__))
    // ...
    ->create();

if (class_exists(PackageManifestInstaller::class)) {
    PackageManifestInstaller::install($app);
}

return $app;
```

Without this, enable/disable cannot gate discovery in time.

## Optional: Modulus Composer

Hosts that should run `composer require` / constraint rewrites from Artisan opt into **[Modulus Composer](modulus-composer.md)** separately. Core must **not** hard-require it.
