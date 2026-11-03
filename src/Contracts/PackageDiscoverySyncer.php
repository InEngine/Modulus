<?php

namespace InEngine\Modulus\Contracts;

/**
 * Refreshes Laravel's effective package discovery after Modulus state changes.
 */
interface PackageDiscoverySyncer
{
    /**
     * Invalidate cached discovery so the next provider resolution sees current state.
     */
    public function sync(): void;
}
