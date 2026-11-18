# InEngine Modulus (package repo)

This repository is `inengine/modulus` (Spatie package + Orchestra Testbench), not a full Laravel app.

## Commands

- `vendor/bin/pest` — run tests
- `vendor/bin/phpstan analyse` — static analysis
- `vendor/bin/pint` — format PHP
- Prefer Testbench/`composer test` scripts in `composer.json`

## Agent rules

- Follow `resources/boost/guidelines/core.blade.php` (also published to consuming hosts via Laravel Boost).
- Read `DESIGN.md` and `docs/INTEGRATION.md` before changing lifecycle, discovery, deps, or events.
- Update `CHANGELOG.md` Unreleased when behavior changes.
- Do not add Composer require/remove hooks, marketplace, or operator UI here — those are `inengine/modulus-*` extensions.
- Prefer PHPDoc blocks over inline comments.
- Never invent a second plugin API; use Laravel events + container bindings.
