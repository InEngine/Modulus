# Upgrades and SemVer

## Host constraint policy

Default `modulus.upgrades.default_constraint` is **`patch`**, which means host apps should require modules as **`~x.y.z`** (same minor only).

| Bump | Automatic? |
|------|------------|
| Patch within `~x.y.z` | May apply via `composer update` / optional `modulus:update --auto` |
| Minor or major | Needs explicit approval |

`modulus:doctor` warns when root constraints are looser than policy (`^`, `*`, etc.).

## Flow

1. `modulus:outdated` / doctor `update_available` / `ModuleUpdateAvailable` — notice only.
2. Patch auto-apply (optional): Modulus Composer bound + `modulus.updates.auto_patch=true` + `modulus:update --auto`.
3. Approve bump: `modulus-composer:approve {package} {version}` rewrites constraint, updates, then Modulus upgrade/publish.
4. Enabled modules can auto-apply **already installed** version changes on boot (`modulus.upgrades.auto_apply_installed`) via migrate + hooks.

## Manual attention

Providers may override `modulusManualAttentionItems()` for post-upgrade checklists. Modulus dispatches `ModuleManualAttentionRequired` for operator UIs.
