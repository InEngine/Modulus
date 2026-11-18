<?php

namespace InEngine\Modulus\Contracts;

use InEngine\Modulus\ModuleRepository;

/**
 * Resolves a module's {@see ModuleLifecycle} (when available) and invokes hooks.
 *
 * Used by {@see ModuleRepository} so orchestration stays free of
 * provider-discovery details. Missing / non-Modulus packages are silent no-ops.
 */
interface ModuleLifecycleRunner extends ModuleLifecycle {}
