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

### 2026-11-19 — Licensing: MIT OSS tier; UI private Pro

- **Branch:** `feat/modulus-state-store`.
- **Done:** Modulus (+ Core / Composer / Skeleton) **MIT**. DESIGN/README: **`modulus-ui` = private Pro**; **Innkeeper = unreleased source**.
- **Next:** Marketplace later; scaffold private Pro UI when ready (separate license, not inside MIT packages).
- **Open questions:** Commercial license text for `modulus-ui`.

### 2026-11-19 — Step 10 ModulusComposer scaffold; Skeleton rename

- **Branch:** `feat/modulus-state-store` (approve messaging points at `modulus-composer:approve`).
- **Done:** Sibling package `Modules/ModulusComposer` owns Composer I/O + real `ModuleUpdateApplier`. Template repo renamed `InEngine/Skeleton` (`Modules/Skeleton`).
- **Next:** Green tests on ModulusComposer; GitHub repo; hosts opt in via path require (Core stays without hard require).
- **Open questions:** Doctor substitution UX; Composer Plugin hooks deferred.

### 2026-11-19 — Resume after Atlas; Steps 0–9 + 8b complete

- **Branch:** `feat/modulus-state-store` @ `754dedd`.
- **Where we are:** Steps **0–9 + 8b** done.
- **Next:** Step 10 (see newer entry).
- **Open questions:** Private Packagist auth UX; doctor substitution.

### 2026-11-17 — Step 6: publish on install; seed explicit + confirmed

- **Branch:** `feat/modulus-state-store` (later committed as `949484e` and beyond).
- **Done:** `Modulus::publish()` for install (composer); enable = migrate + hooks only. `Modulus::seed($pkg, confirmed: true)` only — skeleton `ModuleSeeder` always `requiresConfirmation(): true`; never seed on enable. Dist assets on publish; UI overrides opt-in. Idempotent migration stub.
- **Next:** *(Superseded — Steps 7–9 landed; see 2026-11-19.)*
- **Open questions:** Path-dev manual `Modulus::publish()` when UI assets needed before enable.

### 2026-11-17 — Step 6 lifecycle + migrations (Atlas)

- **Branch:** `feat/modulus-state-store` (superseded by publish/idempotent follow-up above).
- **Done:** Initial Step 6 hooks + migrate pipeline (see newer entry).
- **Next:** (see newer entry)
- **Open questions:** None.

### 2026-11-03 — Checkpoint: through Step 5; next Step 6

- **Branch:** `feat/modulus-state-store` (ahead of origin — push when ready).
- **Where we are:** Steps **1–5** landed on this branch (state store, discovery filter, repository, handshake, Core host require). Also: `InvalidModulusStateStoreException`, `MissingModuleException` (enabled throw / disabled log), `ModulusStateStoreResolver`, Pest **69** green; doctor missing-module repair planned in Plan Step 9.
- **Next:** **Step 6** lifecycle hooks + non-destructive rules + migrations on enable. Then deps (7), bus/contracts (8), full Artisan/`modulus:doctor` (9).
- **Open questions:** Doctor substitute UX; when to merge to `main` / retarget skeleton+Core CI refs.

### 2026-11-03 — Early installer Filesystem fix (Step 5 unblock)

- **Branch:** `feat/modulus-state-store`
- **Done:** `PackageManifestInstaller` constructs `Filesystem` directly (and for state store when `files` unbound) so Core can boot during `RegisterProviders`.
- **Next:** Step 6 lifecycle hooks; merge branch toward `main` when ready.
- **Open questions:** None.

### 2026-11-03 — Step 4b skeleton started (parallel)

- **Branch:** `feat/modulus-state-store` (Step 4 uncommitted polish still local)
- **Done:** `InEngine/module-skeleton` live — https://github.com/InEngine/module-skeleton — extends `ModuleServiceProvider`, capability stub, path require to this package.
- **Next:** Commit Step 4 handshake here; keep reviewing while skeleton iterates.
- **Open questions:** None.

### 2026-11-03 — Step 4 handshake

- **Branch:** `feat/modulus-state-store`
- **Done:** Hardened `ModuleServiceProvider`: `packageRegistered` → register; `boot()` skips Spatie hooks unless enabled; standalone when Modulus absent; version hook; Pest (register while disabled, skip boot, enable then boot, standalone, no-register when absent). Suite **50 passed**.
- **Next:** Commit Step 4; Step 5 Core require Modulus.
- **Open questions:** None.

### 2026-11-03 — Extension naming locked

- **Done (docs):** `inengine/modulus-{role}` naming + module guidelines rule.
- **Next:** (see newer entry)
- **Open questions:** None on naming unless Packagist conflict at publish.

### 2026-11-03 — Step 1a test gaps closed

- **Branch:** `feat/modulus-state-store`
- **Done:** Step 1 + 1a committed (`2cbb102`). Suite **47 passed**.
- **Next:** Step 4 handshake polish.
- **Open questions:** None.

### 2026-11-03 — Step 1 complete + old API cleanup

- **Branch:** `feat/modulus-state-store`
- **Done:** `ModuleRepository` + syncer + facade + base `ModuleServiceProvider`; removed old modules.php rewrite stack.
- **Next:** (see newer entry)
- **Open questions:** None.

### 2026-11-03 — Future: skeleton + capability catalog

- **Done (docs):** Modulus-compatible modules **must** use `InEngine/module-skeleton` (custom Spatie template). Planned **capability catalog** extension (Step 14): inspect provides/requires, events, peers/sub-modules, custom integrate guidance — richer than Composer suggest; not Modulus core.
- **Next:** Commit pending Step 3 work; `ModuleRepository`; Step 4 handshake.
- **Open questions:** Capability descriptor format (PHP class vs JSON); catalog package name.

### 2026-11-03 — Step 3 PackageManifest sync

- **Branch:** `feat/modulus-state-store`
- **Done:** `FilteringPackageManifest`; `PackageManifestInstaller`; discovery config; Pest; Core bootstrap wire.
- **Next:** Commit Step 3; `ModuleRepository` + facade; Step 4 handshake.
- **Open questions:** Marketplace / catalog package names.

### 2026-11-03 — Design: scope=all + marketplace extension

- **Branch:** `feat/modulus-state-store`
- **Done:** Locked design: optional `discovery.scope=all`; marketplace = separate extension; defense in depth required.
- **Next:** (see newer entry)
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
