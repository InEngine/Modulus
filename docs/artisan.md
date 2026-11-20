# Artisan commands

All operator capabilities are available on the CLI (UI must share the same API).

| Command | Purpose |
|---------|---------|
| `modulus:list` | List tracked modules and enablement |
| `modulus:discover` | Scan installed packages → register with Modulus (disabled by default) |
| `modulus:enable {package}` | Enable + migrate + lifecycle |
| `modulus:disable {package}` | Disable (non-destructive) |
| `modulus:uninstall {package}` | Forget from state (non-destructive of DB) |
| `modulus:status {package}` | Status for one package |
| `modulus:publish {package}` | Publish config/dist assets |
| `modulus:seed {package}` | Confirmed seed only |
| `modulus:upgrade {package?} {--publish} {--approve=}` | Apply installed version migrate/hooks; approve messaging for bumps |
| `modulus:outdated` | List available Composer updates for tracked modules |
| `modulus:update {--auto}` | Notify / optionally auto-apply patch updates |
| `modulus:doctor` | Diagnostics (missing packages, deps, constraint policy, updates) |
| `modulus:capabilities {package?} {--token=} {--drivers=} {--all} {--json}` | Capability Registry (provides / requires / drivers) |

### Notes

- `modulus:update --auto` requires `modulus.updates.auto_patch=true` **and** a real `ModuleUpdateApplier` from **Modulus Composer**.
- `modulus:upgrade --approve=` prints the suggested `~x.y.z` constraint; use `php artisan modulus-composer:approve {package} {version}` to rewrite the host lock.
