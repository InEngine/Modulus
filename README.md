# Modulus

[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/InEngine/Modulus/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/InEngine/Modulus/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/InEngine/Modulus/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/InEngine/Modulus/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22+branch%3Amain)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE.txt)

Modulus is the **module runtime** for Laravel: it discovers Composer packages, tracks which ones are enabled or disabled in a file-backed store, filters Laravel package discovery so disabled modules never load providers, and runs lifecycle work (migrate, publish assets, seed, upgrade, doctor) through a CLI/API-first surface.

Feature modules register with Modulus when it is present and stay **disabled until enabled**. Without Modulus they boot as normal Spatie-style packages. Operator UI is optional and must call the same PHP API — it does not live in this package.

## Requirements

- PHP ^8.5
- Laravel ^12 or ^13 (`illuminate/contracts` ^12 || ^13)

## Installation

```bash
composer require inengine/modulus:^0.1@beta
php artisan vendor:publish --tag="modulus-config"
```

No migrations for the registry — state defaults to a JSON file under `storage/modulus/` (typically gitignored content).

> **InEngine Core:** Modulus is **required automatically**. [InEngine Core](https://github.com/InEngine/Core) already lists `inengine/modulus` in `composer.json` and installs the early-boot hook. You do **not** need a separate `composer require` when starting from Core.

Beta: `^0.1@beta`. Stable `^0.1` after the first non-beta tag. Monorepo develop: Core `composer.local.json` path `*@dev`.

## Using Modulus in a Laravel app (not InEngine Core)

Any Laravel 12/13 app can host Modulus. Core is optional.

1. **Require and publish** (see Installation above).
2. **Early boot (required).** Laravel registers discovered providers before package providers boot. After `Application::configure(...)->create()`, install the filtered manifest so enable/disable can gate discovery in time:

```php
// bootstrap/app.php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use InEngine\Modulus\Discovery\PackageManifestInstaller;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

if (class_exists(PackageManifestInstaller::class)) {
    PackageManifestInstaller::install($app);
}

return $app;
```

3. **Install ≠ enable ≠ seed**

| Step | What happens |
|------|----------------|
| `composer require` | Code on disk only |
| `Modulus::publish()` / `modulus:publish` | Config + `resources/dist` → `public/vendor/{name}` |
| `modulus:enable` | Deps → lifecycle → migrate → discovery sync. **No** publish/seed |
| `Modulus::seed(..., confirmed: true)` | Explicit only; never on enable |

4. **Feature modules** should extend `InEngine\Modulus\ModuleServiceProvider`, declare `modulusPackageName()`, and ship providers only in `extra.laravel.providers`. Create them from **[InEngine/Skeleton](https://github.com/InEngine/Skeleton)** (GitHub template, not Packagist).

5. Prefer `Modulus::…` / `modulus:*` over editing `storage/modulus/modules.json` by hand.

Optional Composer I/O (require/remove/update from Artisan) is **[Modulus Composer](https://github.com/InEngine/Modulus-Composer)** — opt-in; do not hard-require it unless the host needs that surface.

## Commands

```bash
php artisan modulus:list
php artisan modulus:discover
php artisan modulus:enable {package}
php artisan modulus:disable {package}
php artisan modulus:uninstall {package}   # non-destructive of DB; forget state
php artisan modulus:status {package}
php artisan modulus:publish {package}
php artisan modulus:seed {package}        # prompts / requires confirmation
php artisan modulus:upgrade {package?} {--publish} {--approve=}
php artisan modulus:outdated
php artisan modulus:update {--auto}       # --auto needs Modulus Composer applier + auto_patch
php artisan modulus:doctor
```

Default host constraint policy: **patch** → `~x.y.z`. Minor/major bumps need approval via **Modulus Composer** (`modulus-composer:approve`) or UI later.

## Extensions

| Package | Display name | Role |
|---------|--------------|------|
| [`inengine/modulus-composer`](https://github.com/InEngine/Modulus-Composer) | **Modulus Composer** | Composer require/remove/update/approve, constraint rewrite, `ModuleUpdateApplier` (public MIT; opt-in) |
| `inengine/modulus-marketplace` | (planned) | Browse / install sources |
| `inengine/modulus-ui` | (planned) | Operator UI — **private Pro** |
| `inengine/modulus-inspect` | (deferred) | Optional UX over in-core Capability Registry |

## Testing

```bash
composer test
composer analyse
composer format
```

## Further documentation

- [docs/](docs/README.md) — user-facing documentation
- [DESIGN.md](DESIGN.md) — package design notes
- [INTEGRATION.md](INTEGRATION.md) — events, contracts, DTOs
- [CHANGELOG.md](CHANGELOG.md)
- [InEngine Core](https://github.com/InEngine/Core) — opinionated host that requires Modulus
- [InEngine/Skeleton](https://github.com/InEngine/Skeleton) — feature-module GitHub template

## Credits

- [James Johnson](https://github.com/excellentingenuity)
- [Laravel](https://laravel.com) — underlying application framework hosts integrate with
- [Spatie](https://spatie.be) — package tools / skeleton patterns used by feature modules

## License

The MIT License (MIT). See [LICENSE.txt](LICENSE.txt).
