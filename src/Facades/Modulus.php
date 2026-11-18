<?php

namespace InEngine\Modulus\Facades;

use Illuminate\Support\Facades\Facade;
use InEngine\Modulus\ModuleRepository;

/**
 * @method static array all()
 * @method static array enabled()
 * @method static array disabled()
 * @method static array|null get(string $package)
 * @method static bool isEnabled(string $package)
 * @method static void register(string $package, string|null $version = null)
 * @method static int discover()
 * @method static bool upgrade(string $package, bool $publish = false)
 * @method static int upgradeAll(bool $publish = false)
 * @method static void publish(string $package)
 * @method static void seed(string $package, bool $confirmed = false)
 * @method static void enable(string $package)
 * @method static void disable(string $package)
 * @method static void forget(string $package)
 * @method static void uninstall(string $package)
 * @method static void validateTrackedPackages()
 * @method static bool isDenied(string $package)
 *
 * @see ModuleRepository
 */
class Modulus extends Facade
{
    /**
     * {@inheritdoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return ModuleRepository::class;
    }
}
