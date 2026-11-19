# Modulus

[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/InEngine/Modulus/run-tests.yml?branch=feat%2Fmodulus-state-store&label=tests&style=flat-square)](https://github.com/InEngine/Modulus/actions?query=workflow%3Arun-tests)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/InEngine/Modulus/fix-php-code-style-issues.yml?branch=feat%2Fmodulus-state-store&label=code%20style&style=flat-square)](https://github.com/InEngine/Modulus/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE.txt)

Module runtime for [InEngine](https://github.com/InEngine): discover, enable, disable, publish, seed, upgrade, and diagnose Composer-based Laravel modules.

**CLI / API first.** UI is optional and must call the same PHP API. Enabled/disabled state lives in a **file** (`ModulusStateStore`); Modulus filters Laravel’s `PackageManifest` so disabled modules never load providers. Feature modules **register with Modulus** when it is present and stay disabled until enabled; without Modulus they boot as normal Spatie packages.

**Documentation:** [docs/](docs/) (Jekyll site under `/docs` for GitHub Pages when the org plan allows it).

## Status

Steps **0–9 + 8b** complete on `feat/modulus-state-store` (state store, discovery filter, repository, handshake, lifecycle/publish/seed, Composer deps, events/DTOs/Boost, Artisan + doctor + update notices).

**Step 10** lives in the sibling package **[Modulus Composer](https://github.com/InEngine/ModulusComposer)** (`inengine/modulus-composer`): Composer require/remove/update, `~x.y.z` constraint rewrites, and the real `ModuleUpdateApplier`. Marketplace / modulus-ui remain later.

## Requirements

- PHP ^8.5
- Laravel ^12 or ^13 (`illuminate/contracts`)

## Installation

```bash
composer require inengine/modulus
php artisan vendor:publish --tag="modulus-config"
```

No migrations for the registry (file-backed state under `storage/modulus/` by default).

### Early boot (required)

Laravel registers discovered providers before package providers boot. After `Application::configure(...)->create()`, install the filtered manifest:

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

### Local monorepo (InEngine Core)

Path-resolve via `composer.local.json` (merge plugin) → `../Modules/*`. Core requires `inengine/modulus` `*@dev`; do not hard-require Modulus Composer in Core.

## Install ≠ enable ≠ seed

| Step | What happens |
|------|----------------|
| Composer require | Code on disk only |
| `Modulus::publish()` / `modulus:publish` | Config + `resources/dist` → `public/vendor/{name}` (install/update path) |
| `modulus:enable` | Deps → lifecycle → migrate → discovery sync. **No** publish/seed |
| `Modulus::seed(..., confirmed: true)` | Explicit only; never on enable |

## Artisan

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

## Feature modules

Create from **[InEngine/Skeleton](https://github.com/InEngine/Skeleton)** (GitHub template, not Packagist):

- Package name `inengine/{feature}` — never `inengine/modulus-*`
- Extend `InEngine\Modulus\ModuleServiceProvider`
- `extra.laravel.providers` only
- Boot only when Modulus reports enabled; standalone full boot if Modulus is absent

## Extensions

| Package | Display name | Role |
|---------|--------------|------|
| [`inengine/modulus-composer`](https://github.com/InEngine/ModulusComposer) | **Modulus Composer** | Composer I/O + constraint rewrite + `ModuleUpdateApplier` |
| `inengine/modulus-marketplace` | (planned) | Browse / install sources |
| `inengine/modulus-ui` | (planned) | Operator UI |
| `inengine/modulus-inspect` | (planned) | Capability catalog |

## Testing / quality

```bash
composer test
composer analyse
composer format
```

## Further reading

- [docs/](docs/README.md) — user-facing documentation
- [DESIGN.md](DESIGN.md) — package design notes
- [INTEGRATION.md](INTEGRATION.md) — events, contracts, DTOs
- [CHANGELOG.md](CHANGELOG.md)

## Credits

- [James Johnson](https://github.com/excellentingenuity)

## License

The MIT License (MIT). See [LICENSE.txt](LICENSE.txt).
