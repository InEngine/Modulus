<?php

namespace InEngine\Modulus\Commands;

use Illuminate\Console\Command;
use InEngine\Modulus\Capabilities\CapabilityRegistry;
use InEngine\Modulus\Commands\Concerns\InteractsWithModulus;

/**
 * Artisan `modulus:capabilities` — list or show capability registry data (read-only).
 */
class CapabilitiesCommand extends Command
{
    use InteractsWithModulus;

    protected $signature = 'modulus:capabilities
                            {package? : Composer package name to show}
                            {--token= : List packages that provide this capability token}
                            {--drivers= : List driver names for a family (e.g. person.user.driver)}
                            {--all : Include disabled modules when filtering by token/drivers}
                            {--json : Machine-readable JSON}';

    protected $description = 'Inspect Modulus capability registry (provides / requires / drivers)';

    public function handle(CapabilityRegistry $registry): int
    {
        $package = $this->argument('package');
        $token = $this->option('token');
        $drivers = $this->option('drivers');
        $enabledOnly = ! (bool) $this->option('all');

        if (is_string($token) && $token !== '') {
            return $this->renderProviders($registry, $token, $enabledOnly);
        }

        if (is_string($drivers) && $drivers !== '') {
            return $this->renderDrivers($registry, $drivers, $enabledOnly);
        }

        if (is_string($package) && $package !== '') {
            return $this->renderPackage($registry, $package);
        }

        return $this->renderAll();
    }

    protected function renderAll(): int
    {
        $payload = [];
        $rows = [];

        foreach ($this->modulus()->all() as $package => $state) {
            /** @var list<string> $provides */
            $provides = $state['capabilities']['provides'] ?? [];
            $status = $state['enabled'] ? 'enabled' : 'disabled';

            $payload[] = [
                'package' => $package,
                'status' => $status,
                'provides' => $provides,
            ];
            $rows[] = [
                $package,
                $status,
                $provides === [] ? '—' : implode(', ', $provides),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->info('No modules tracked yet. Run modulus:discover.');

            return self::SUCCESS;
        }

        $this->table(['Package', 'Status', 'Provides'], $rows);

        return self::SUCCESS;
    }

    protected function renderPackage(CapabilityRegistry $registry, string $package): int
    {
        $capabilities = $registry->capabilitiesFor($package);

        if ($capabilities === null) {
            $this->error("No capabilities recorded for [{$package}]. Run modulus:discover or enable the module once.");

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'package' => $package,
                'capabilities' => $capabilities,
                'missing_requirements' => $registry->missingRequirements($package),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info($package);
        $this->line('Provides: '.$this->listOrDash($capabilities['provides']));
        $this->line('Requires: '.$this->listOrDash($capabilities['requires']));
        $this->line('Peers: '.$this->listOrDash($capabilities['peers']));
        $this->line('Events: '.$this->listOrDash($capabilities['events']));
        $this->line('Binds: '.$this->listOrDash($capabilities['binds']));

        if ($capabilities['drivers'] !== []) {
            foreach ($capabilities['drivers'] as $family => $names) {
                $this->line("Drivers [{$family}]: ".implode(', ', $names));
            }
        }

        $missing = $registry->missingRequirements($package);

        if ($missing !== []) {
            $this->warn('Missing requirements: '.implode(', ', $missing));
        }

        return self::SUCCESS;
    }

    protected function renderProviders(CapabilityRegistry $registry, string $token, bool $enabledOnly): int
    {
        $providers = $registry->providersOf($token, $enabledOnly);

        if ($this->option('json')) {
            $this->line(json_encode([
                'token' => $token,
                'enabled_only' => $enabledOnly,
                'providers' => $providers,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($providers === []) {
            $this->warn("No providers for token [{$token}].");

            return self::SUCCESS;
        }

        foreach ($providers as $provider) {
            $this->line($provider);
        }

        return self::SUCCESS;
    }

    protected function renderDrivers(CapabilityRegistry $registry, string $family, bool $enabledOnly): int
    {
        $names = $registry->driversOf($family, $enabledOnly);

        if ($this->option('json')) {
            $this->line(json_encode([
                'family' => $family,
                'enabled_only' => $enabledOnly,
                'drivers' => $names,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($names === []) {
            $this->warn("No drivers for family [{$family}].");

            return self::SUCCESS;
        }

        foreach ($names as $name) {
            $this->line($name);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $items
     */
    protected function listOrDash(array $items): string
    {
        return $items === [] ? '—' : implode(', ', $items);
    }
}
