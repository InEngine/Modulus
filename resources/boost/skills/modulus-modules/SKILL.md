---
name: modulus-modules
description: "Use when working with inengine/modulus APIs, ModuleServiceProvider, enable/disable/publish/seed, module Composer dependencies, or lifecycle events/contracts/DTOs."
---

# Modulus modules

## Install ≠ enable ≠ seed

`composer require` → `Modulus::publish($pkg)` → `Modulus::enable($pkg)` → optional `Modulus::seed($pkg, confirmed: true)`.

## Prefer

- `InEngine\Modulus\Facades\Modulus` / `ModuleRepository`
- Lifecycle hooks on the owning `ModuleServiceProvider`
- Laravel events (`InEngine\Modulus\Events\*`) for peers
- Container-bound contracts + readonly DTOs

## Avoid

- Composer hooks / UI / marketplace inside `inengine/modulus`
- Schema drops on disable/forget
- Ad-hoc associative arrays as long-term cross-module payloads

See package `INTEGRATION.md` and `resources/boost/guidelines/core.blade.php`.
