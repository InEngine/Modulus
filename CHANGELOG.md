# Changelog

All notable changes to `Modulus` will be documented in this file.

## Unreleased

### Added

- `ModuleExtensionGate` for companion macros / `resolveRelationUsing` (tokens, packages, configKeys + `whenReady()`)
- Library module kind (`kind: library`): default-enabled on register; dependency guard treats installed libraries as satisfied; `enable()` promotes Composer-required libraries; re-register heals stale disabled library state; cannot disable/forget; stay in package discovery like Modulus core (deny list)
- Step 14 Capability Registry (in-core): persist descriptor metadata on state; `CapabilityRegistry` query API; `modulus:capabilities` Artisan; `ModuleCapabilityLocator` for disabled modules

## 0.1.0-beta.2 - 2026-11-20

### Changed

- `extra.branch-alias` `dev-main` → `0.1.x-dev` so path `*@dev` resolves against `^0.1@beta` dependents
- Docs: Packagist beta + Step 13 Alert pilot notes

## 0.1.0-beta.1 - 2026-11-20

### Added

- First public beta on Packagist / GitHub Releases

### Changed

- Relicense as MIT
- Packagist-ready metadata (homepage, description)

### Step 9 — Artisan surface, doctor, upgrades + update notices

- Commands: `modulus:list|discover|enable|disable|uninstall|status|publish|seed|upgrade|outdated|update|doctor`
- `ModuleRepository::discover()`, `upgrade()`, `upgradeAll()`
- Auto-apply installed Composer version on enabled-module handshake (`modulusPackageVersion()` default)
- SemVer policy config `modulus.upgrades` (default `patch` → `~x.y.z`); doctor warns on looser host constraints
- Available-update notices: `ModuleUpdateChecker` / `ModuleUpdateAvailable` / `modulus:outdated` for CLI + modulus-ui
- Optional non-breaking auto-patch via `modulus.updates.auto_patch` + `ModuleUpdateApplier` (`modulus:update --auto`; Null until modulus-composer)
- `ModulusDoctor` + `DoctorReport`/`DoctorFinding` (incl. `update_available`) for CLI and modulus-ui
- `ModuleManualAttentionRequired` + `modulusManualAttentionItems()` for post-upgrade operator checklists
- `modulus:upgrade --approve=` messages constraint bump (Composer rewrite stays modulus-composer / UI)

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
