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
| `FilteringPackageManifest` | Read-time omit of disabled tracked packages from discovery |
| `PackageManifestInstaller` | Early `beforeBootstrapping(RegisterProviders)` rebind (host must call) |
| Base `ModuleServiceProvider` | Register-with-Modulus; **do not boot unless enabled** (defense in depth) |
| Lifecycle hooks | Non-destructive disable/uninstall; migrate on enable/upgrade |
| Events + contracts/DTOs | Inter-module integration |
| Artisan | list, discover, enable, disable, uninstall, status, **doctor** (incl. missing-module list + repair: install / supply source / substitute — Step 9) |

## Module authoring (required template + guidelines)

Modulus-compatible feature modules should be created from **`InEngine/module-skeleton`**: a customized Spatie Laravel package template that includes the base provider handshake, quality gates, and a **capability descriptor stub**. Hand-rolled packages are discouraged.

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
| `inengine/modulus-composer` | Composer require/remove/update; optional Composer hooks → discover → enable |
| `inengine/modulus-marketplace` | Browse/search known sources; uses composer client for install |
| `inengine/modulus-ui` | Operator UI over Modulus + extension APIs |
| `inengine/modulus-inspect` | Capability inspect / compat / integrate docs |

Feature modules remain `inengine/{feature}`, not `modulus-*`.

## Quality

SemVer, Pint, Pest, PHPStan required (same bar as Core and feature modules).

## Status

Steps 0–5 + handshake + missing-package validation complete. Next: Step 6 lifecycle. Doctor missing-module remediation (install / supply / substitute) planned under Step 9 + `modulus-composer`.
