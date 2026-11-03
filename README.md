# Modulus

[![Latest Version on Packagist](https://img.shields.io/packagist/v/inengine/modulus.svg?style=flat-square)](https://packagist.org/packages/inengine/modulus)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/inengine/modulus/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/inengine/modulus/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/inengine/modulus/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/inengine/modulus/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/inengine/modulus.svg?style=flat-square)](https://packagist.org/packages/inengine/modulus)
[![CC BY-NC-SA 4.0](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-lightgrey.svg)](http://creativecommons.org/licenses/by-nc-sa/4.0/)

Module system for [InEngine](https://github.com/InEngine): discover, enable, disable, and uninstall Composer-based Laravel modules. **CLI / API first**; UI is optional and must share the same API. Enabled/disabled state is tracked in a **file**, then synced so Laravel’s package discovery only loads enabled module providers. Feature modules **register with Modulus** when it is present and stay disabled until enabled.

See [DESIGN.md](DESIGN.md) and the platform [Plan and Design](../../Documentation/Plan%20and%20Design.md) (stepped plan 0–13: `ModulusStateStore`, PackageManifest sync, doctor, Composer submodule, non-destructive lifecycle).

## Status

Laravel 13 / PHP 8.5 baseline is on `main` ([PR #7](https://github.com/InEngine/Modulus/pull/7) merged). `ModulusStateStore` + PackageManifest filtering (Step 3) are in progress on `feat/modulus-state-store`. Next: `ModuleRepository`, self-gating `ModuleServiceProvider`, Artisan lifecycle commands (including `modulus:doctor`).

## Installation

```bash
composer require inengine/modulus
php artisan vendor:publish --tag="modulus-config"
```

No migrations are required for the module registry (file-backed state).

### Early boot (required for enable/disable)

Laravel registers discovered providers before package service providers run. After `Application::configure(...)->create()`, call the installer so Modulus can filter `PackageManifest` in time:

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

Disabled packages tracked in the Modulus state file are then omitted from auto-discovered providers/aliases. Listing CLI/UI still sees them via the state store.

## Intended Artisan surface

Every UI capability must have a matching Artisan command (same underlying API):

```bash
php artisan modulus:list
php artisan modulus:discover
php artisan modulus:enable {module}
php artisan modulus:disable {module}
php artisan modulus:uninstall {module}
php artisan modulus:status {module}
```

## Module packages

Feature modules are Spatie-style Laravel packages that:

- Register via `extra.laravel.providers` only
- Extend Modulus’s base `ModuleServiceProvider`: if Modulus exists, **register with it** and boot only when enabled; if not, boot normally (standalone)
- Rely on Modulus to sync enable/disable into Laravel’s effective package-discovery / provider load set

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [James Johnson](https://github.com/excellentingenuity)
- [All Contributors](../../contributors)

## License

CC BY-NC-SA 4.0. Please see the [License File](LICENSE.txt) for more information.
