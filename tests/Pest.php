<?php

/**
 * Pest bootstrap: binds {@see \InEngine\Modulus\Tests\TestCase} (Orchestra Testbench) to every test in this directory.
 */

use InEngine\Modulus\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);
