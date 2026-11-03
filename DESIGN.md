# Modulus — Design

Package-local design notes for `inengine/modulus`. Canonical platform plan: [Plan and Design.md](../../Documentation/Plan%20and%20Design.md). Updated 2026-11-03.

## Purpose

Module runtime for InEngine: discover Composer Laravel packages that are modules, track enabled/disabled in a **file** (`ModulusStateStore`), sync enable/disable through Laravel **package discovery**, lifecycle hooks, Artisan (including **`modulus:doctor`**), and event bus + contracts/DTOs.

**Not in core:** marketplace browse/install, or capability inspect/compat UX — those are separate extensions that call Modulus APIs.

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
| Artisan | list, discover, enable, disable, uninstall, status, **doctor** |

## Module authoring (required template)

Modulus-compatible feature modules should be created from **`InEngine/module-skeleton`**: a customized Spatie Laravel package template that includes the base provider handshake, quality gates, and a **capability descriptor stub** (provides/requires, events, related packages, integrate notes). Hand-rolled packages are discouraged; inspect/compat tooling will assume the skeleton shape.

## Discovery scope

- **`modules` (default):** only Modulus-aware modules are gated; other Laravel packages discover normally.  
- **`all` (opt-in):** also enable/disable any auto-discovered Composer Laravel package via the same filter + state; never disable Modulus itself; denylist critical packages.

CLI/UI always list from Modulus state (installed/disabled/enabled), not from “currently booted providers.”

## Defense in depth

1. PackageManifest omits disabled providers (Step 3 — requires host early boot installer).  
2. Module providers still check Modulus and refuse to boot if disabled (Step 4).

## Extensions (not this package)

| Extension | Role |
|-----------|------|
| Marketplace | Browse/install from Packagist / private Packagist / Git → discover → disabled → enable |
| Capability catalog | Interrogate descriptors: what a module does, peers/sub-modules, events/contracts, how a custom module integrates (`modulus:inspect` / compat; CLI/API first) |

## Quality

SemVer, Pint, Pest, PHPStan required (same bar as Core and feature modules).

## Status

Step 0 done. Steps 1–2: `ModulusStateStore` + swappable store. Step 3: `FilteringPackageManifest` + `PackageManifestInstaller` (Core bootstrap wired when class present). Next: `ModuleRepository`, then handshake base provider (Step 4). Skeleton + capability catalog are later steps (12 / 14).
