# Modulus Composer

**Display name:** Modulus Composer  
**Package:** [`inengine/modulus-composer`](https://github.com/InEngine/ModulusComposer)  
**Repo:** [InEngine/ModulusComposer](https://github.com/InEngine/ModulusComposer)

Optional Modulus **extension** (not a feature module). Owns all Composer shell I/O so `inengine/modulus` stays lean.

## Responsibilities

- `composer require` / `remove` / `update`
- Host `composer.json` constraint rewrite under Modulus policy (`~x.y.z` by default)
- Bind real `ModuleUpdateApplier` (replaces Modulus `NullModuleUpdateApplier`)
- After acquire: `Modulus::discover()` + `Modulus::publish()` — **never** enable unless `--enable`
- Before remove: `Modulus::forget()` then `composer remove`

## Artisan

```bash
php artisan modulus-composer:require inengine/alert
php artisan modulus-composer:require inengine/alert 1.2.3 --enable
php artisan modulus-composer:remove inengine/alert
php artisan modulus-composer:update inengine/alert
php artisan modulus-composer:approve inengine/alert 2.0.0
```

Private Packagist / Satis / VCS: use Composer `repositories` and `auth.json`. Modulus Composer does not store credentials.

## Host wiring

Opt in only:

```json
{
  "require": {
    "inengine/modulus-composer": "*@dev"
  }
}
```

InEngine Core should **not** hard-require this package.
