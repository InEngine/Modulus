# Concepts

## Install ≠ enable ≠ seed

1. **Install** — Composer puts the package on disk. Modulus does not treat that as enabled.
2. **Publish** — `Modulus::publish()` / `modulus:publish` copies config and public dist assets (install/update path). Owned by Modulus Composer when acquiring via that extension.
3. **Enable** — `modulus:enable` runs dependency checks, activate hooks, migrations, then syncs discovery. Does **not** publish or seed.
4. **Seed** — `Modulus::seed($package, confirmed: true)` only. Skeleton seeders always require confirmation.

## State store

`ModulusStateStore` (default: atomic JSON file) tracks which Composer packages are enabled or disabled. Swap implementations via `modulus.store`.

## Discovery gating

`FilteringPackageManifest` omits disabled **tracked** packages from Laravel auto-discovery. Primary gate. The base `ModuleServiceProvider` handshake is defense-in-depth if a provider still loads.

Default `modulus.discovery.scope` is `modules`. `inengine/modulus` itself is denylisted so it cannot be “disabled” out of the host.

## Data safety

| Operation | Data / schema |
|-----------|----------------|
| Disable | Non-destructive — stop loading providers; keep tables/files |
| Uninstall / forget | Non-destructive of DB by default; drops Modulus state; Composer remove is Modulus Composer’s job |
| Enable / upgrade | Run pending module migrations |

## Dependencies

Composer `require` is the hard graph. Required Modulus modules must already be **enabled**. Plain packages need only be **installed** (normal Laravel discovery). Disable/forget is blocked while enabled dependents still require the package.
