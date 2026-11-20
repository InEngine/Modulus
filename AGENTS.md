# InEngine Modulus

Composer package `inengine/modulus` — module runtime for InEngine (PHP ^8.5, illuminate/contracts ^12||^13).

## Build and test

- `composer test` / `vendor/bin/pest`
- `composer analyse` / `vendor/bin/phpstan analyse`
- `vendor/bin/pint --dirty`

## Project docs

- User docs (GitHub Pages): `docs/`
- `DESIGN.md`, `INTEGRATION.md`, `CHANGELOG.md`
- Boost package guidelines (for hosts): `resources/boost/guidelines/core.blade.php`
- Repo guidelines: `.ai/guidelines/inengine-modulus.md`
- Cursor: `.cursor/rules/modulus-module-guidelines.mdc`

<laravel-boost-guidelines>
=== foundation rules ===

# Package agent rules

- Follow existing conventions in sibling files when editing.
- Prefer PHPDoc blocks over inline comments unless logic is exceptionally complex.
- Update `CHANGELOG.md` Unreleased when behavior changes; update `DESIGN.md` for architectural shifts.
- Do not add Composer require/remove hooks, marketplace browse, or operator UI into this package — those are `inengine/modulus-*` extensions.
- Be concise in replies.

=== inengine/modulus rules ===

# inengine/modulus

Module runtime: file-backed enable/disable, `PackageManifest` filtering, handshake via `ModuleServiceProvider`, lifecycle hooks, Composer dependency guards, `publish`/`seed`, lifecycle events.

Prefer `Modulus` facade / `ModuleRepository` over the state store or discovery syncer directly.

## Install ≠ enable

1. Composer installs the package.
2. `Modulus::publish($package)` on install/update (config + `resources/dist` → `public/vendor/{name}`).
3. Default **disabled** until `Modulus::enable($package)`.
4. Enable: deps → activating → migrate → state → sync → activated. No publish/seed on enable.
5. Seed only: `Modulus::seed($package, confirmed: true)`.

## Naming

- Runtime: `inengine/modulus`
- Extensions: `inengine/modulus-{role}` (`composer`, `marketplace`, `ui`, `inspect`)
- Features: `inengine/{feature}` from `InEngine/Skeleton`

## Dependencies

- Graph = `composer.json` `require` (skip platform php/ext/composer-*).
- Modulus deps: installed + enabled. Plain packages: installed only; normal Laravel discovery.
- Optional reach-down from `activating()` for config / UI dist / migrations.
- Block disable/forget while enabled dependents require the package.

## Integration

- Owning module: `ModuleLifecycle` hooks.
- Peers/host: `InEngine\Modulus\Events\*` + `ModuleStateData`.
- Contracts + DTOs via container bindings. No custom plugin registry. See `INTEGRATION.md`.

## Quality

- Pest, PHPStan, Pint. PHPDoc over inline comments.

</laravel-boost-guidelines>

## Git

- Work on **`dev`**; open a PR into **`main`**. See InEngine Documentation `Git workflow.md`.
