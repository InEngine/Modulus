# Changelog

All notable changes to `Modulus` will be documented in this file.

## Unreleased

### Step 6 — Lifecycle, publish, seed

- Lifecycle hooks on `ModuleServiceProvider` (`activating` / `activated` / `deactivating` / `deactivated` / `uninstalling` / `uninstalled` / `upgrading` / `upgraded`) via `ResolvingModuleLifecycleRunner`
- Enable runs pending migrations (`PathModuleMigrator`); version-bump while enabled runs upgrade + migrate
- Disable / `forget` / `uninstall` stay non-destructive (no schema wipe; Composer remove deferred to `modulus-composer`)
- `Modulus::publish()` for **install/update** (not enable): config + `resources/dist` → `public/vendor/{name}`; views/lang/inertia opt-in
- `Modulus::seed($package, confirmed: true)` only; skeleton seeders require confirmation; never seed on enable
- `InstalledPackageLocator` gains `installPath()` and `laravelProviderClasses()`
- Lazy Laravel `migrator` resolve so package:discover works before DB migrator is bound
- Config: `modulus.migrations`, `modulus.publish`
- Pest coverage expanded (95 tests)
