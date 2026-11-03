# Modulus — Design

Package-local design notes for `inengine/modulus`. Canonical platform plan: [Plan and Design.md](../../Documentation/Plan%20and%20Design.md). Updated 2026-11-03.

## Purpose

Module runtime for InEngine: discover Composer Laravel packages that are modules, track enabled/disabled in a **file** (`ModulusStateStore`), sync enable/disable through Laravel **package discovery**, lifecycle hooks, Artisan (including **`modulus:doctor`**), and event bus + contracts/DTOs.

**Not in core:** marketplace browse/install UX — that is a separate extension that calls Modulus APIs after `composer require`.

## Collaborating parts (not a god object)

| Piece | Role |
|-------|------|
| `ModulusStateStore` | Atomic temp-write + swap of enabled/disabled state (swappable via `modulus.store`) |
| Repository / orchestrator | Enable, disable, uninstall, deps; list known enabled **and** disabled |
| `PackageManifest` sync | Filter effective auto-discovered providers (`discovery.scope`: `modules` default, optional `all`) |
| Base `ModuleServiceProvider` | Register-with-Modulus; **do not boot unless enabled** (defense in depth) |
| Lifecycle hooks | Non-destructive disable/uninstall; migrate on enable/upgrade |
| Events + contracts/DTOs | Inter-module integration |
| Artisan | list, discover, enable, disable, uninstall, status, **doctor** |

## Discovery scope

- **`modules` (default):** only Modulus-aware modules are gated; other Laravel packages discover normally.  
- **`all` (opt-in):** also enable/disable any auto-discovered Composer Laravel package via the same filter + state; never disable Modulus itself; denylist critical packages.

CLI/UI always list from Modulus state (installed/disabled/enabled), not from “currently booted providers.”

## Defense in depth

1. PackageManifest omits disabled providers.  
2. Module providers still check Modulus and refuse to boot if disabled.

## Marketplace (extension, not this package)

Separate package: discover/install from known module sources (Packagist, private Packagist, Git). Ends in discover → disabled → explicit enable.

## Quality

SemVer, Pint, Pest, PHPStan required (same bar as Core and feature modules).

## Status

Step 0 done (L13 / PHP 8.5 on `main`). Steps 1–2 underway (`ModulusStateStore` + swappable store config). Next: `ModuleRepository`, then PackageManifest sync + handshake.
