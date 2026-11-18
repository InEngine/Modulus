<?php

namespace InEngine\Modulus\Contracts;

use InEngine\Modulus\ModuleRepository;

/**
 * Module data seeder — never run automatically on enable/install.
 *
 * Invoked only through {@see ModuleRepository::seed()} after
 * the caller passes confirmed: true. Skeleton modules ship a seeder whose
 * {@see requiresConfirmation()} returns true so operators cannot seed by accident.
 *
 * Seeders must be idempotent (upsert / firstOrCreate): disable/uninstall leave data.
 */
interface ModuleSeeder
{
    /**
     * When true, {@see ModuleRepository::seed()} requires
     * $confirmed = true or it throws.
     *
     * Skeleton seeders always return true.
     */
    public function requiresConfirmation(): bool;

    /**
     * Apply seed data. Keep idempotent for re-runs after non-destructive uninstall.
     */
    public function run(): void;
}
