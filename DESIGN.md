# Modulus — Design

Package-local design notes for `inengine/modulus`. Canonical platform plan: [Plan and Design.md](../../Documentation/Plan%20and%20Design.md). Updated 2026-09-08.

## Purpose

Module runtime for InEngine: discover Composer Laravel packages that are modules, track enabled/disabled in a **file** (`ModulusStateStore`), sync enable/disable through Laravel **package discovery**, lifecycle hooks, Artisan (including **`modulus:doctor`**), event bus + contracts/DTOs, and a Composer/Packagist/VCS **submodule** for acquiring packages.

## Collaborating parts (not a god object)

| Piece | Role |
|-------|------|
| `ModulusStateStore` | Atomic temp-write + swap of enabled/disabled state |
| Repository / orchestrator | Enable, disable, uninstall, deps |
| `PackageManifest` sync | Filter effective auto-discovered module providers |
| Base `ModuleServiceProvider` | Register-with-Modulus handshake |
| Lifecycle hooks | Non-destructive disable/uninstall; migrate on enable/upgrade |
| Events + contracts/DTOs | Inter-module integration |
| Composer submodule | Packagist / private Packagist / Git require & remove |
| Artisan | list, discover, enable, disable, uninstall, status, **doctor** |

## Quality

SemVer, Pint, Pest, PHPStan required (same bar as Core and feature modules).

## Status

Step 0 done (L13 / PHP 8.5 on `main`). Implementing Steps 1–2 (`ModulusStateStore` + service split) per Documentation `Plan and Design.md` (Steps 0–13).
