<?php

namespace InEngine\Modulus\Commands\Concerns;

use InEngine\Modulus\Exceptions\MissingModuleException;
use InEngine\Modulus\Exceptions\ModuleHasDependentsException;
use InEngine\Modulus\Exceptions\ModuleSeederMissingException;
use InEngine\Modulus\Exceptions\SeedingConfirmationRequiredException;
use InEngine\Modulus\Exceptions\UnmetModuleDependencyException;
use InEngine\Modulus\ModuleRepository;
use Throwable;

trait InteractsWithModulus
{
    protected function modulus(): ModuleRepository
    {
        return app(ModuleRepository::class);
    }

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
