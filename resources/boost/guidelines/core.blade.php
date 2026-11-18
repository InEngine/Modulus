## inengine/modulus

Module runtime for InEngine hosts: file-backed enable/disable state, Laravel `PackageManifest` filtering, register-with-Modulus handshake, lifecycle hooks, Composer dependency guards, publish/seed APIs, and lifecycle events.

Prefer `InEngine\Modulus\Facades\Modulus` / `ModuleRepository` over touching the state store or discovery syncer directly.

### Install ≠ enable

1. Composer require (or path `@dev`) installs the package.
2. `Modulus::publish($package)` on install/update (config + `resources/dist` → `public/vendor/{name}`) — owned by `inengine/modulus-composer` later; call manually until then.
3. Packages default **disabled** until `Modulus::enable($package)` (or future `modulus:enable`).
4. Enable runs dependency checks → activating → migrate → state → discovery sync → activated. It does **not** publish assets or seed.
5. Seed only via `Modulus::seed($package, confirmed: true)`.

### Naming

- Runtime: `inengine/modulus`
- Extensions: `inengine/modulus-{role}` only (`composer`, `marketplace`, `ui`, `inspect`) — never fold Composer hooks or operator UI into this package
- Feature modules: `inengine/{feature}` from `InEngine/module-skeleton` — never `modulus-*`

### Feature modules

- Extend `InEngine\Modulus\ModuleServiceProvider`; declare `modulusPackageName()`.
- Discovery only via `extra.laravel.providers`.
- Migrations on enable/upgrade; disable/forget/uninstall are non-destructive (no schema wipe).
- Migrations must guard `Schema::hasTable` for re-enable after non-destructive uninstall.

### Dependencies (Step 7)

- Hard graph = package `composer.json` `require` (skip php/ext/composer-* platform keys).
- Required **Modulus modules** must be installed **and** enabled before enable.
- **Non-Modulus** Composer packages must be installed only; they keep normal Laravel discovery / `vendor:publish`.
- Modules may optionally reach down in `activating()` / `bootstrapNonModulusDependencies()` for config, UI dist → `public/vendor`, or migrations.
- Cannot disable/forget while enabled dependents still require the package.

### Integration (Step 8)

- Owning module side effects: `ModuleLifecycle` hooks on the provider.
- Peers/host: listen to `InEngine\Modulus\Events\*` (`ModuleEnabled`, `ModuleDisabled`, `ModuleForgotten`, `ModuleUpgraded`, `ModuleRegistered`, `ModulePublished`, `ModuleSeeded`). Payload often includes `ModuleStateData`.
- Cross-module APIs: PHP interfaces + readonly DTOs, bound in the Laravel container when the providing module is enabled. No custom plugin registry.
- Author detail: package `INTEGRATION.md`.

### Host wiring (Core)

- Require `inengine/modulus` in the host `composer.json`.
- Call `PackageManifestInstaller` early so filtering is active before feature providers load.
- Publish Modulus config; keep `modulus.state_path` writable.
- Do not put product features or a default `User` model in Core.

### Tests & quality

- Pest + PHPStan + Pint; PHP `^8.5`, Laravel 12/13 contracts.
- Prefer PHPDoc over inline comments.
- Canonical plan: InEngine Documentation `Plan and Design.md`.
