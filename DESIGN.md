# Modulus — Design

Package-local design notes for `inengine/modulus`. Canonical platform plan: [Plan and Design.md](../../Documentation/Plan%20and%20Design.md). Updated 2026-11-03.

## Purpose

Module runtime for InEngine: discover Composer Laravel packages that are modules, track enabled/disabled in a **file** (`ModulusStateStore`), sync enable/disable through Laravel **package discovery**, lifecycle hooks, Artisan (including **`modulus:doctor`**), and event bus + contracts/DTOs.

**Not in core:** Composer require/remove/hooks, marketplace browse, operator UI, or capability inspect — those are `inengine/modulus-*` extension packages.

## Collaborating parts (not a god object)

| Piece | Role |
|-------|------|
| `ModulusStateStore` | Atomic temp-write + swap of enabled/disabled state (swappable via `modulus.store`) |
| Repository / orchestrator | Enable, disable, uninstall, deps; list known enabled **and** disabled |
| `ModuleDependencyGuard` | Composer `require` graph: Modulus deps must be enabled; non-Modulus deps installed-only |
| `FilteringPackageManifest` | Read-time omit of disabled tracked packages from discovery |
| `PackageManifestInstaller` | Early `beforeBootstrapping(RegisterProviders)` rebind (host must call) |
| Base `ModuleServiceProvider` | Register-with-Modulus; **do not boot unless enabled** (defense in depth) |
| Lifecycle hooks | `ModuleLifecycle` on base provider; `ResolvingModuleLifecycleRunner` instantiates provider from Composer extra without Laravel-registering it |
| `PathModulePublisher` | **Install/update** via `ModuleRepository::publish()` (modulus-composer): config + public assets by default; views/lang/inertia opt-in overrides |
| `PathModuleMigrator` | Pending migrations from `{installPath}/database/migrations` on enable / version-bump upgrade only |
| `ModuleSeeder` / `seed()` | Explicit only: `Modulus::seed($pkg, confirmed: true)`; skeleton seeder always requires confirmation; never on enable |
| Lifecycle events + `ModuleStateData` | Laravel bus after enable/disable/forget/upgrade/publish/seed |
| Contracts + DTOs (convention) | Interfaces + readonly payloads; container bindings — see `INTEGRATION.md` |
| Artisan | list, discover, enable, disable, uninstall, status, **doctor** (incl. missing-module list + repair: install / supply source / substitute — Step 9) |

## Module authoring (required template + guidelines)

Modulus-compatible feature modules should be created from **`InEngine/Skeleton`**: a customized Spatie Laravel package template that includes the base provider handshake, quality gates, and a **capability descriptor stub**. Hand-rolled packages are discouraged.

**Guidelines (also in Documentation plan + `.cursor/rules/modulus-module-guidelines.mdc`):**

1. Name: `inengine/{feature}` — never `inengine/modulus-*` (reserved for runtime extensions).  
2. Extend `ModuleServiceProvider`; boot only when enabled; standalone if Modulus absent.  
3. Discovery via `extra.laravel.providers` only.  
4. Install ≠ enable; Composer acquire/remove belongs in `modulus-composer` / marketplace, not the feature module.  
5. Ship capability descriptors for `modulus-inspect`.  
6. SemVer, Pint, Pest, PHPStan; non-destructive disable/uninstall.

## Discovery scope

- **`modules` (default):** only Modulus-aware modules are gated; other Laravel packages discover normally.  
- **`all` (opt-in):** also gate tracked non-module packages; never disable Modulus itself; denylist critical packages.

## Defense in depth (Step 4)

1. PackageManifest omits disabled providers (primary).  
2. Base `ModuleServiceProvider`: `packageRegistered()` → `ModuleRepository::register()`; `boot()` no-ops Spatie boot unless enabled; standalone full boot if Modulus absent.

## Extensions (`inengine/modulus-{role}`)

| Package | Role |
|---------|------|
| `inengine/modulus-composer` (**Modulus Composer**) | Composer require/remove/update; Artisan hooks → discover/publish (install ≠ enable) |
| `inengine/modulus-marketplace` | Browse/search known sources; uses composer client for install |
| `inengine/modulus-ui` | Operator UI over Modulus + extension APIs |
| `inengine/modulus-inspect` | Capability inspect / compat / integrate docs |

Feature modules remain `inengine/{feature}`, not `modulus-*`.

## Quality

SemVer, Pint, Pest, PHPStan required (same bar as Core and feature modules).

## Dependencies (Step 7)

- Source: installed package `composer.json` `require` (not capability tokens).  
- Modulus module among requires → must be installed and enabled (deny-list always enabled).  
- Plain Composer package among requires → must be installed only; still uses **normal Laravel discovery / vendor:publish**. Modules may optionally reach down on enable for extra config / `public/vendor` dist / migrations.  
- Disable / forget blocked while any **enabled** module lists the package in its `require`.

## Integration (Step 8)

- Dispatch `InEngine\Modulus\Events\*` after successful repository transitions.  
- Owning module: `ModuleLifecycle` hooks. Peers/host: event listeners.  
- Domain events: normal Laravel. Contracts/DTOs: provide interface + bind when enabled; no second plugin API.  
- Author guide: `INTEGRATION.md` (skeleton mirrors under `resources/INTEGRATION.md`).

## Agent guidelines (Step 8b)

- Boost package guidelines/skills under `resources/boost/` (loaded by host `boost:update` when `inengine/modulus` is in `boost.json` packages).
- Repo-local: `AGENTS.md`, `.ai/guidelines/`.

## Artisan + upgrades (Step 9)

- Full `modulus:*` CLI; doctor findings structured for modulus-ui.
- Default constraint policy **patch** (`~x.y.z`); approve minor/major via UI/CLI + modulus-composer.
- Enabled modules auto-upgrade schema/hooks when installed version changes on boot.
- Update notices: `modulus:outdated` / `ModuleUpdateAvailable` / doctor `update_available`; optional `modulus:update --auto` for patch-within-constraint when `auto_patch` + real applier.

## Status

Steps 0–9 + **8b** complete. Step 10 **Modulus Composer** (`inengine/modulus-composer`, repo `InEngine/ModulusComposer`): require/remove/update/approve, constraint rewrite, real `ModuleUpdateApplier`, discover/publish hooks (install ≠ enable). User docs under `docs/`. Marketplace / modulus-ui still later.
