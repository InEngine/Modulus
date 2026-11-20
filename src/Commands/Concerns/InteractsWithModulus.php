<?php

namespace InEngine\Modulus\Commands\Concerns;

use InEngine\Modulus\Exceptions\MissingModuleException;
use InEngine\Modulus\Exceptions\ModuleHasDependentsException;
use InEngine\Modulus\Exceptions\ModuleSeederMissingException;
use InEngine\Modulus\Exceptions\SeedingConfirmationRequiredException;
use InEngine\Modulus\Exceptions\UnmetModuleDependencyException;
use InEngine\Modulus\ModuleRepository;
use Throwable;

/**
 * Shared helpers for Modulus Artisan commands.
 *
 * Resolves the {@see \InEngine\Modulus\ModuleRepository} facade-level orchestrator and maps expected
 * domain exceptions to a failure exit code. Intended for use on {@see \Illuminate\Console\Command} subclasses.
 */
trait InteractsWithModulus
{
    /** Resolve the {@see \InEngine\Modulus\ModuleRepository} from the container. */
    protected function modulus(): ModuleRepository
    {
        return app(ModuleRepository::class);
    }

    /**
     * Print the exception message and map expected Modulus failures to an exit code.
     *
     * Dependency, missing-module, seeding, and invalid-argument errors return {@see self::FAILURE};
     * any other {@see Throwable} is rethrown.
     *
     * @return int Command exit code ({@see self::FAILURE}).
     */
    protected function reportModulusException(Throwable $e): int
    {
        $this->error($e->getMessage());

        if ($e instanceof UnmetModuleDependencyException
            || $e instanceof ModuleHasDependentsException
            || $e instanceof MissingModuleException
            || $e instanceof SeedingConfirmationRequiredException
            || $e instanceof ModuleSeederMissingException
            || $e instanceof \InvalidArgumentException
        ) {
            return self::FAILURE;
        }

        throw $e;
    }
}
