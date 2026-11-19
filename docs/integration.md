# Integration

Modulus does **not** invent a second plugin API. Use Laravel’s event bus and container.

## Lifecycle events

Dispatched by `ModuleRepository` after successful transitions (see root [INTEGRATION.md](../INTEGRATION.md)):

- `ModuleRegistered`, `ModuleEnabled`, `ModuleDisabled`, `ModuleForgotten`
- `ModuleUpgraded`, `ModulePublished`, `ModuleSeeded`
- `ModuleManualAttentionRequired`, `ModuleUpdateAvailable`

Owning module: `ModuleLifecycle` hooks on the provider. Peers/host: listen to events.

## Contracts and DTOs

1. Publish a PHP interface in the providing module.
2. Bind the implementation when the module is **enabled**.
3. Cross-boundary payloads: readonly DTOs (e.g. `ModuleStateData`), not ad-hoc arrays.

## Domain events

Feature modules dispatch their own domain events the normal Laravel way.
