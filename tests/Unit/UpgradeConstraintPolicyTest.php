<?php

use InEngine\Modulus\Upgrades\UpgradeConstraintPolicy;

it('suggests tilde constraints for the default patch policy', function () {
    $policy = new UpgradeConstraintPolicy(UpgradeConstraintPolicy::PATCH);

    expect($policy->suggestConstraint('1.2.3'))->toBe('~1.2.3')
        ->and($policy->constraintAllowsUnapprovedBumps('^1.2.3'))->toBeTrue()
        ->and($policy->constraintAllowsUnapprovedBumps('~1.2.3'))->toBeFalse()
        ->and($policy->constraintAllowsUnapprovedBumps('*'))->toBeTrue();
});

it('treats caret as acceptable under minor policy', function () {
    $policy = new UpgradeConstraintPolicy(UpgradeConstraintPolicy::MINOR);

    expect($policy->suggestConstraint('1.2.3'))->toBe('^1.2.3')
        ->and($policy->constraintAllowsUnapprovedBumps('^1.2.3'))->toBeFalse()
        ->and($policy->constraintAllowsUnapprovedBumps('*'))->toBeTrue();
});
