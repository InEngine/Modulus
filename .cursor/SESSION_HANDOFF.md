# Session handoff (Modulus)

Use this file so new chats pick up **package work**, **branch focus**, and **next steps**. Newest sessions at the top below the intro. **No secrets.**

**Repo:** https://github.com/InEngine/Modulus — Composer `inengine/modulus`. GitHub project: [InEngine #2](https://github.com/orgs/InEngine/projects/2).

## Platform map

| Consumer | Path | Local require |
|----------|------|----------------|
| InEngine Core | `…/InEngine/Core` | `composer.local.json` → `../Modules/*` `@dev` |
| Provost | `…/Provost/web` | `composer.local.json` → `../../InEngine/Modules/*` `@dev` |
| LTC (legacy) | `…/LegacyTradeCollege/app` | path to Modules when needed |

## How to update

1. Paste a new block **below** `<!-- SESSION_LOG_START -->`.
2. Include branch, PR/issue links, and test commands run.

<!-- SESSION_LOG_START -->

### 2026-11-03 — Design: scope=all + marketplace extension

- **Branch:** `feat/modulus-state-store`
- **Done:** Locked design: optional `discovery.scope=all` for non-module packages; marketplace browse/install is a **separate extension** (not Modulus core); defense in depth required (PackageManifest + provider must not boot unless enabled). Docs: Documentation `Plan and Design.md` + Modulus `DESIGN.md`.
- **Next:** `ModuleRepository` + facade; PackageManifest sync with scope config; handshake base provider.
- **Open questions:** Marketplace package name; denylist defaults for `scope=all`.

### 2026-11-03 — ModulusStateStore (Steps 1–2 start)

- **Branch:** `feat/modulus-state-store` (from `main` after PR #7 merge).
- **Done:** `ModulusStateStore` + swappable `modulus.store` (default file JSON); Pest; attribution hook/rule; DESIGN.md.
- **Next:** `ModuleRepository` + facade rewire; drop old `Modulus` config rewrite; then PackageManifest sync (Step 3).
- **Open questions:** (see newer entry)

### 2026-09-08 — Session pause (design locked; implement next)

- **Branch:** `feat/laravel-13-support` — PR **[#7](https://github.com/InEngine/Modulus/pull/7)** still open.
- **Done:** Design locked with Core/Documentation: see [DESIGN.md](../DESIGN.md) and `InEngine/Documentation/Plan and Design.md` steps 0–13. Do not re-litigate file vs DB, discovery approach, or CLI/API-first.
- **Next:** Merge PR #7 when CI green; implement Step 1–2 (`ModulusStateStore` + service split), then discovery sync, handshake base provider, Artisan including `modulus:doctor`.
- **Open questions:** State path/format; Packagist client as namespace vs package.

### 2026-09-08 — Expanded plan sync (state store, discovery, doctor)

- **Branch:** `feat/laravel-13-support` — PR **[#7](https://github.com/InEngine/Modulus/pull/7)** still open.
- **Done:** Aligned [DESIGN.md](../DESIGN.md) with Documentation plan: multi-class Modulus, **`ModulusStateStore`** (atomic swap), PackageManifest sync (no composer.json toggle fights), Core early-boot require, SemVer/Pint/Pest/PHPStan, bus+contracts/DTOs, Composer/Packagist submodule, non-destructive disable/uninstall, **`modulus:doctor`**, Steps 0–13.
- **Next:** Merge PR #7; implement StateStore + service split; then discovery sync + doctor.
- **Open questions:** Exact state path/format; Packagist client as namespace vs separate package.

### 2026-09-08 — File registry + CLI parity (design)

- **Branch:** `feat/laravel-13-support` — PR **[#7](https://github.com/InEngine/Modulus/pull/7)** still open.
- **Done:** Documented target design in [DESIGN.md](../DESIGN.md) + README: **file-backed** state (not DB); Artisan for **every** UI action; discovery via `extra.laravel.providers` only. Modules **register with Modulus** when present and stay disabled until enabled. Modulus **syncs enable/disable into Laravel package discovery** (`PackageManifest` / packages cache)—not host `composer.json` dont-discover churn. Aligns with `InEngine/Documentation/Plan and Design.md`.
- **Next:** Merge PR #7 when CI green; implement file store, discovery sync, gating/`register` base provider, full Artisan surface + Pest; then module-skeleton pilot.
- **Open questions:** Exact state file path/format; PackageManifest decorate vs rewrite `packages.php`; uninstall always `composer remove` vs forget-only flag.

### 2026-08-28 — Laravel 13 / PHP 8.5 support

- **Branch:** `feat/laravel-13-support` — PR **[#7](https://github.com/InEngine/Modulus/pull/7)**; tracking **[#8](https://github.com/InEngine/Modulus/issues/8)**.
- **Done:** `illuminate/contracts` `^12\|\|^13`, PHP `^8.5`, testbench/CI matrix like TableUI (L13+L12). Pest **16 passed**, PHPStan clean on Laravel 13 locally. Core + Provost local dist require `@dev`.
- **Next:** Merge PR when CI green; tag release; then Provost issue **#1** (Require Modulus in Provost) can move off blocked.
