<?php

namespace InEngine\Modulus\Updates;

use Illuminate\Contracts\Events\Dispatcher;
use InEngine\Modulus\Contracts\ModuleUpdateApplier;
use InEngine\Modulus\Contracts\ModuleUpdateChecker;
use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;
use InEngine\Modulus\Events\ModuleUpdateAvailable;
use InEngine\Modulus\ModuleRepository;

/**
 * Shared CLI/UI entry: list available updates, dispatch notices, optional auto-patch apply.
 */
final class ModuleUpdateNotifier
{
    public function __construct(
        protected ModuleUpdateChecker $checker,
        protected ModuleUpdateApplier $applier,
        protected ModuleRepository $modules,
        protected Dispatcher $events,
        protected bool $autoPatch = false,
    ) {}

    /**
     * @return list<AvailableModuleUpdate>
     */
    public function check(bool $dispatch = true): array
    {
        $updates = $this->checker->availableUpdates();

        if ($dispatch && $updates !== []) {
            $this->events->dispatch(new ModuleUpdateAvailable($updates));
        }

        return $updates;
    }

    /**
     * Apply auto-eligible patch updates (requires a real ModuleUpdateApplier), then upgrade state.
     *
     * @return array{found: list<AvailableModuleUpdate>, applied: list<string>}
     */
    public function autoApplyPatches(): array
    {
        $updates = $this->check(dispatch: true);
        $eligible = array_values(array_filter(
            $updates,
            static fn (AvailableModuleUpdate $update): bool => $update->autoEligible,
        ));

        if ($eligible === [] || ! $this->autoPatch) {
            return ['found' => $updates, 'applied' => []];
        }

        $applied = $this->applier->apply($eligible);

        foreach ($applied as $package) {
            if ($this->modules->get($package) !== null) {
                $this->modules->upgrade($package, publish: true);
            }
        }

        return ['found' => $updates, 'applied' => $applied];
    }
}
