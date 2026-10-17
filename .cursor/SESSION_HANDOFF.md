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

### 2026-08-28 — Laravel 13 / PHP 8.5 support

- **Branch:** `feat/laravel-13-support` — PR **[#7](https://github.com/InEngine/Modulus/pull/7)**; tracking **[#8](https://github.com/InEngine/Modulus/issues/8)**.
- **Done:** `illuminate/contracts` `^12\|\|^13`, PHP `^8.5`, testbench/CI matrix like TableUI (L13+L12). Pest **16 passed**, PHPStan clean on Laravel 13 locally. Core + Provost local dist require `@dev`.
- **Next:** Merge PR when CI green; tag release; then Provost issue **#1** (Require Modulus in Provost) can move off blocked.
