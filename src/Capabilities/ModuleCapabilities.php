<?php

namespace InEngine\Modulus\Capabilities;

/**
 * Normalize and shape capability descriptor payloads stored on module state.
 *
 * @phpstan-type CapabilityList list<string>
 * @phpstan-type DriverMap array<string, list<string>>
 * @phpstan-type ModuleCapabilitiesShape array{
 *     provides: CapabilityList,
 *     requires: CapabilityList,
 *     events: CapabilityList,
 *     peers: CapabilityList,
 *     drivers: DriverMap,
 *     binds: CapabilityList,
 *     label: string|null,
 *     description: string|null,
 *     kind: string
 * }
 */
final class ModuleCapabilities
{
    public const KIND_FEATURE = 'feature';

    public const KIND_LIBRARY = 'library';

    /**
     * Empty capability blob.
     *
     * @return ModuleCapabilitiesShape
     */
    public static function empty(): array
    {
        return [
            'provides' => [],
            'requires' => [],
            'events' => [],
            'peers' => [],
            'drivers' => [],
            'binds' => [],
            'label' => null,
            'description' => null,
            'kind' => self::KIND_FEATURE,
        ];
    }

    /**
     * Normalize a partial descriptor payload (from {@see ModuleCapabilityDescriptor::toArray()} or callers).
     *
     * @param  array<string, mixed>  $payload
     * @return ModuleCapabilitiesShape
     */
    public static function normalize(array $payload): array
    {
        $base = self::empty();

        $base['provides'] = self::stringList($payload['provides'] ?? []);
        $base['requires'] = self::stringList($payload['requires'] ?? []);
        $base['events'] = self::stringList($payload['events'] ?? []);
        $base['peers'] = self::stringList($payload['peers'] ?? []);
        $base['binds'] = self::stringList($payload['binds'] ?? []);
        $base['drivers'] = self::driverMap($payload['drivers'] ?? []);

        $label = $payload['label'] ?? null;
        $base['label'] = is_string($label) && $label !== '' ? $label : null;

        $description = $payload['description'] ?? null;
        $base['description'] = is_string($description) && $description !== '' ? $description : null;

        $kind = $payload['kind'] ?? self::KIND_FEATURE;
        $base['kind'] = is_string($kind) && $kind !== '' ? $kind : self::KIND_FEATURE;

        // Derive drivers from provides tokens using family:name convention when drivers map omitted entries.
        foreach ($base['provides'] as $token) {
            $separator = strrpos($token, ':');

            if ($separator === false || $separator === 0 || $separator === strlen($token) - 1) {
                continue;
            }

            $family = substr($token, 0, $separator);
            $name = substr($token, $separator + 1);

            // Tokens with a colon use family:name (e.g. person.user.driver:google, auth.method:local).
            if (! isset($base['drivers'][$family])) {
                $base['drivers'][$family] = [];
            }

            if (! in_array($name, $base['drivers'][$family], true)) {
                $base['drivers'][$family][] = $name;
            }
        }

        ksort($base['drivers']);

        return $base;
    }

    /**
     * Whether two capability blobs are equivalent.
     *
     * @param  ModuleCapabilitiesShape|null  $left
     * @param  ModuleCapabilitiesShape|null  $right
     */
    public static function equals(?array $left, ?array $right): bool
    {
        if ($left === null && $right === null) {
            return true;
        }

        if ($left === null || $right === null) {
            return false;
        }

        return self::normalize($left) === self::normalize($right);
    }

    /**
     * @return CapabilityList
     */
    protected static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $list[] = $item;
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * @return DriverMap
     */
    protected static function driverMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $family => $names) {
            if (! is_string($family) || $family === '') {
                continue;
            }

            $map[$family] = self::stringList($names);
        }

        return $map;
    }
}
