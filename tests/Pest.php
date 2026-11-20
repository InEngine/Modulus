<?php

/**
 * Pest bootstrap: binds {@see TestCase} (Orchestra Testbench) to every test in this directory.
 */

use InEngine\Modulus\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);
