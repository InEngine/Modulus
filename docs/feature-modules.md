# Feature modules

## Template

Start from **[InEngine/Skeleton](https://github.com/InEngine/Skeleton)** (GitHub **template** repository — not a Packagist package).

## Rules

1. Name: `inengine/{feature}` (e.g. `inengine/alert`). Never `inengine/modulus-*` (reserved for runtime extensions).
2. Extend `InEngine\Modulus\ModuleServiceProvider` and implement `modulusPackageName()`.
3. Declare providers only in `composer.json` `extra.laravel.providers`.
4. When Modulus is present: register, default **disabled**, boot only when enabled.
5. When Modulus is absent: boot fully (standalone Spatie package).
6. Ship migrations that tolerate re-enable (`Schema::hasTable` guards). Disable/uninstall stay non-destructive.
7. Override lifecycle hooks (`activating`, …) for module-owned side effects; use events for peers/host — see [Integration](integration.md).
8. Fill `Capabilities\ModuleCapabilityDescriptor` (and optional Composer `provide`) so the [Capability Registry](STEP-14.md) can index the module while disabled.

## Quality bar

SemVer, Pint, Pest, and PHPStan — same as Core and Modulus.
