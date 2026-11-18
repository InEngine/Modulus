# Inter-module integration (Step 8)

Modulus does **not** invent a second plugin API. Use Laravel’s event bus and container.

## Lifecycle events (peers / host)

`ModuleRepository` dispatches these after successful transitions:

| Event | When |
|-------|------|
| `InEngine\Modulus\Events\ModuleRegistered` | Package first tracked in state |
| `ModuleEnabled` | Full enable finished |
| `ModuleDisabled` | Disable finished (non-destructive) |
| `ModuleForgotten` | Removed from Modulus state |
| `ModuleUpgraded` | Enabled package version bump + migrate |
| `ModulePublished` | `Modulus::publish()` finished |
| `ModuleSeeded` | Confirmed `Modulus::seed()` finished |
| `ModuleManualAttentionRequired` | Upgrade/doctor surfaced operator checklist items |
| `ModuleUpdateAvailable` | Composer newer versions found (`modulus:outdated` / `modulus:update`) |

Owning-module side effects stay on `ModuleLifecycle` hooks (`activating`, …). Listen to events when **another** module or the host should react.

### modulus-ui

- Subscribe to `ModuleUpgraded` / `ModuleManualAttentionRequired` / `ModuleUpdateAvailable` and poll/cache `ModulusDoctor::run()` findings for admin notifications.
- Show outdated modules from `ModuleUpdateAvailable` or `modulus:outdated`; allow `modulus:update --auto` only when `modulus.updates.auto_patch` is on (patches within `~x.y.z`).
- “Approve upgrade” actions should call modulus-composer to rewrite host constraints (default stay on `~x.y.z`) then `modulus:upgrade`.

```php
use InEngine\Modulus\Events\ModuleEnabled;
use Illuminate\Support\Facades\Event;

Event::listen(ModuleEnabled::class, function (ModuleEnabled $event): void {
    // $event->module->package, ->enabled, ->version
});
```

## Domain events

Feature modules dispatch their own domain events the normal Laravel way (`event(new SomethingHappened(...))` or `SomethingHappened::dispatch()`). Listeners/subscribers live in the consuming module. Prefer typed event classes + DTOs over ad-hoc arrays.

## Contracts + DTOs

1. Publish a **PHP interface** in the providing module (`src/Contracts/…`).
2. Bind the implementation in that module’s provider **when enabled** (`$this->app->bind(Foo::class, FooImpl::class)`).
3. Consuming modules type-hint the interface; resolve via the container.
4. Cross-boundary payloads should be **readonly DTOs** (see `InEngine\Modulus\DataTransferObjects\ModuleStateData` as the Modulus example), not associative arrays.

Do not register a Modulus-specific service locator. Abilities/menus registries can wait.
