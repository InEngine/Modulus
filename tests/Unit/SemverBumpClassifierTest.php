<?php

/**
 * Unit coverage for {@see \InEngine\Modulus\Upgrades\SemverBumpClassifier} patch/minor/major classification.
 */

use InEngine\Modulus\DataTransferObjects\AvailableModuleUpdate;
use InEngine\Modulus\Upgrades\SemverBumpClassifier;

it('classifies patch minor and major bumps', function () {
    $classifier = new SemverBumpClassifier;

    expect($classifier->classify('1.2.3', '1.2.4'))->toBe(AvailableModuleUpdate::BUMP_PATCH)
        ->and($classifier->classify('1.2.3', '1.3.0'))->toBe(AvailableModuleUpdate::BUMP_MINOR)
        ->and($classifier->classify('1.2.3', '2.0.0'))->toBe(AvailableModuleUpdate::BUMP_MAJOR);
});
