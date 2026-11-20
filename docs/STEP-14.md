# Step 14 — Capability Registry (in-core)

Capability discovery for Modulus-compatible modules lives **in `inengine/modulus`**, not a separate registry package.

## Goals

- Answer “what does this module provide / require / peer with?” for **installed** packages, including **disabled** ones (providers may not boot).
- Index soft capability **tokens** and driver families so peers (User, Person, Alert, …) can query without hard Composer requires for every optional peer.
- Keep **bindings** in providing modules’ service providers (Laravel-normal). The registry does **not** instantiate implementations.

## System of record

1. Modules expose a static `ModuleCapabilityDescriptor` (skeleton stub) readable without booting.
2. Handshake `ModuleRepository::register()` and `discover()` persist a `capabilities` blob on the package’s `ModulusStateStore` entry.
3. `CapabilityRegistry` reads that store (enabled-aware queries).

Optional thin Artisan (`modulus:capabilities`) only **reads** the registry — it is not a second catalog. A future `modulus-inspect` Pro/UI layer may present the same data.

## Dual support (Skeleton)

| Mechanism | Audience |
|-----------|----------|
| Descriptor → Modulus Registry | InEngine hosts |
| Composer [`provide`](https://getcomposer.org/doc/04-schema.md#provide) | Packages reused outside Modulus |

## Multi-implementation (drivers)

Do **not** rebind one interface three ways (last enable wins). Named drivers + a Manager (Person `user(type)` pattern) — see User `IDENTITY_ARCHITECTURE.md` and platform plan.

## Token convention

- Soft capabilities: `alerts.dispatch`, `identity.demographics`
- Drivers: `family:name` e.g. `person.user.driver:google`
- Auth methods: `auth.method:local`, `auth.method:google`

## Non-goals

- Registry as a plugin container / service locator for concrete classes
- Replacing Composer hard `require` with tokens alone
- Database-backed module registry
