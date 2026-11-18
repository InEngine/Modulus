# Changelog

All notable changes to `Modulus` will be documented in this file.

## Unreleased

### Step 8b — Laravel Boost agent guidelines

- `resources/boost/guidelines/core.blade.php` + `resources/boost/skills/modulus-modules` for consuming hosts
- Repo `AGENTS.md`, `.ai/guidelines/inengine-modulus.md`

### Step 8 — Event bus + contracts/DTOs

- Lifecycle events: `ModuleRegistered`, `ModuleEnabled`, `ModuleDisabled`, `ModuleForgotten`, `ModuleUpgraded`, `ModulePublished`, `ModuleSeeded`
- Shared DTO: `DataTransferObjects\ModuleStateData`
- `ModuleRepository` dispatches via Laravel `Dispatcher` after successful transitions
- Author guide: `INTEGRATION.md` (container contracts/DTOs + domain events; no second plugin API)

### Step 7 — Module dependencies

- `ModuleDependencyGuard` / `ComposerModuleDependencyGuard`: Composer `require` graph on enable / disable / forget
- Modulus modules among requires must be enabled; non-Modulus packages must only be installed (still use normal Laravel discovery / `vendor:publish`)
- `UnmetModuleDependencyException`, `ModuleHasDependentsException`
- `InstalledPackageLocator::composerRequires()` (skips platform php/ext/lib/composer-*)

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
